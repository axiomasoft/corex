<?php

declare(strict_types=1);

namespace CoreX\Features;

use CoreX\Contracts\FeatureFlagDefinition;
use CoreX\Contracts\FeatureFlags;
use CoreX\Contracts\SettingsScope;
use CoreX\Enums\SettingScope;
use CoreX\Models\FeatureFlag;
use CoreX\Models\FeatureOverride;
use Override;

/**
 * Persisted feature flags on `sys_feature_flags`/`sys_feature_overrides`
 * (B-10 §3.1, AC-22). Replaces the interim config-driven `FeatureResolver`/
 * `ConfigFeatureResolver` (plan.md D27) — a single source of truth, no
 * config-fallback lane.
 *
 * `$connection` mirrors `DatabaseSettingsRepository`'s seam (null = app
 * default), used by the PG-only test lane (D12).
 */
final class DatabaseFeatureFlags implements FeatureFlags
{
    public function __construct(
        private readonly ?string $connection = null,
    ) {}

    #[Override]
    public function active(string $key, ?SettingsScope $scope = null): bool
    {
        $flag = $this->find($key);

        if (! $flag instanceof FeatureFlag) {
            return false;
        }

        $override = $this->findOverride($flag->id, $scope);

        return $override instanceof FeatureOverride ? $override->is_enabled : $flag->is_enabled;
    }

    #[Override]
    public function value(string $key, ?SettingsScope $scope = null): mixed
    {
        $flag = $this->find($key);

        if (! $flag instanceof FeatureFlag) {
            return null;
        }

        $override = $this->findOverride($flag->id, $scope);

        return $override instanceof FeatureOverride ? $override->payload : $flag->payload;
    }

    #[Override]
    public function define(FeatureFlagDefinition $def): void
    {
        $existing = $this->find($def->key);

        if ($existing instanceof FeatureFlag) {
            return;
        }

        FeatureFlag::on($this->connection)->create([
            'key' => $def->key,
            'module' => $def->module,
            'is_enabled' => $def->default,
            'payload' => $def->payload,
        ]);
    }

    private function find(string $key): ?FeatureFlag
    {
        /** @var FeatureFlag|null */
        return FeatureFlag::on($this->connection)->where('key', $key)->first();
    }

    /**
     * `null`/account scope has no override row — the flag's own `is_enabled`
     * IS the account-level value. Department scope is schema-ready
     * (sys_feature_overrides.scope_type CHECK) but unreachable via
     * SettingsScope until P1.10 (see Contracts\FeatureFlags docblock).
     */
    private function findOverride(string $flagId, ?SettingsScope $scope): ?FeatureOverride
    {
        if ($scope === null || $scope->type === SettingScope::Account) {
            return null;
        }

        /** @var FeatureOverride|null */
        return FeatureOverride::on($this->connection)
            ->where('flag_id', $flagId)
            ->where('scope_type', $scope->type->value)
            ->where('scope_id', $scope->id)
            ->first();
    }
}
