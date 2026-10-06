<?php

declare(strict_types=1);

namespace CoreX\Config\Laravel;

use BackedEnum;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Contracts\AuditRetention;
use CoreX\Audit\Internal\NullAuditRetention;
use CoreX\Audit\Internal\QueuedAuditLogger;
use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Internal\NullImpersonationService;
use CoreX\Context\Internal\NullTenancyManager;
use CoreX\Context\Internal\SingleAccountResolver;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Internal\DenyDataScopeAuthorizer;
use CoreX\Departments\Contracts\DepartmentRepository;
use CoreX\Departments\Internal\DatabaseDepartmentRepository;
use CoreX\Features\Contracts\FeatureFlags;
use CoreX\Features\Internal\DatabaseFeatureFlags;
use CoreX\Messaging\Enums\PubSubDriver;
use CoreX\Settings\Contracts\SettingDefaultsProvider;
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Settings\Internal\DatabaseSettingsRepository;
use CoreX\Settings\Internal\NullSettingDefaultsProvider;
use DateInterval;
use Throwable;

/** Parses the host Laravel configuration and validates module bindings. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CoreOptionsParser
{
    /** @deprecated Resolve `CoreOptions` from the container; it is a shared lazy singleton. */
    public static function current(): CoreOptions
    {
        return app(CoreOptions::class);
    }

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): CoreOptions
    {
        $violations = new ConfigurationValidator;
        $tenant = is_array($config['tenant_context'] ?? null) ? $config['tenant_context'] : [];
        $bindings = is_array($config['bindings'] ?? null) ? $config['bindings'] : [];
        $storage = is_array($config['storage'] ?? null) ? $config['storage'] : [];
        $views = is_array($config['views'] ?? null) ? $config['views'] : [];
        $audit = is_array($config['audit'] ?? null) ? $config['audit'] : [];
        $settings = is_array($config['settings'] ?? null) ? $config['settings'] : [];
        $pubsub = is_array($config['pubsub'] ?? null) ? $config['pubsub'] : [];
        $messaging = is_array($config['messaging'] ?? null) ? $config['messaging'] : [];

        $tenantContextResolver = self::classString($violations, 'tenant_context.resolver', $tenant['resolver'] ?? SingleAccountResolver::class, TenantContextResolver::class);
        $tenancyManager = self::classString($violations, 'tenant_context.manager', $tenant['manager'] ?? NullTenancyManager::class, TenancyManager::class);
        $impersonationService = self::classString($violations, 'tenant_context.impersonation', $tenant['impersonation'] ?? NullImpersonationService::class, ImpersonationService::class);
        $settingsRepository = self::classString($violations, 'bindings.settings', $bindings['settings'] ?? DatabaseSettingsRepository::class, SettingsRepository::class);
        $auditLogger = self::classString($violations, 'bindings.audit_logger', $bindings['audit_logger'] ?? QueuedAuditLogger::class, AuditLogger::class);
        $auditRetention = self::classString($violations, 'bindings.audit_retention', $bindings['audit_retention'] ?? NullAuditRetention::class, AuditRetention::class);
        $settingDefaultsProvider = self::classString($violations, 'bindings.setting_defaults', $bindings['setting_defaults'] ?? NullSettingDefaultsProvider::class, SettingDefaultsProvider::class);
        $featureFlags = self::classString($violations, 'bindings.feature_flags', $bindings['feature_flags'] ?? DatabaseFeatureFlags::class, FeatureFlags::class);
        $departmentRepository = self::classString($violations, 'bindings.departments', $bindings['departments'] ?? DatabaseDepartmentRepository::class, DepartmentRepository::class);
        $dataScopeAuthorizer = self::classString($violations, 'bindings.data_scope_authorizer', $bindings['data_scope_authorizer'] ?? DenyDataScopeAuthorizer::class, DataScopeAuthorizer::class);

        $durable = self::nonEmpty($violations, 'storage.durable_root', $storage['durable_root'] ?? 'storage/app/corex/durable');
        $ephemeral = self::nonEmpty($violations, 'storage.ephemeral_root', $storage['ephemeral_root'] ?? 'storage/app/corex/ephemeral');
        $connection = $views['connection'] ?? null;
        $viewsConnection = is_string($connection) && $connection !== '' ? $connection : null;
        $auditEnabled = self::bool($violations, 'audit.enabled', $audit['enabled'] ?? true);
        $cacheStore = self::nonEmpty($violations, 'settings.cache_store', $settings['cache_store'] ?? 'array');
        $cacheTtl = self::nullableNonNegativeInt(
            $violations,
            'settings.cache_ttl',
            array_key_exists('cache_ttl', $settings) ? $settings['cache_ttl'] : 3600,
        );
        $cachePrefix = self::nonEmpty($violations, 'settings.cache_prefix', $settings['cache_prefix'] ?? 'corex.settings');
        $pubsubDriver = self::enum($violations, 'pubsub.driver', $pubsub['driver'] ?? PubSubDriver::Null, PubSubDriver::class);
        $poll = self::positiveInt($violations, 'pubsub.poll_interval_microseconds', $pubsub['poll_interval_microseconds'] ?? 50_000);
        $optIn = self::bool($violations, 'pubsub.octane_worker_opt_in', $pubsub['octane_worker_opt_in'] ?? false);
        $retention = self::interval($violations, 'pubsub.retention', $pubsub['retention'] ?? '30 days');
        $lease = self::positiveInt($violations, 'messaging.lease_seconds', $messaging['lease_seconds'] ?? 120);
        $batch = self::positiveInt($violations, 'messaging.batch_size', $messaging['batch_size'] ?? 100);
        $attempts = self::positiveInt($violations, 'messaging.max_attempts', $messaging['max_attempts'] ?? 8);
        $backoff = self::positiveInt($violations, 'messaging.backoff_cap_seconds', $messaging['backoff_cap_seconds'] ?? 300);
        $outboxRetention = self::interval($violations, 'messaging.outbox_retention', $messaging['outbox_retention'] ?? '14 days');
        $deadRetention = self::interval($violations, 'messaging.dead_retention', $messaging['dead_retention'] ?? '90 days');
        $inboxRetention = array_key_exists('inbox_retention', $messaging) && $messaging['inbox_retention'] !== null
            ? self::interval($violations, 'messaging.inbox_retention', $messaging['inbox_retention'])
            : null;
        $acknowledged = self::acknowledgedDangerousModes($violations, $config['acknowledged_dangerous_modes'] ?? []);

        $violations->failIfAny();

        return new CoreOptions(
            tenantContextResolver: $tenantContextResolver,
            tenancyManager: $tenancyManager,
            impersonationService: $impersonationService,
            settingsRepository: $settingsRepository,
            auditLogger: $auditLogger,
            auditRetention: $auditRetention,
            settingDefaultsProvider: $settingDefaultsProvider,
            featureFlags: $featureFlags,
            departmentRepository: $departmentRepository,
            dataScopeAuthorizer: $dataScopeAuthorizer,
            storageDurableRoot: $durable,
            storageEphemeralRoot: $ephemeral,
            viewsConnection: $viewsConnection,
            auditEnabled: $auditEnabled,
            settingsCacheStore: $cacheStore,
            settingsCacheTtl: $cacheTtl,
            settingsCachePrefix: $cachePrefix,
            pubsubDriver: $pubsubDriver,
            pubsubPollIntervalMicroseconds: $poll,
            pubsubOctaneWorkerOptIn: $optIn,
            pubsubRetention: $retention,
            messagingLeaseSeconds: $lease,
            messagingBatchSize: $batch,
            messagingMaxAttempts: $attempts,
            messagingBackoffCapSeconds: $backoff,
            messagingOutboxRetention: $outboxRetention,
            messagingDeadRetention: $deadRetention,
            messagingInboxRetention: $inboxRetention,
            acknowledgedDangerousModes: $acknowledged,
        );
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @return T
     */
    private static function enum(ConfigurationValidator $violations, string $path, mixed $value, string $enumClass): BackedEnum
    {
        if ($value instanceof $enumClass) {
            return $value;
        }

        if ((is_string($value) || is_int($value)) && is_subclass_of($enumClass, BackedEnum::class)) {
            $resolved = $enumClass::tryFrom($value);

            if ($resolved instanceof $enumClass) {
                return $resolved;
            }
        }

        $violations->add($path, 'must be a '.$enumClass.' value');

        return $enumClass::cases()[0];
    }

    /**
     * @param  class-string  $contract
     * @return class-string
     */
    private static function classString(ConfigurationValidator $violations, string $path, mixed $value, string $contract): string
    {
        if (is_string($value) && $value !== '' && class_exists($value) && is_a($value, $contract, true)) {
            return $value;
        }

        $violations->add($path, 'must be a class implementing '.$contract);

        return $contract;
    }

    /** @return list<string> */
    private static function acknowledgedDangerousModes(ConfigurationValidator $violations, mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $violations->add('acknowledged_dangerous_modes', 'must be a list of exact config paths');

            return [];
        }

        $paths = [];
        foreach ($value as $index => $path) {
            if (! is_string($path) || $path === '' || str_contains($path, '*')) {
                $violations->add('acknowledged_dangerous_modes.'.$index, 'must be an exact config path without wildcards');

                continue;
            }

            $paths[] = $path;
        }

        return $paths;
    }

    private static function bool(ConfigurationValidator $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add($path, 'must be a boolean');

        return false;
    }

    private static function positiveInt(ConfigurationValidator $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add($path, 'must be a positive integer');

        return 1;
    }

    private static function nullableNonNegativeInt(ConfigurationValidator $violations, string $path, mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) && $value >= 0) {
            return $value;
        }

        $violations->add($path, 'must be null or a non-negative integer');

        return null;
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add($path, 'must be a non-empty string');

        return 'invalid';
    }

    private static function interval(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '' && self::parsesAsInterval($value)) {
            return $value;
        }

        $violations->add($path, 'must be a DateInterval string');

        return '30 days';
    }

    private static function parsesAsInterval(string $value): bool
    {
        try {
            return self::intervalHasDuration(new DateInterval($value));
        } catch (Throwable) {
            // Relative strings such as "30 days" are not ISO-8601 intervals.
        }

        try {
            $relative = DateInterval::createFromDateString($value);
        } catch (Throwable) {
            return false;
        }

        return self::intervalHasDuration($relative);
    }

    private static function intervalHasDuration(DateInterval $interval): bool
    {
        return $interval->y !== 0
            || $interval->m !== 0
            || $interval->d !== 0
            || $interval->h !== 0
            || $interval->i !== 0
            || $interval->s !== 0
            || $interval->f !== 0.0;
    }
}
