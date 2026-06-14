<?php

declare(strict_types=1);

namespace CoreX\Support;

use CoreX\Audit\DatabaseAuditLogger;
use CoreX\Contracts\AuditLogger;
use CoreX\Contracts\EntityRegistry;
use CoreX\Contracts\SettingsRepository;
use CoreX\Contracts\TenantResolver;
use CoreX\Contracts\TenantService;
use CoreX\Enums\IdStrategy;
use CoreX\Registry\EntityRegistry as EntityRegistryImpl;
use CoreX\Settings\DatabaseSettingsRepository;
use CoreX\Tenancy\NullTenantService;

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
        return (string) config('corex.tables.settings', 'corex_settings');
    }

    public static function auditLogsTable(): string
    {
        return (string) config('corex.tables.audit_logs', 'corex_audit_logs');
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

    // ─── Bindings ─────────────────────────────────────────────────────────

    /** @return class-string<EntityRegistry> */
    public static function entityRegistryClass(): string
    {
        /** @var class-string<EntityRegistry> $class */
        $class = config('corex.bindings.entity_registry', EntityRegistryImpl::class);

        return $class;
    }

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
        $class = config('corex.bindings.audit_logger', DatabaseAuditLogger::class);

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
