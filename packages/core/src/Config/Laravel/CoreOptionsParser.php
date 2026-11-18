<?php

declare(strict_types=1);

namespace CoreX\Config\Laravel;

use BackedEnum;
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
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Settings\Internal\DatabaseSettingsRepository;
use CoreX\Settings\Internal\NullSettingDefaultsProvider;
use DateInterval;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Laravel\ConfigurationValidator;
use Packman\Settings\Contracts\SettingDefaultsProviderInterface as SettingDefaultsProvider;
use Throwable;

/** Parses the host Laravel configuration and validates module bindings. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CoreOptionsParser
{
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

        $tenantContextResolver = self::classString(violations: $violations, path: 'tenant_context.resolver', value: $tenant['resolver'] ?? SingleAccountResolver::class, contract: TenantContextResolver::class);
        $tenancyManager = self::classString(violations: $violations, path: 'tenant_context.manager', value: $tenant['manager'] ?? NullTenancyManager::class, contract: TenancyManager::class);
        $impersonationService = self::classString(violations: $violations, path: 'tenant_context.impersonation', value: $tenant['impersonation'] ?? NullImpersonationService::class, contract: ImpersonationService::class);
        $settingsRepository = self::classString(violations: $violations, path: 'bindings.settings', value: $bindings['settings'] ?? DatabaseSettingsRepository::class, contract: SettingsRepository::class);
        $auditLogger = self::classString(violations: $violations, path: 'bindings.audit_logger', value: $bindings['audit_logger'] ?? QueuedAuditLogger::class, contract: AuditLogger::class);
        $auditRetention = self::classString(violations: $violations, path: 'bindings.audit_retention', value: $bindings['audit_retention'] ?? NullAuditRetention::class, contract: AuditRetention::class);
        $settingDefaultsProvider = self::classString(violations: $violations, path: 'bindings.setting_defaults', value: $bindings['setting_defaults'] ?? NullSettingDefaultsProvider::class, contract: SettingDefaultsProvider::class);
        $featureFlags = self::classString(violations: $violations, path: 'bindings.feature_flags', value: $bindings['feature_flags'] ?? DatabaseFeatureFlags::class, contract: FeatureFlags::class);
        $departmentRepository = self::classString(violations: $violations, path: 'bindings.departments', value: $bindings['departments'] ?? DatabaseDepartmentRepository::class, contract: DepartmentRepository::class);
        $dataScopeAuthorizer = self::classString(violations: $violations, path: 'bindings.data_scope_authorizer', value: $bindings['data_scope_authorizer'] ?? DenyDataScopeAuthorizer::class, contract: DataScopeAuthorizer::class);

        $durable = self::nonEmpty(violations: $violations, path: 'storage.durable_root', value: $storage['durable_root'] ?? 'storage/app/corex/durable');
        $ephemeral = self::nonEmpty(violations: $violations, path: 'storage.ephemeral_root', value: $storage['ephemeral_root'] ?? 'storage/app/corex/ephemeral');
        $connection = $views['connection'] ?? null;
        $viewsConnection = is_string($connection) && $connection !== '' ? $connection : null;
        $auditEnabled = self::bool(violations: $violations, path: 'audit.enabled', value: $audit['enabled'] ?? true);
        $cacheStore = self::nonEmpty(violations: $violations, path: 'settings.cache_store', value: $settings['cache_store'] ?? 'array');
        $cacheTtl = self::nullableNonNegativeInt(
            violations: $violations,
            path: 'settings.cache_ttl',
            value: array_key_exists('cache_ttl', $settings) ? $settings['cache_ttl'] : 3600,
        );
        $cachePrefix = self::nonEmpty(violations: $violations, path: 'settings.cache_prefix', value: $settings['cache_prefix'] ?? 'corex.settings');
        $pubsubDriver = self::enum(violations: $violations, path: 'pubsub.driver', value: $pubsub['driver'] ?? PubSubDriver::Null, enumClass: PubSubDriver::class);
        $poll = self::positiveInt(violations: $violations, path: 'pubsub.poll_interval_microseconds', value: $pubsub['poll_interval_microseconds'] ?? 50_000);
        $optIn = self::bool(violations: $violations, path: 'pubsub.octane_worker_opt_in', value: $pubsub['octane_worker_opt_in'] ?? false);
        $retention = self::interval(violations: $violations, path: 'pubsub.retention', value: $pubsub['retention'] ?? '30 days');
        $lease = self::positiveInt(violations: $violations, path: 'messaging.lease_seconds', value: $messaging['lease_seconds'] ?? 120);
        $batch = self::positiveInt(violations: $violations, path: 'messaging.batch_size', value: $messaging['batch_size'] ?? 100);
        $attempts = self::positiveInt(violations: $violations, path: 'messaging.max_attempts', value: $messaging['max_attempts'] ?? 8);
        $backoff = self::positiveInt(violations: $violations, path: 'messaging.backoff_cap_seconds', value: $messaging['backoff_cap_seconds'] ?? 300);
        $outboxRetention = self::interval(violations: $violations, path: 'messaging.outbox_retention', value: $messaging['outbox_retention'] ?? '14 days');
        $deadRetention = self::interval(violations: $violations, path: 'messaging.dead_retention', value: $messaging['dead_retention'] ?? '90 days');
        $inboxRetention = array_key_exists('inbox_retention', $messaging) && $messaging['inbox_retention'] !== null
            ? self::interval(violations: $violations, path: 'messaging.inbox_retention', value: $messaging['inbox_retention'])
            : null;
        $acknowledged = self::acknowledgedDangerousModes(violations: $violations, value: $config['acknowledged_dangerous_modes'] ?? []);

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

        $violations->add(path: $path, message: 'must be a '.$enumClass.' value');

        return $enumClass::cases()[0];
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return class-string<T>
     */
    private static function classString(ConfigurationValidator $violations, string $path, mixed $value, string $contract): string
    {
        if (is_string($value) && $value !== '' && class_exists($value) && is_a($value, $contract, true)) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a class implementing '.$contract);

        return $contract;
    }

    /** @return list<string> */
    private static function acknowledgedDangerousModes(ConfigurationValidator $violations, mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $violations->add(path: 'acknowledged_dangerous_modes', message: 'must be a list of exact config paths');

            return [];
        }

        $paths = [];
        foreach ($value as $index => $path) {
            if (! is_string($path) || $path === '' || str_contains($path, '*')) {
                $violations->add(path: 'acknowledged_dangerous_modes.'.$index, message: 'must be an exact config path without wildcards');

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

    private static function nullableNonNegativeInt(ConfigurationValidator $violations, string $path, mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) && $value >= 0) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be null or a non-negative integer');

        return null;
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a non-empty string');

        return 'invalid';
    }

    private static function interval(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '' && self::parsesAsInterval($value)) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a DateInterval string');

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
