<?php

declare(strict_types=1);

namespace CoreX\Support;

use BackedEnum;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Contracts\AuditRetention;
use CoreX\Audit\NullAuditRetention;
use CoreX\Audit\QueuedAuditLogger;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Contracts\DepartmentRepository;
use CoreX\Contracts\FeatureFlags;
use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Contracts\SettingsRepository;
use CoreX\Departments\DatabaseDepartmentRepository;
use CoreX\Enums\IdStrategy;
use CoreX\Enums\PubSubDriver;
use CoreX\Features\DatabaseFeatureFlags;
use CoreX\Settings\DatabaseSettingsRepository;
use CoreX\Settings\NullSettingDefaultsProvider;
use CoreX\Support\Data\DenyDataScopeAuthorizer;
use CoreX\Tenancy\Contracts\ImpersonationService;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\NullImpersonationService;
use CoreX\Tenancy\NullTenancyManager;
use CoreX\Tenancy\SingleAccountResolver;
use DateInterval;
use Throwable;

/** @internal Package composition type. */
final readonly class CoreOptions
{
    /**
     * @param  class-string<TenantContextResolver>  $tenantContextResolver
     * @param  class-string<TenancyManager>  $tenancyManager
     * @param  class-string<ImpersonationService>  $impersonationService
     * @param  class-string<SettingsRepository>  $settingsRepository
     * @param  class-string<AuditLogger>  $auditLogger
     * @param  class-string<AuditRetention>  $auditRetention
     * @param  class-string<SettingDefaultsProvider>  $settingDefaultsProvider
     * @param  class-string<FeatureFlags>  $featureFlags
     * @param  class-string<DepartmentRepository>  $departmentRepository
     * @param  class-string<DataScopeAuthorizer>  $dataScopeAuthorizer
     */
    public function __construct(
        public IdStrategy $idStrategy,
        public string $settingsTable,
        public string $auditLogsTable,
        public string $featureFlagsTable,
        public string $featureOverridesTable,
        public string $departmentsTable,
        public string $departmentUserTable,
        public string $pubsubMessagesTable,
        public string $pubsubCursorsTable,
        public string $savedViewsTable,
        public string $savedViewScopesTable,
        public string $outboxMessagesTable,
        public string $inboxMessagesTable,
        public string $tenantContextResolver,
        public string $tenancyManager,
        public string $impersonationService,
        public string $settingsRepository,
        public string $auditLogger,
        public string $auditRetention,
        public string $settingDefaultsProvider,
        public string $featureFlags,
        public string $departmentRepository,
        public string $dataScopeAuthorizer,
        public string $storageDurableRoot,
        public string $storageEphemeralRoot,
        public ?string $viewsConnection,
        public bool $auditEnabled,
        public string $settingsCacheStore,
        public ?int $settingsCacheTtl,
        public string $settingsCachePrefix,
        public PubSubDriver $pubsubDriver,
        public int $pubsubPollIntervalMicroseconds,
        public bool $pubsubOctaneWorkerOptIn,
        public string $pubsubRetention,
        public int $messagingLeaseSeconds,
        public int $messagingBatchSize,
        public int $messagingMaxAttempts,
        public int $messagingBackoffCapSeconds,
        public string $messagingOutboxRetention,
        public string $messagingDeadRetention,
        public ?string $messagingInboxRetention,
    ) {}

    /** @param  array<string, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ViolationList;
        $tables = is_array($config['tables'] ?? null) ? $config['tables'] : [];
        $tenant = is_array($config['tenant_context'] ?? null) ? $config['tenant_context'] : [];
        $bindings = is_array($config['bindings'] ?? null) ? $config['bindings'] : [];
        $storage = is_array($config['storage'] ?? null) ? $config['storage'] : [];
        $views = is_array($config['views'] ?? null) ? $config['views'] : [];
        $audit = is_array($config['audit'] ?? null) ? $config['audit'] : [];
        $settings = is_array($config['settings'] ?? null) ? $config['settings'] : [];
        $pubsub = is_array($config['pubsub'] ?? null) ? $config['pubsub'] : [];
        $messaging = is_array($config['messaging'] ?? null) ? $config['messaging'] : [];
        $ids = is_array($config['ids'] ?? null) ? $config['ids'] : [];

        $idStrategy = self::enum($violations, 'ids.strategy', $ids['strategy'] ?? IdStrategy::Uuid7, IdStrategy::class);
        $settingsTable = self::table($violations, 'tables.settings', $tables['settings'] ?? 'sys_settings');
        $auditLogsTable = self::table($violations, 'tables.audit_logs', $tables['audit_logs'] ?? 'sys_audit_log');
        $featureFlagsTable = self::table($violations, 'tables.feature_flags', $tables['feature_flags'] ?? 'sys_feature_flags');
        $featureOverridesTable = self::table($violations, 'tables.feature_overrides', $tables['feature_overrides'] ?? 'sys_feature_overrides');
        $departmentsTable = self::table($violations, 'tables.departments', $tables['departments'] ?? 'sys_departments');
        $departmentUserTable = self::table($violations, 'tables.department_user', $tables['department_user'] ?? 'sys_department_user');
        $pubsubMessagesTable = self::table($violations, 'tables.pubsub_messages', $tables['pubsub_messages'] ?? 'sys_pubsub_messages');
        $pubsubCursorsTable = self::table($violations, 'tables.pubsub_cursors', $tables['pubsub_cursors'] ?? 'sys_pubsub_cursors');
        $savedViewsTable = self::table($violations, 'tables.saved_views', $tables['saved_views'] ?? 'sys_saved_views');
        $savedViewScopesTable = self::table($violations, 'tables.saved_view_scopes', $tables['saved_view_scopes'] ?? 'sys_saved_view_scopes');
        $outboxMessagesTable = self::table($violations, 'tables.outbox_messages', $tables['outbox_messages'] ?? 'sys_outbox_messages');
        $inboxMessagesTable = self::table($violations, 'tables.inbox_messages', $tables['inbox_messages'] ?? 'sys_inbox_messages');

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

        $violations->failIfAny();

        return new self(
            idStrategy: $idStrategy,
            settingsTable: $settingsTable,
            auditLogsTable: $auditLogsTable,
            featureFlagsTable: $featureFlagsTable,
            featureOverridesTable: $featureOverridesTable,
            departmentsTable: $departmentsTable,
            departmentUserTable: $departmentUserTable,
            pubsubMessagesTable: $pubsubMessagesTable,
            pubsubCursorsTable: $pubsubCursorsTable,
            savedViewsTable: $savedViewsTable,
            savedViewScopesTable: $savedViewScopesTable,
            outboxMessagesTable: $outboxMessagesTable,
            inboxMessagesTable: $inboxMessagesTable,
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
        );
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @return T
     */
    private static function enum(ViolationList $violations, string $path, mixed $value, string $enumClass): BackedEnum
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

    private static function table(ViolationList $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add($path, 'must be a non-empty string');

        return 'invalid';
    }

    /**
     * @param  class-string  $contract
     * @return class-string
     */
    private static function classString(ViolationList $violations, string $path, mixed $value, string $contract): string
    {
        if (is_string($value) && $value !== '' && class_exists($value) && is_a($value, $contract, true)) {
            return $value;
        }

        $violations->add($path, 'must be a class implementing '.$contract);

        return $contract;
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

    private static function nullableNonNegativeInt(ViolationList $violations, string $path, mixed $value): ?int
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

    private static function nonEmpty(ViolationList $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add($path, 'must be a non-empty string');

        return 'invalid';
    }

    private static function interval(ViolationList $violations, string $path, mixed $value): string
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
