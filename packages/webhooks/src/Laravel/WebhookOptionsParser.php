<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Laravel\ConfigurationValidator;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Webhooks\Data\WebhookOptions;
use CoreX\Webhooks\Data\WebhookTopic;
use InvalidArgumentException;

/** Immutable webhook activation policy; it never contains secret material. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WebhookOptionsParser
{
    /** @param array<array-key, mixed> $config */
    public static function fromArray(array $config): WebhookOptions
    {
        $violations = new ConfigurationValidator;
        $deliveryEnabled = self::bool(violations: $violations, path: 'delivery_enabled', value: $config['delivery_enabled'] ?? false);
        $workerReady = self::bool(violations: $violations, path: 'worker_ready', value: $config['worker_ready'] ?? false);
        $lease = self::positiveInt(violations: $violations, path: 'lease_seconds', value: $config['lease_seconds'] ?? 30);
        $bodyRetention = self::positiveInt(violations: $violations, path: 'body_retention_seconds', value: $config['body_retention_seconds'] ?? 604_800);
        $historyRetention = self::positiveInt(violations: $violations, path: 'history_retention_seconds', value: $config['history_retention_seconds'] ?? 2_592_000);
        $replayHorizon = self::positiveInt(violations: $violations, path: 'replay_horizon_seconds', value: $config['replay_horizon_seconds'] ?? 604_800);
        $maxAttempts = self::positiveInt(violations: $violations, path: 'max_attempts', value: $config['max_attempts'] ?? 12);
        $retryHorizon = self::positiveInt(violations: $violations, path: 'retry_horizon_seconds', value: $config['retry_horizon_seconds'] ?? 259_200);
        $deniedCidrs = self::stringList(violations: $violations, path: 'organization_denied_cidrs', value: $config['organization_denied_cidrs'] ?? []);
        $projectors = self::projectors(violations: $violations, value: $config['projectors'] ?? []);
        $topics = self::topics(violations: $violations, value: $config['topics'] ?? []);
        $topicIds = array_map(static fn (WebhookTopic $topic): string => $topic->id(), $topics);

        if ($deliveryEnabled && ! $workerReady) {
            $violations->add(path: 'worker_ready', message: 'must be true before remote delivery is enabled');
        }

        if ($lease <= 10) {
            $violations->add(path: 'lease_seconds', message: 'must exceed the ten-second HTTP timeout');
        }

        if ($bodyRetention > $historyRetention) {
            $violations->add(path: 'body_retention_seconds', message: 'must not exceed history_retention_seconds');
        }

        if ($replayHorizon !== $bodyRetention) {
            $violations->add(path: 'replay_horizon_seconds', message: 'must equal body_retention_seconds');
        }

        if ($maxAttempts > 20) {
            $violations->add(path: 'max_attempts', message: 'must be between 1 and 20');
        }

        if ($retryHorizon < 3600 || $retryHorizon > 604_800 || $retryHorizon > $replayHorizon) {
            $violations->add(path: 'retry_horizon_seconds', message: 'must be between 3600 and 604800 and not exceed replay_horizon_seconds');
        }

        foreach ($topics as $index => $topic) {
            if (! array_key_exists($topic->projectorService, $projectors)) {
                $violations->add(path: 'topics.'.$index.'.projector_service', message: 'must name an installed projector mapping');
            }
        }

        if (count(array_unique($topicIds)) !== count($topicIds)) {
            $violations->add(path: 'topics', message: 'must not declare the same topic version twice');
        }

        $violations->failIfAny();

        return new WebhookOptions(
            deliveryEnabled: $deliveryEnabled,
            workerReady: $workerReady,
            leaseSeconds: $lease,
            bodyRetentionSeconds: $bodyRetention,
            historyRetentionSeconds: $historyRetention,
            replayHorizonSeconds: $replayHorizon,
            organizationDeniedCidrs: $deniedCidrs,
            topics: $topics,
            projectors: $projectors,
            maxAttempts: $maxAttempts,
            retryHorizonSeconds: $retryHorizon,
        );
    }

    private static function bool(ConfigurationValidator $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a boolean');

        return false;
    }

    private static function positiveInt(ConfigurationValidator $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a positive integer');

        return 1;
    }

    /** @return list<string> */
    private static function stringList(ConfigurationValidator $violations, string $path, mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $violations->add(path: $path, message: 'must be a list of non-empty strings');

            return [];
        }

        $resolved = [];

        foreach ($value as $index => $entry) {
            if (! is_string($entry) || $entry === '') {
                $violations->add(path: $path.'.'.$index, message: 'must be a non-empty string');

                continue;
            }

            $resolved[] = $entry;
        }

        return $resolved;
    }

    /** @return array<string, string> */
    private static function projectors(ConfigurationValidator $violations, mixed $value): array
    {
        if (! is_array($value) || array_is_list($value) && $value !== []) {
            $violations->add(path: 'projectors', message: 'must be a service-id map');

            return [];
        }

        $resolved = [];

        foreach ($value as $id => $service) {
            if (! is_string($id)
                || preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)*\z/D', $id) !== 1
                || ! is_string($service)
                || $service === '') {
                $violations->add(path: 'projectors', message: 'must map safe ids to non-empty container services');

                continue;
            }

            $resolved[$id] = $service;
        }

        ksort($resolved);

        return $resolved;
    }

    /** @return list<WebhookTopic> */
    private static function topics(ConfigurationValidator $violations, mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $violations->add(path: 'topics', message: 'must be a list of webhook topic declarations');

            return [];
        }

        $topics = [];

        foreach ($value as $index => $row) {
            if ($row instanceof WebhookTopic) {
                $topics[] = $row;

                continue;
            }

            if (! is_array($row)) {
                $violations->add(path: 'topics.'.$index, message: 'must be a WebhookTopic or declaration map');

                continue;
            }

            $schema = is_array($row['schema'] ?? null) ? $row['schema'] : [];

            try {
                foreach ($schema as $key => $type) {
                    if (! is_string($key) || ! is_string($type)) {
                        throw new InvalidArgumentException('Topic schema must contain named scalar type declarations.');
                    }
                }
                /** @var array<string, string> $schema */
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
                $violations->add(path: 'topics.'.$index, message: $exception->getMessage());
            }
        }

        return $topics;
    }
}
