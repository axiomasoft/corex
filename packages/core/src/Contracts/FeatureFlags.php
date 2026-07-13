<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Per-account feature-flag store with workspace/user overrides (B-10 §3.1,
 * AC-22). `$scope` addresses ONE override level — `null` reads the
 * account-level default carried by the flag row itself. Department-scoped
 * overrides (`sys_feature_overrides.scope_type = 'department'`) are schema-
 * ready but not reachable through this contract yet: `SettingsScope` has no
 * department level before P1.10 introduces departments (see P1.9 Pending Work).
 */
interface FeatureFlags
{
    public function active(string $key, ?SettingsScope $scope = null): bool;

    public function value(string $key, ?SettingsScope $scope = null): mixed;

    /** Idempotent — a second `define()` for the same key never overwrites an admin-toggled `is_enabled`. */
    public function define(FeatureFlagDefinition $def): void;
}
