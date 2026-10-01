<?php

declare(strict_types=1);

namespace CoreX\Settings\Contracts;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;
use CoreX\Settings\Data\SettingsScope;

/**
 * Hierarchical settings store: user → workspace → account →
 * manifest-declared default ({@see SettingDefaultsProvider}).
 */
#[Api(since: '1.0')]
interface SettingsRepository
{
    public function get(string $namespace, string $key, ?SettingsScope $scope = null, ?JsonDocument $default = null): JsonDocument;

    public function set(string $namespace, string $key, JsonDocument $value, ?SettingsScope $scope = null): void;

    public function forget(string $namespace, string $key, ?SettingsScope $scope = null): void;

    /** @return array<string, JsonDocument> merged by cascade — narrower scope overrides broader */
    public function all(string $namespace, ?SettingsScope $scope = null): array;
}
