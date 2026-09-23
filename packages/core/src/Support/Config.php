<?php

declare(strict_types=1);

namespace CoreX\Support;

use CoreX\Contracts\AuditLogger;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Contracts\DepartmentRepository;
use CoreX\Contracts\FeatureFlags;
use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Contracts\SettingsRepository;
use CoreX\Enums\IdStrategy;
use CoreX\Enums\PubSubDriver;
use CoreX\Tenancy\Contracts\ImpersonationService;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use LogicException;

/**
 * Holder over the CoreOptions snapshot bound at boot.
 */
final class Config
{
    private static ?CoreOptions $bound = null;

    private static ?bool $octaneOptInOverride = null;

    public static function bind(CoreOptions $options): void
    {
        self::$bound = $options;
    }

    public static function unbind(): void
    {
        self::$bound = null;
    }

    public static function enableOctaneWorkerOptIn(): void
    {
        self::$octaneOptInOverride = true;
    }

    public static function options(): CoreOptions
    {
        $resolved = self::optionsFromApplication();

        if ($resolved instanceof CoreOptions) {
            self::$bound = $resolved;

            return $resolved;
        }

        if (self::$bound instanceof CoreOptions) {
            return self::$bound;
        }

        throw new LogicException('CoreOptions is not bound; call Config::bind at boot.');
    }

    /**
     * A booted application may replace `corex` after provider registration
     * (test setup, a migrate that follows a table-name override). The holder
     * then parses that repository value. `enableOctaneWorkerOptIn()` stays
     * independent of the snapshot.
     */
    private static function optionsFromApplication(): ?CoreOptions
    {
        if (! function_exists('app')) {
            return null;
        }

        $application = app();

        if (! $application->bound('config')) {
            return null;
        }

        $raw = $application->make('config')->get('corex', []);

        if (! is_array($raw)) {
            return null;
        }

        return CoreOptions::fromArray($raw);
    }

    public static function idStrategy(): IdStrategy
    {
        return self::options()->idStrategy;
    }

    public static function settingsTable(): string
    {
        return self::options()->settingsTable;
    }

    public static function auditLogsTable(): string
    {
        return self::options()->auditLogsTable;
    }

    public static function featureFlagsTable(): string
    {
        return self::options()->featureFlagsTable;
    }

    public static function featureOverridesTable(): string
    {
        return self::options()->featureOverridesTable;
    }

    public static function departmentsTable(): string
    {
        return self::options()->departmentsTable;
    }

    public static function departmentUserTable(): string
    {
        return self::options()->departmentUserTable;
    }

    // ─── Tenant context ───────────────────────────────────────────────────

    /** @return class-string<TenantContextResolver> */
    public static function tenantContextResolverClass(): string
    {
        /** @var class-string<TenantContextResolver> $class */
        $class = self::options()->tenantContextResolver;

        return $class;
    }

    /** @return class-string<TenancyManager> */
    public static function tenancyManagerClass(): string
    {
        /** @var class-string<TenancyManager> $class */
        $class = self::options()->tenancyManager;

        return $class;
    }

    /** @return class-string<ImpersonationService> */
    public static function impersonationServiceClass(): string
    {
        /** @var class-string<ImpersonationService> $class */
        $class = self::options()->impersonationService;

        return $class;
    }

    // ─── Bindings ─────────────────────────────────────────────────────────

    /** @return class-string<SettingsRepository> */
    public static function settingsRepositoryClass(): string
    {
        /** @var class-string<SettingsRepository> $class */
        $class = self::options()->settingsRepository;

        return $class;
    }

    /** @return class-string<AuditLogger> */
    public static function auditLoggerClass(): string
    {
        /** @var class-string<AuditLogger> $class */
        $class = self::options()->auditLogger;

        return $class;
    }

    /** @return class-string<SettingDefaultsProvider> */
    public static function settingDefaultsProviderClass(): string
    {
        /** @var class-string<SettingDefaultsProvider> $class */
        $class = self::options()->settingDefaultsProvider;

        return $class;
    }

    /** @return class-string<FeatureFlags> */
    public static function featureFlagsClass(): string
    {
        /** @var class-string<FeatureFlags> $class */
        $class = self::options()->featureFlags;

        return $class;
    }

    /** @return class-string<DepartmentRepository> */
    public static function departmentRepositoryClass(): string
    {
        /** @var class-string<DepartmentRepository> $class */
        $class = self::options()->departmentRepository;

        return $class;
    }

    /** @return class-string<DataScopeAuthorizer> */
    public static function dataScopeAuthorizerClass(): string
    {
        /** @var class-string<DataScopeAuthorizer> $class */
        $class = self::options()->dataScopeAuthorizer;

        return $class;
    }

    // ─── Audit ────────────────────────────────────────────────────────────

    public static function auditEnabled(): bool
    {
        return self::options()->auditEnabled;
    }

    // ─── Settings cache ───────────────────────────────────────────────────

    public static function settingsCacheStore(): string
    {
        return self::options()->settingsCacheStore;
    }

    public static function settingsCacheTtl(): ?int
    {
        return self::options()->settingsCacheTtl;
    }

    public static function settingsCachePrefix(): string
    {
        return self::options()->settingsCachePrefix;
    }

    // ─── PubSub ───────────────────────────────────────────────────────────

    public static function pubsubMessagesTable(): string
    {
        return self::options()->pubsubMessagesTable;
    }

    public static function pubsubCursorsTable(): string
    {
        return self::options()->pubsubCursorsTable;
    }

    public static function savedViewsTable(): string
    {
        return self::options()->savedViewsTable;
    }

    public static function savedViewScopesTable(): string
    {
        return self::options()->savedViewScopesTable;
    }

    public static function pubsubDriver(): PubSubDriver
    {
        return self::options()->pubsubDriver;
    }

    public static function pubsubPollIntervalMicroseconds(): int
    {
        return self::options()->pubsubPollIntervalMicroseconds;
    }

    /**
     * Opt-in flag a dedicated worker command sets before calling
     * `subscribe()` under an Octane runtime — `runningInConsole()`
     * cannot be trusted there (see `CoreX\Contracts\WorkerRuntime`), so the
     * guard requires this explicit signal instead. Never set by
     * request-handling code.
     *
     * The boot snapshot stays false. A worker command calls
     * {@see self::enableOctaneWorkerOptIn()} before `subscribe()`.
     *
     * @internal spec: D44
     */
    public static function pubsubOctaneWorkerOptIn(): bool
    {
        return self::$octaneOptInOverride ?? self::options()->pubsubOctaneWorkerOptIn;
    }

    /**
     * Default `--older-than` for `corex:pubsub:prune` — a
     * `DateInterval`-parseable relative-time string. MUST stay longer than
     * the worst-case subscriber downtime: pruning a message before every
     * durable cursor behind it has passed it is a silent, permanent
     * invalidation loss.
     *
     * @internal spec: D44
     */
    public static function pubsubRetention(): string
    {
        return self::options()->pubsubRetention;
    }

    // ─── Messaging ──────────────────────────────────────────────────────

    public static function outboxMessagesTable(): string
    {
        return self::options()->outboxMessagesTable;
    }

    public static function inboxMessagesTable(): string
    {
        return self::options()->inboxMessagesTable;
    }

    public static function messagingLeaseSeconds(): int
    {
        return self::options()->messagingLeaseSeconds;
    }

    public static function messagingBatchSize(): int
    {
        return self::options()->messagingBatchSize;
    }

    public static function messagingMaxAttempts(): int
    {
        return self::options()->messagingMaxAttempts;
    }

    public static function messagingBackoffCapSeconds(): int
    {
        return self::options()->messagingBackoffCapSeconds;
    }

    public static function messagingOutboxRetention(): string
    {
        return self::options()->messagingOutboxRetention;
    }

    public static function messagingDeadRetention(): string
    {
        return self::options()->messagingDeadRetention;
    }

    public static function messagingInboxRetention(): ?string
    {
        return self::options()->messagingInboxRetention;
    }

    public static function storageDurableRoot(): string
    {
        return self::options()->storageDurableRoot;
    }

    public static function storageEphemeralRoot(): string
    {
        return self::options()->storageEphemeralRoot;
    }
}
