<?php

declare(strict_types=1);

namespace CoreX\OfflineMigration;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use SQLite3;

/**
 * @phpstan-type SqlRow array<string, int|float|string|null>
 * @phpstan-type MappedRow array{source_pk: string, source_digest: string, target: array<string, string>}
 */
final class SourceSnapshot
{
    public const string Artifact = '1c11e0e35961409631cf68ebb28c7d2cd53ebb91';

    public const string Schema = 'c0b0c270ab6ca520572a58a6036b1fea636f6d5158bb55d4442f0a681897eb6a';

    public const string Codec = 'corex-v1-webhooks-sqlite-scope-v2';

    public readonly string $path;

    /** @var array{device: string, inode: string} */
    public readonly array $fileIdentity;

    private readonly SQLite3 $db;

    /** @param array<string, mixed> $descriptor */
    public function __construct(public readonly array $descriptor)
    {
        exactFields($descriptor, [
            'key_id', 'principal', 'artifact_sha', 'deployment_id', 'database_id',
            'snapshot_id', 'snapshot_sha256', 'schema_sha256', 'codec', 'database',
        ], 'source descriptor');
        check(shaField($descriptor, 'artifact_sha', 40) === self::Artifact, 'Unsupported 1.x artifact.');
        check(shaField($descriptor, 'schema_sha256') === self::Schema && field($descriptor, 'codec') === self::Codec,
            'Unsupported source schema or codec.');
        foreach (['deployment_id', 'database_id', 'snapshot_id'] as $key) {
            field($descriptor, $key);
        }

        $this->path = fullPath(field($descriptor, 'database'));
        $this->fileIdentity = fileIdentity($this->path);
        $this->assertSealed();
        $this->db = database($this->path, write: false);
        $this->db->exec('BEGIN');
        check($this->db->querySingle('PRAGMA journal_mode') === 'delete', 'Source must use a sealed rollback-journal snapshot.');
        check(schemaFingerprint($this->db) === self::Schema, 'Source schema fingerprint changed.');
        $this->assertSealed();
    }

    public function assertSealed(): void
    {
        foreach (['-wal', '-shm', '-journal'] as $suffix) {
            clearstatcache(true, $this->path.$suffix);
            check(! file_exists($this->path.$suffix), 'Source snapshot has an active journal or WAL.');
        }
        check(fileIdentity($this->path) === $this->fileIdentity
            && fileDigest($this->path) === shaField($this->descriptor, 'snapshot_sha256'), 'Source snapshot identity or digest changed.');
    }

    /** @return array{subscriptions: list<MappedRow>, deliveries: list<MappedRow>} */
    public function map(string $account, string $sourceEpoch): array
    {
        $mappedSubscriptions = [];
        $subscriptions = [];
        foreach (rows($this->db, 'SELECT * FROM sys_api_webhook_subscriptions ORDER BY id COLLATE BINARY') as $subscription) {
            $id = self::text($subscription, 'id');
            self::validateSubscription($subscription, $id);
            $subscriptions[$id] = $subscription;
            $normalized = $subscription;
            $normalized['created_at'] = self::utc(self::text($subscription, 'created_at'), $id);
            $normalized['updated_at'] = self::utc(self::text($subscription, 'updated_at'), $id);
            $mappedSubscriptions[] = self::mapped($id, $subscription, [
                'id' => $id,
                'status' => self::text($subscription, 'status'),
                'subscription_json' => canonical($normalized),
            ]);
        }

        $attempts = [];
        foreach (rows($this->db, 'SELECT * FROM sys_api_webhook_attempts ORDER BY delivery_id COLLATE BINARY, attempt') as $attempt) {
            $attempts[self::text($attempt, 'delivery_id')][] = $attempt;
        }

        $deliveries = [];
        foreach (rows($this->db, 'SELECT * FROM sys_api_webhook_deliveries ORDER BY id COLLATE BINARY') as $delivery) {
            $id = self::text($delivery, 'id');
            $subscription = $subscriptions[self::text($delivery, 'subscription_id')] ?? null;
            check($subscription !== null, "Orphan webhook delivery [{$id}].", 3);
            $deliveryAttempts = $attempts[$id] ?? [];
            unset($attempts[$id]);
            $deliveries[] = self::mapDelivery($delivery, $subscription, $deliveryAttempts, $account, $sourceEpoch);
        }
        check($attempts === [], 'Orphan webhook attempt in source.', 3);
        check($mappedSubscriptions !== [] && $deliveries !== [], 'The prototype requires a nonempty representative slice.', 3);
        $this->assertSealed();

        return ['subscriptions' => $mappedSubscriptions, 'deliveries' => $deliveries];
    }

    /** @param SqlRow $row */
    private static function validateSubscription(array $row, string $id): void
    {
        check($id !== '' && $row['major'] === 'v1'
            && is_int($row['revision']) && is_int($row['topic_version'])
            && in_array($row['status'], ['active', 'disabled'], true), "Unsupported subscription [{$id}].", 3);
        $refs = self::json(self::text($row, 'key_refs'), $id);
        check(is_array($refs) && array_is_list($refs) && count($refs) >= 1 && count($refs) <= 2
            && array_filter($refs, is_string(...)) === $refs, "Invalid key references [{$id}].", 3);
    }

    /**
     * @param  SqlRow  $row
     * @param  SqlRow  $subscription
     * @param  list<SqlRow>  $attempts
     * @return MappedRow
     */
    private static function mapDelivery(array $row, array $subscription, array $attempts, string $account, string $sourceEpoch): array
    {
        $id = self::text($row, 'id');
        $subscriptionId = self::text($row, 'subscription_id');
        check($id === substr(hash('sha256', $subscriptionId.'|'.self::text($row, 'event_id')), 0, 32)
            && $row['body_hash'] === hash('sha256', self::text($row, 'body'))
            && $row['major'] === 'v1' && is_int($row['attempt']) && is_int($row['replay_count'])
            && is_int($row['subscription_revision']) && is_int($row['topic_version']),
            "Invalid source identity or payload [{$id}].", 3);

        $scope = self::json(self::text($row, 'scope'), $id);
        check(is_array($scope) && count($scope) === 7 && array_key_exists('workspace', $scope)
            && ($scope['version'] ?? null) === 2 && ($scope['boxed'] ?? null) === true
            && ($scope['account'] ?? null) === $account && $scope['workspace'] === null
            && ($scope['connection'] ?? null) === 'boxed:'.$account
            && ($scope['restoreEpoch'] ?? null) === $sourceEpoch
            && is_string($scope['actor'] ?? null) && $scope['actor'] !== '', "Unknown source scope [{$id}].", 3);
        $disposition = self::disposition($row, $subscription, $attempts);
        $source = ['delivery' => $row, 'attempts' => $attempts];

        foreach (['created_at', 'expires_at', 'next_attempt_at'] as $column) {
            $row[$column] = self::utc(self::text($row, $column), $id);
        }

        if ($row['finished_at'] !== null) {
            $row['finished_at'] = self::utc(self::text($row, 'finished_at'), $id);
        }
        foreach ($attempts as $index => $attempt) {
            $attempts[$index]['started_at'] = self::utc(self::text($attempt, 'started_at'), $id);
            $attempts[$index]['finished_at'] = self::utc(self::text($attempt, 'finished_at'), $id);
        }

        // The old HTTP delivery ID is the stable effect key. Historical POST bytes remain opaque.
        unset($row['id'], $row['subscription_id'], $row['scope'], $row['lease_owner'], $row['lease_until']);
        $row['scope'] = ['account' => $account, 'workspace' => null, 'actor' => $scope['actor']];

        return self::mapped($id, $source, [
            'effect_key' => $id,
            'subscription_id' => $subscriptionId,
            'disposition' => $disposition,
            'delivery_json' => canonical(['delivery' => $row, 'attempts' => $attempts]),
        ]);
    }

    /**
     * @param  SqlRow  $row
     * @param  SqlRow  $subscription
     * @param  list<SqlRow>  $attempts
     * @return 'pending'|'history'
     */
    private static function disposition(array $row, array $subscription, array $attempts): string
    {
        $id = self::text($row, 'id');
        $status = self::text($row, 'status');
        check($row['lease_owner'] === null && $row['lease_until'] === null && $row['replay_count'] === 0,
            "Unknown or leased webhook effect [{$id}].", 3);

        if ($status === 'pending') {
            check($row['attempt'] === 0 && $attempts === [] && $row['finished_at'] === null && $row['last_error'] === null
                && $subscription['status'] === 'active' && $row['subscription_revision'] === $subscription['revision']
                && $row['topic'] === $subscription['topic'] && $row['topic_version'] === $subscription['topic_version']
                && $row['endpoint_snapshot'] === $subscription['endpoint_url'], "Webhook pre-send state is unproven [{$id}].", 3);

            return 'pending';
        }

        if ($status === 'succeeded' && $row['attempt'] === 1 && count($attempts) === 1) {
            $attempt = $attempts[0];
            check($attempt['attempt'] === 1 && $attempt['id'] === substr(hash('sha256', $id.'|1'), 0, 32)
                && $attempt['outcome'] === 'succeeded' && $attempt['error_code'] === null
                && is_int($attempt['http_status']) && $attempt['http_status'] >= 200 && $attempt['http_status'] < 300
                && $row['finished_at'] !== null && $attempt['finished_at'] === $row['finished_at'] && $row['last_error'] === null,
                "Missing successful webhook receipt [{$id}].", 3);

            return 'history';
        }

        if ($status === 'dead' && $row['attempt'] === 0 && $attempts === []
            && $row['finished_at'] !== null && $row['last_error'] === 'payload_too_large') {
            return 'history';
        }

        throw new MigrationError("Unknown or unsupported webhook effect [{$id}] with status [{$status}].", 3);
    }

    private static function json(string $value, string $id): mixed
    {
        try {
            return json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MigrationError("Invalid JSON in source row [{$id}].", 3);
        }
    }

    private static function utc(string $value, string $id): string
    {
        // The SQLite owner stores offset-free values in its UTC connection.
        $format = 'Y-m-d H:i:s';
        $time = DateTimeImmutable::createFromFormat('!'.$format, $value, new DateTimeZone('UTC'));
        check($time !== false && $time->format($format) === $value, "Unsupported source timestamp [{$id}].", 3);

        return $time->format('Y-m-d\TH:i:s\Z');
    }

    /** @param SqlRow $row */
    private static function text(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        check(is_string($value), "Invalid source column [{$column}].", 3);

        return $value;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, string>  $target
     * @return MappedRow
     */
    private static function mapped(string $pk, array $source, array $target): array
    {
        return ['source_pk' => $pk, 'source_digest' => digest($source), 'target' => $target];
    }
}
