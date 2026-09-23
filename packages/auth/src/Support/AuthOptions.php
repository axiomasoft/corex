<?php

declare(strict_types=1);

namespace CoreX\Auth\Support;

use CoreX\Support\ViolationList;

final readonly class AuthOptions
{
    /**
     * @param  array<string, string>  $identityColumns
     * @param  array<string, mixed>  $azguardPins
     */
    public function __construct(
        public string $panel,
        public ?string $cacheStore,
        public bool $bindPermissionLayer,
        public ?string $workspaceScopeClass,
        public bool $identityEnabled,
        public ?string $identityConnection,
        public string $identityTable,
        public array $identityColumns,
        public array $azguardPins,
    ) {}

    /** @param  array<string, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ViolationList;

        $panel = self::nonEmpty($violations, 'panel', $config['panel'] ?? 'app');

        $cache = is_array($config['cache'] ?? null) ? $config['cache'] : [];
        $cacheStore = self::nullableString($cache['store'] ?? null);

        $bindPermissionLayer = self::bool($violations, 'bind_permission_layer', $config['bind_permission_layer'] ?? true);

        $workspaceScopeClass = self::nullableString($config['workspace_scope_class'] ?? null);

        if ($workspaceScopeClass === '') {
            $workspaceScopeClass = null;
        }

        $identity = is_array($config['identity'] ?? null) ? $config['identity'] : [];
        $identityEnabled = self::bool($violations, 'identity.enabled', $identity['enabled'] ?? true);
        $identityConnection = self::nullableString($identity['connection'] ?? null);
        $identityTable = self::nonEmpty($violations, 'identity.table', $identity['table'] ?? 'users');

        $defaults = [
            'id' => 'id',
            'email' => 'email',
            'password' => 'password_hash',
            'active' => 'is_active',
            'deleted_at' => 'deleted_at',
        ];
        $rawColumns = is_array($identity['columns'] ?? null) ? $identity['columns'] : null;
        $identityColumns = [];

        if ($rawColumns === null) {
            $identityColumns = $defaults;
        } else {
            foreach ($defaults as $key => $default) {
                if (! array_key_exists($key, $rawColumns)) {
                    $violations->add('identity.columns.'.$key, 'must be a non-empty string');
                    $identityColumns[$key] = $default;

                    continue;
                }

                $value = $rawColumns[$key];

                if (is_string($value) && $value !== '') {
                    $identityColumns[$key] = $value;

                    continue;
                }

                $violations->add('identity.columns.'.$key, 'must be a non-empty string');
                $identityColumns[$key] = $default;
            }
        }

        $azguardPins = is_array($config['azguard_pins'] ?? null) ? $config['azguard_pins'] : [];

        $violations->failIfAny();

        return new self(
            panel: $panel,
            cacheStore: $cacheStore,
            bindPermissionLayer: $bindPermissionLayer,
            workspaceScopeClass: $workspaceScopeClass,
            identityEnabled: $identityEnabled,
            identityConnection: $identityConnection,
            identityTable: $identityTable,
            identityColumns: $identityColumns,
            azguardPins: $azguardPins,
        );
    }

    private static function bool(ViolationList $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add($path, 'must be a boolean');

        return false;
    }

    private static function nonEmpty(ViolationList $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add($path, 'must be a non-empty string');

        return 'invalid';
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }
}
