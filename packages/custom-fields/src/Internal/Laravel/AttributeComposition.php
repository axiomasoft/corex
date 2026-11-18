<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Laravel;

use CoreX\CustomFields\Internal\Value;
use Packman\Attributes\Internal;
use Packman\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type. */
#[Internal]
final readonly class AttributeComposition
{
    private const array ALLOWED_SOURCES = ['static', 'module'];

    /**
     * @param  list<string>  $sources
     * @param  array<string, list<array<string, mixed>>>  $definitions
     * @param  array<string, bool>  $drivers
     * @param  array<string, 'jsonb'|'eav'>  $storageByEntity
     */
    public function __construct(
        public array $sources,
        public string $storageDriver,
        public array $definitions,
        public array $drivers,
        public array $storageByEntity = [],
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $sources = self::sources(violations: $violations, value: $config['sources'] ?? ['static']);
        $storageDriver = self::storageDriver(violations: $violations, value: $config['storage_driver'] ?? 'jsonb');
        $definitions = self::definitions(violations: $violations, value: $config['definitions'] ?? []);
        $drivers = self::drivers(violations: $violations, value: $config['drivers'] ?? []);
        $storageByEntity = self::storageByEntity(violations: $violations, value: $config['storage_by_entity'] ?? []);
        $violations->failIfAny();

        return new self(
            sources: $sources,
            storageDriver: $storageDriver,
            definitions: $definitions,
            drivers: $drivers,
            storageByEntity: $storageByEntity,
        );
    }

    /**
     * @return list<string>
     */
    public function effectiveSources(bool $modulesBound): array
    {
        $sources = $this->sources;

        if (! $modulesBound) {
            $sources = array_values(array_filter(
                $sources,
                static fn (string $source): bool => $source !== 'module',
            ));
        } elseif (! in_array('module', $sources, true)) {
            $sources[] = 'module';
        }

        return $sources === [] ? ['static'] : $sources;
    }

    /**
     * @return list<string>
     */
    private static function sources(ConfigurationValidator $violations, mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            $violations->add(path: 'sources', message: 'must be a non-empty list');

            return ['static'];
        }

        $sources = [];
        foreach ($value as $index => $source) {
            if (! is_string($source) || ! in_array($source, self::ALLOWED_SOURCES, true)) {
                $violations->add(path: 'sources.'.$index, message: 'must be static or module');

                continue;
            }

            if (in_array($source, $sources, true)) {
                $violations->add(path: 'sources.'.$index, message: 'is duplicated');
            }

            $sources[] = $source;
        }

        $static = array_search('static', $sources, true);
        $module = array_search('module', $sources, true);

        if ($static !== false && $module !== false && $module < $static) {
            $violations->add(path: 'sources', message: 'module must follow static');
        }

        return $sources === [] ? ['static'] : $sources;
    }

    private static function storageDriver(ConfigurationValidator $violations, mixed $value): string
    {
        if (is_string($value) && in_array($value, ['jsonb', 'eav'], true)) {
            return $value;
        }

        $violations->add(path: 'storage_driver', message: 'must be jsonb or eav');

        return 'jsonb';
    }

    /**
     * @return array<string, 'jsonb'|'eav'>
     */
    private static function storageByEntity(ConfigurationValidator $violations, mixed $value): array
    {
        if ($value === []) {
            return [];
        }

        if (! is_array($value) || array_is_list($value)) {
            $violations->add(path: 'storage_by_entity', message: 'must be an entity map');

            return [];
        }

        $map = [];
        foreach ($value as $entity => $driver) {
            if (! is_string($entity) || preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $entity) !== 1
                || ! is_string($driver) || ! in_array($driver, ['jsonb', 'eav'], true)) {
                $violations->add(path: 'storage_by_entity', message: 'must map each entity to jsonb or eav');

                return [];
            }

            $map[$entity] = $driver;
        }

        return $map;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private static function definitions(ConfigurationValidator $violations, mixed $value): array
    {
        if (! is_array($value)) {
            $violations->add(path: 'definitions', message: 'must be a map of entity definitions');

            return [];
        }

        $definitions = [];
        foreach ($value as $entity => $fields) {
            if (! is_string($entity) || $entity === '' || ! is_array($fields) || ! array_is_list($fields)) {
                $violations->add(path: 'definitions', message: 'must be a map of entity field lists');

                continue;
            }

            $rows = [];
            foreach ($fields as $index => $field) {
                if (! is_array($field) || array_is_list($field) || ! is_string($field['code'] ?? null) || ! is_string($field['type'] ?? null)) {
                    $violations->add(path: 'definitions.'.$entity.'.'.$index, message: 'must be a field map with code and type');

                    continue;
                }

                if (array_key_exists('storage_driver', $field) || array_key_exists('storage_engine', $field)) {
                    $violations->add(path: 'definitions.'.$entity.'.'.$index, message: 'per-field storage driver is not supported');

                    continue;
                }

                $rows[] = Value::map($field);
            }

            $definitions[$entity] = $rows;
        }

        return $definitions;
    }

    /**
     * @return array<string, bool>
     */
    private static function drivers(ConfigurationValidator $violations, mixed $value): array
    {
        if (! is_array($value)) {
            $violations->add(path: 'drivers', message: 'must be a map');

            return [];
        }

        $drivers = [];
        foreach ($value as $name => $enabled) {
            if (! is_string($name) || ! is_bool($enabled)) {
                $violations->add(path: 'drivers', message: 'must be a map of booleans');

                continue;
            }

            $drivers[$name] = $enabled;
        }

        return $drivers;
    }
}
