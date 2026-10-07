<?php

declare(strict_types=1);

namespace CoreX\Access\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Laravel\ConfigurationValidator;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class AuthOptions
{
    /**
     * @param  array<string, string>  $identityColumns
     */
    public function __construct(
        public string $panel,
        public ?string $cacheStore,
        public ?string $workspaceScopeClass,
        public bool $identityEnabled,
        public ?string $identityConnection,
        public string $identityTable,
        public array $identityColumns,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $panel = self::nonEmpty(violations: $violations, path: 'panel', value: $config['panel'] ?? 'app');

        $cache = is_array($config['cache'] ?? null) ? $config['cache'] : [];
        $cacheStore = self::nullableString($cache['store'] ?? null);

        $workspaceScopeClass = self::nullableString($config['workspace_scope_class'] ?? null);

        if ($workspaceScopeClass === '') {
            $workspaceScopeClass = null;
        }

        $identity = is_array($config['identity'] ?? null) ? $config['identity'] : [];
        $identityEnabled = self::bool(violations: $violations, path: 'identity.enabled', value: $identity['enabled'] ?? true);
        $identityConnection = self::nullableString($identity['connection'] ?? null);
        $identityTable = self::nonEmpty(violations: $violations, path: 'identity.table', value: $identity['table'] ?? 'users');

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
                    $violations->add(path: 'identity.columns.'.$key, message: 'must be a non-empty string');
                    $identityColumns[$key] = $default;

                    continue;
                }

                $value = $rawColumns[$key];

                if (is_string($value) && $value !== '') {
                    $identityColumns[$key] = $value;

                    continue;
                }

                $violations->add(path: 'identity.columns.'.$key, message: 'must be a non-empty string');
                $identityColumns[$key] = $default;
            }
        }

        $violations->failIfAny();

        return new self(
            panel: $panel,
            cacheStore: $cacheStore,
            workspaceScopeClass: $workspaceScopeClass,
            identityEnabled: $identityEnabled,
            identityConnection: $identityConnection,
            identityTable: $identityTable,
            identityColumns: $identityColumns,
        );
    }

    private static function bool(ConfigurationValidator $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a boolean');

        return false;
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a non-empty string');

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
