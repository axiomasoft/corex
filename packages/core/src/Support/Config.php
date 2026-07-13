<?php

declare(strict_types=1);

namespace CoreX\Support;

use CoreX\Audit\QueuedAuditLogger;
use CoreX\Contracts\AuditLogger;
use CoreX\Contracts\DepartmentRepository;
use CoreX\Contracts\FeatureFlags;
use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Contracts\SettingsRepository;
use CoreX\Contracts\TenantResolver;
use CoreX\Contracts\TenantService;
use CoreX\Departments\DatabaseDepartmentRepository;
use CoreX\Enums\IdStrategy;
use CoreX\Features\DatabaseFeatureFlags;
use CoreX\Settings\DatabaseSettingsRepository;
use CoreX\Settings\NullSettingDefaultsProvider;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\NullTenantService;
use CoreX\Tenancy\SingleAccountResolver;

/**
 * Typed accessor over config('corex.*') — keeps string keys in one place.
 */
final class Config
{
    public static function idStrategy(): IdStrategy
    {
        $value = config('corex.ids.strategy', IdStrategy::Ulid);

        return $value instanceof IdStrategy ? $value : IdStrategy::from((string) $value);
    }

    public static function settingsTable(): string
    {
        return (string) config('corex.tables.settings', 'sys_settings');
    }

    public static function auditLogsTable(): string
    {
        return (string) config('corex.tables.audit_logs', 'sys_audit_log');
    }

    public static function featureFlagsTable(): string
    {
        return (string) config('corex.tables.feature_flags', 'sys_feature_flags');
    }

    public static function featureOverridesTable(): string
    {
        return (string) config('corex.tables.feature_overrides', 'sys_feature_overrides');
    }

    public static function departmentsTable(): string
    {
        return (string) config('corex.tables.departments', 'sys_departments');
    }

    public static function departmentUserTable(): string
    {
        return (string) config('corex.tables.department_user', 'sys_department_user');
    }

    // ─── Tenancy ──────────────────────────────────────────────────────────

    public static function tenantColumn(): string
    {
        return (string) config('corex.tenancy.column', 'tenant_id');
    }

    /** @return class-string<TenantService> */
    public static function tenantServiceClass(): string
    {
        /** @var class-string<TenantService> $class */
        $class = config('corex.tenancy.service', NullTenantService::class);

        return $class;
    }

    /** @return class-string<TenantResolver>|null */
    public static function tenantResolverClass(): ?string
    {
        /** @var class-string<TenantResolver>|null $class */
        $class = config('corex.tenancy.resolver');

        return $class;
    }

    /** @return class-string<TenantContextResolver> */
    public static function tenantContextResolverClass(): string
    {
        /** @var class-string<TenantContextResolver> $class */
        $class = config('corex.tenant_context.resolver', SingleAccountResolver::class);

        return $class;
    }

    // ─── Bindings ─────────────────────────────────────────────────────────

    /** @return class-string<SettingsRepository> */
    public static function settingsRepositoryClass(): string
    {
        /** @var class-string<SettingsRepository> $class */
        $class = config('corex.bindings.settings', DatabaseSettingsRepository::class);

        return $class;
    }

    /** @return class-string<AuditLogger> */
    public static function auditLoggerClass(): string
    {
        /** @var class-string<AuditLogger> $class */
        $class = config('corex.bindings.audit_logger', QueuedAuditLogger::class);

        return $class;
    }

    /** @return class-string<SettingDefaultsProvider> */
    public static function settingDefaultsProviderClass(): string
    {
        /** @var class-string<SettingDefaultsProvider> $class */
        $class = config('corex.bindings.setting_defaults', NullSettingDefaultsProvider::class);

        return $class;
    }

    /** @return class-string<FeatureFlags> */
    public static function featureFlagsClass(): string
    {
        /** @var class-string<FeatureFlags> $class */
        $class = config('corex.bindings.feature_flags', DatabaseFeatureFlags::class);

        return $class;
    }

    /** @return class-string<DepartmentRepository> */
    public static function departmentRepositoryClass(): string
    {
        /** @var class-string<DepartmentRepository> $class */
        $class = config('corex.bindings.departments', DatabaseDepartmentRepository::class);

        return $class;
    }

    // ─── Audit ────────────────────────────────────────────────────────────

    public static function auditEnabled(): bool
    {
        return (bool) config('corex.audit.enabled', true);
    }

    // ─── Settings cache ───────────────────────────────────────────────────

    public static function settingsCacheStore(): string
    {
        return (string) config('corex.settings.cache_store', 'array');
    }

    public static function settingsCacheTtl(): ?int
    {
        $value = config('corex.settings.cache_ttl', 3600);

        return $value !== null ? (int) $value : null;
    }

    public static function settingsCachePrefix(): string
    {
        return (string) config('corex.settings.cache_prefix', 'corex.settings');
    }
}
