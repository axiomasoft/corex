<?php

declare(strict_types=1);

namespace CoreX\OfflineMigration;

use SQLite3;

/**
 * @phpstan-import-type MappedRow from SourceSnapshot
 *
 * @phpstan-type TargetIdentity array{database_id: string, deployment_id: string, artifact_sha: string, binding_generation: positive-int, recovery_generation: positive-int}
 */
final readonly class TargetDatabase
{
    public const string Schema = '175de606933f48084699531952d2ef4e013ce1648c67d41eb78b1ceb31dd34a4';

    public const string Codec = 'corex-v2-stage0-webhook-receipts';

    public const array Tables = ['subscriptions' => 'webhook_subscriptions', 'deliveries' => 'webhook_deliveries'];

    public const array IdentityKeys = ['subscriptions' => 'id', 'deliveries' => 'effect_key'];

    public string $path;

    /** @var array{device: string, inode: string} */
    public array $fileIdentity;

    /** @var TargetIdentity */
    public array $identity;

    /** @param array<string, mixed> $descriptor */
    public function __construct(array $descriptor)
    {
        exactFields($descriptor, [
            'database_id', 'deployment_id', 'artifact_sha', 'binding_generation', 'recovery_generation',
            'database', 'schema_sha256', 'codec',
        ], 'target descriptor');
        check(shaField($descriptor, 'schema_sha256') === self::Schema && field($descriptor, 'codec') === self::Codec,
            'Unsupported target schema or codec.');
        $binding = $descriptor['binding_generation'] ?? null;
        $recovery = $descriptor['recovery_generation'] ?? null;
        check(is_int($binding) && $binding > 0, 'Invalid target binding_generation.');
        check(is_int($recovery) && $recovery > 0, 'Invalid target recovery_generation.');
        $this->path = fullPath(field($descriptor, 'database'));
        $this->fileIdentity = fileIdentity($this->path);
        $this->identity = [
            'database_id' => field($descriptor, 'database_id'),
            'deployment_id' => field($descriptor, 'deployment_id'),
            'artifact_sha' => shaField($descriptor, 'artifact_sha', 40),
            'binding_generation' => $binding,
            'recovery_generation' => $recovery,
        ];
    }

    public function connect(bool $write): SQLite3
    {
        $this->assertFile();
        $db = database($this->path, $write);

        if ($write) {
            $db->exec('PRAGMA synchronous = FULL');
        }
        $this->assertFile();

        return $db;
    }

    public function assertIdentity(SQLite3 $db): void
    {
        $this->assertFile();
        check($db->querySingle('PRAGMA journal_mode') === 'delete', 'Target must use rollback-journal mode.');
        check(schemaFingerprint($db) === self::Schema, 'Target schema fingerprint changed.');
        $actual = iterator_to_array(rows($db, 'SELECT * FROM migration_identity'));
        check(count($actual) === 1 && canonical($actual[0]) === canonical($this->identity), 'Target database identity or generation changed.');
    }

    public function assertFile(): void
    {
        check(fileIdentity($this->path) === $this->fileIdentity, 'Target physical identity changed.');
    }

    public function assertEmpty(SQLite3 $db): void
    {
        foreach ([...array_values(self::Tables), 'migration_checkpoint', 'migration_row_map'] as $table) {
            check($db->querySingle("SELECT EXISTS(SELECT 1 FROM {$table})") === 0, 'Target is not empty for this plan.');
        }
    }

    /**
     * Verify the contiguous committed prefix against the real owner rows and the ledger on
     * the same SQL snapshot. Returning its size makes a lost commit response harmless.
     *
     * @param  key-of<self::Tables>  $segment
     * @param  list<MappedRow>  $expected
     */
    public function progress(SQLite3 $db, string $planId, string $segment, array $expected, bool $requireComplete = false): int
    {
        $table = self::Tables[$segment];
        $identity = self::IdentityKeys[$segment];
        $checkpoints = iterator_to_array(rows($db, 'SELECT * FROM migration_checkpoint WHERE segment = ?', [$segment]));
        $checkpoint = $checkpoints[0] ?? null;
        $done = $checkpoint['rows'] ?? 0;
        check(count($checkpoints) <= 1 && is_int($done) && $done >= 0 && $done <= count($expected), "Invalid checkpoint for {$segment}.", 3);
        check(! $requireComplete || $done === count($expected), "Incomplete checkpoint for {$segment}.", 3);
        $prefix = array_slice($expected, 0, $done);

        if ($checkpoint !== null) {
            check($done > 0, "Invalid checkpoint rows for {$segment}.", 3);
            check(canonical($checkpoint) === canonical(self::checkpoint($planId, $segment, $expected, $done)),
                "Checkpoint identity or digest mismatch for {$segment}.", 3);
        }

        $ledger = iterator_to_array(rows($db, 'SELECT * FROM migration_row_map WHERE segment = ? ORDER BY source_pk COLLATE BINARY', [$segment]));
        $expectedMap = array_map(fn (array $row): array => self::mapping($planId, $segment, $row), $prefix);
        check(digestRows($ledger) === digestRows($expectedMap), "Row map mismatch for {$segment}.", 3);

        $actual = rows($db, "SELECT * FROM {$table} ORDER BY {$identity} COLLATE BINARY");
        check(digestRows($actual) === digestRows(array_column($prefix, 'target')), "Target semantic digest mismatch for {$segment}.", 3);

        return $done;
    }

    /**
     * @param  key-of<self::Tables>  $segment
     * @param  list<MappedRow>  $expected
     */
    public function insertNext(SQLite3 $db, string $planId, string $segment, array $expected, int $offset): void
    {
        $row = $expected[$offset] ?? null;
        check($offset >= 0 && $row !== null, 'Copy offset out of bounds.', 3);

        if ($segment === 'subscriptions') {
            execute($db, 'INSERT INTO webhook_subscriptions (id, status, subscription_json) VALUES (?, ?, ?)', array_values($row['target']));
        } else {
            execute($db, 'INSERT INTO webhook_deliveries (effect_key, subscription_id, disposition, delivery_json) VALUES (?, ?, ?, ?)', array_values($row['target']));
        }
        execute($db, 'INSERT INTO migration_row_map (plan_id, segment, source_pk, source_digest, target_ref, semantic_digest)
            VALUES (?, ?, ?, ?, ?, ?)', array_values(self::mapping($planId, $segment, $row)));
        execute($db, 'INSERT INTO migration_checkpoint (plan_id, segment, high_pk, rows, digest, phase)
            VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(plan_id, segment) DO UPDATE SET
            high_pk = excluded.high_pk, rows = excluded.rows, digest = excluded.digest, phase = excluded.phase',
            array_values(self::checkpoint($planId, $segment, $expected, $offset + 1)));
    }

    /**
     * @param  key-of<self::Tables>  $segment
     * @param  MappedRow  $row
     * @return array{plan_id: string, segment: string, source_pk: string, source_digest: string, target_ref: string, semantic_digest: string}
     */
    private static function mapping(string $planId, string $segment, array $row): array
    {
        return [
            'plan_id' => $planId,
            'segment' => $segment,
            'source_pk' => $row['source_pk'],
            'source_digest' => $row['source_digest'],
            'target_ref' => $row['target'][self::IdentityKeys[$segment]],
            'semantic_digest' => digest($row['target']),
        ];
    }

    /**
     * @param  list<MappedRow>  $expected
     * @param  positive-int  $done
     * @return array{plan_id: string, segment: string, high_pk: string, rows: positive-int, digest: string, phase: 'copying'|'copied'}
     */
    private static function checkpoint(string $planId, string $segment, array $expected, int $done): array
    {
        return [
            'plan_id' => $planId,
            'segment' => $segment,
            'high_pk' => $expected[$done - 1]['source_pk'],
            'rows' => $done,
            'digest' => digestRows(array_slice($expected, 0, $done)),
            'phase' => $done === count($expected) ? 'copied' : 'copying',
        ];
    }
}
