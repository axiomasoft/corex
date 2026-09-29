<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Data;

use CoreX\Config\ViolationList;
use CoreX\Operations\Validation\JsonCanonical;
use InvalidArgumentException;

/** Immutable webhook activation policy; it never contains secret material. */
final readonly class WebhookOptions
{
    /**
     * @param  list<string>  $organizationDeniedCidrs
     * @param  list<WebhookTopic>  $topics
     * @param  array<string, string>  $projectors
     */
    public function __construct(
        public bool $deliveryEnabled,
        public bool $workerReady,
        public int $leaseSeconds,
        public int $bodyRetentionSeconds,
        public int $historyRetentionSeconds,
        public int $replayHorizonSeconds,
        public array $organizationDeniedCidrs,
        public array $topics,
        public array $projectors,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $violations = new ViolationList;
        $deliveryEnabled = self::bool($violations, 'delivery_enabled', $config['delivery_enabled'] ?? false);
        $workerReady = self::bool($violations, 'worker_ready', $config['worker_ready'] ?? false);
        $lease = self::positiveInt($violations, 'lease_seconds', $config['lease_seconds'] ?? 30);
        $bodyRetention = self::positiveInt($violations, 'body_retention_seconds', $config['body_retention_seconds'] ?? 604_800);
        $historyRetention = self::positiveInt($violations, 'history_retention_seconds', $config['history_retention_seconds'] ?? 2_592_000);
        $replayHorizon = self::positiveInt($violations, 'replay_horizon_seconds', $config['replay_horizon_seconds'] ?? 604_800);
        $deniedCidrs = self::stringList($violations, 'organization_denied_cidrs', $config['organization_denied_cidrs'] ?? []);
        $projectors = self::projectors($violations, $config['projectors'] ?? []);
        $topics = self::topics($violations, $config['topics'] ?? []);
        $topicIds = array_map(static fn (WebhookTopic $topic): string => $topic->id(), $topics);

        if ($deliveryEnabled && ! $workerReady) {
            $violations->add('worker_ready', 'must be true before remote delivery is enabled');
        }

        if ($lease <= 10) {
            $violations->add('lease_seconds', 'must exceed the ten-second HTTP timeout');
        }

        if ($bodyRetention > $historyRetention) {
            $violations->add('body_retention_seconds', 'must not exceed history_retention_seconds');
        }

        if ($replayHorizon !== $bodyRetention) {
            $violations->add('replay_horizon_seconds', 'must equal body_retention_seconds');
        }

        foreach ($topics as $index => $topic) {
            if (! array_key_exists($topic->projectorService, $projectors)) {
                $violations->add('topics.'.$index.'.projector_service', 'must name an installed projector mapping');
            }
        }

        if (count(array_unique($topicIds)) !== count($topicIds)) {
            $violations->add('topics', 'must not declare the same topic version twice');
        }

        $violations->failIfAny();

        return new self(
            deliveryEnabled: $deliveryEnabled,
            workerReady: $workerReady,
            leaseSeconds: $lease,
            bodyRetentionSeconds: $bodyRetention,
            historyRetentionSeconds: $historyRetention,
            replayHorizonSeconds: $replayHorizon,
            organizationDeniedCidrs: $deniedCidrs,
            topics: $topics,
            projectors: $projectors,
        );
    }

    private static function bool(ViolationList $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add($path, 'must be a boolean');

        return false;
    }

    private static function positiveInt(ViolationList $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add($path, 'must be a positive integer');

        return 1;
    }

    /** @return list<string> */
    private static function stringList(ViolationList $violations, string $path, mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $violations->add($path, 'must be a list of non-empty strings');

            return [];
        }

        $resolved = [];

        foreach ($value as $index => $entry) {
            if (! is_string($entry) || $entry === '') {
                $violations->add($path.'.'.$index, 'must be a non-empty string');

                continue;
            }

            $resolved[] = $entry;
        }

        return $resolved;
    }

    /** @return array<string, string> */
    private static function projectors(ViolationList $violations, mixed $value): array
    {
        if (! is_array($value) || array_is_list($value) && $value !== []) {
            $violations->add('projectors', 'must be a service-id map');

            return [];
        }

        $resolved = [];

        foreach ($value as $id => $service) {
            if (! is_string($id)
                || preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)*\z/D', $id) !== 1
                || ! is_string($service)
                || $service === '') {
                $violations->add('projectors', 'must map safe ids to non-empty container services');

                continue;
            }

            $resolved[$id] = $service;
        }

        ksort($resolved);

        return $resolved;
    }

    /** @return list<WebhookTopic> */
    private static function topics(ViolationList $violations, mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $violations->add('topics', 'must be a list of webhook topic declarations');

            return [];
        }

        $topics = [];

        foreach ($value as $index => $row) {
            if ($row instanceof WebhookTopic) {
                $topics[] = $row;

                continue;
            }

            if (! is_array($row)) {
                $violations->add('topics.'.$index, 'must be a WebhookTopic or declaration map');

                continue;
            }

            $schema = is_array($row['schema'] ?? null) ? $row['schema'] : [];

            try {
                $topics[] = new WebhookTopic(
                    name: is_string($row['name'] ?? null) ? $row['name'] : '',
                    version: is_int($row['version'] ?? null) ? $row['version'] : 0,
                    major: is_string($row['major'] ?? null) ? $row['major'] : '',
                    schema: $schema,
                    schemaHash: is_string($row['schema_hash'] ?? null)
                        ? $row['schema_hash']
                        : hash('sha256', JsonCanonical::encode($schema)),
                    piiClass: is_string($row['pii_class'] ?? null) ? $row['pii_class'] : '',
                    projectorService: is_string($row['projector_service'] ?? null) ? $row['projector_service'] : '',
                );
            } catch (InvalidArgumentException $exception) {
                $violations->add('topics.'.$index, $exception->getMessage());
            }
        }

        return $topics;
    }
}
