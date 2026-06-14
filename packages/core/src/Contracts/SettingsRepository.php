<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Enums\SettingScope;

/**
 * Hierarchical settings store: System → Tenant → User.
 */
interface SettingsRepository
{
    public function get(string $key, mixed $default = null, SettingScope $scope = SettingScope::System, ?string $scopeId = null): mixed;

    public function set(string $key, mixed $value, SettingScope $scope = SettingScope::System, ?string $scopeId = null): void;

    public function forget(string $key, SettingScope $scope = SettingScope::System, ?string $scopeId = null): void;
}
