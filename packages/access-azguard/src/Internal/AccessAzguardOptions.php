<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Internal;

use AzGuard\Models\Role;
use CoreX\Config\Laravel\ConfigurationValidator;

/** @internal Immutable configuration for bridge composition. */
final readonly class AccessAzguardOptions
{
    /** @param class-string<Role>|null $roleModel */
    public function __construct(
        public bool $bindPermissionLayer,
        public string $rolesTable,
        public string $morphType,
        public ?string $roleModel,
    ) {}

    /** @param array<array-key, mixed> $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $bindPermissionLayer = $config['bind_permission_layer'] ?? true;

        if (! is_bool($bindPermissionLayer)) {
            $violations->add(path: 'bind_permission_layer', message: 'must be a boolean');
            $bindPermissionLayer = false;
        }

        $pins = self::section(violations: $violations, path: 'azguard_pins', value: $config['azguard_pins'] ?? []);
        $tables = self::section(violations: $violations, path: 'azguard_pins.table_names', value: $pins['table_names'] ?? []);
        $columns = self::section(violations: $violations, path: 'azguard_pins.column_names', value: $pins['column_names'] ?? []);
        $models = self::section(violations: $violations, path: 'azguard_pins.models', value: $pins['models'] ?? []);
        $rolesTable = $tables['roles'] ?? 'az_guard_roles';

        if (! is_string($rolesTable) || trim($rolesTable) === '') {
            $violations->add(path: 'azguard_pins.table_names.roles', message: 'must be a non-empty string');
            $rolesTable = 'az_guard_roles';
        }

        $morphType = $columns['morph_type'] ?? 'uuid';

        if (! in_array($morphType, ['int', 'ulid', 'uuid'], true)) {
            $violations->add(path: 'azguard_pins.column_names.morph_type', message: 'must be int, ulid or uuid');
            $morphType = 'uuid';
        }

        $roleModel = array_key_exists('role', $models) ? $models['role'] : AzGuardRoleModel::class;

        if ($roleModel !== null && (! is_string($roleModel) || ! is_a($roleModel, Role::class, true))) {
            $violations->add(path: 'azguard_pins.models.role', message: 'must be an AzGuard role class or null');
            $roleModel = AzGuardRoleModel::class;
        }

        $violations->failIfAny();

        return new self(
            bindPermissionLayer: $bindPermissionLayer,
            rolesTable: $rolesTable,
            morphType: $morphType,
            roleModel: $roleModel,
        );
    }

    /** @return array<array-key, mixed> */
    private static function section(ConfigurationValidator $violations, string $path, mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be an array');

        return [];
    }
}
