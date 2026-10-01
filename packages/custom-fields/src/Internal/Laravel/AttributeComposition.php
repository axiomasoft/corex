<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Laravel;

use CoreX\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type. */
final readonly class AttributeComposition
{
    private const ALLOWED_SOURCES = ['static', 'module'];

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

    /** @param  array<string, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $sources = self::sources($violations, $config['sources'] ?? ['static']);
        $storageDriver = self::storageDriver($violations, $config['storage_driver'] ?? 'jsonb');
        $definitions = self::definitions($violations, $config['definitions'] ?? []);
        $drivers = self::drivers($violations, $config['drivers'] ?? []);
        $storageByEntity = self::storageByEntity($violations, $config['storage_by_entity'] ?? []);
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
            $violations->add('sources', 'must be a non-empty list');

            return ['static'];
        }

        $sources = [];
        foreach ($value as $index => $source) {
            if (! is_string($source) || ! in_array($source, self::ALLOWED_SOURCES, true)) {
                $violations->add('sources.'.$index, 'must be static or module');

                continue;
            }

            if (in_array($source, $sources, true)) {
                $violations->add('sources.'.$index, 'is duplicated');
            }

            $sources[] = $source;
        }

        $static = array_search('static', $sources, true);
        $module = array_search('module', $sources, true);

        if ($static !== false && $module !== false && $module < $static) {
            $violations->add('sources', 'module must follow static');
        }

        return $sources === [] ? ['static'] : $sources;
    }

    private static function storageDriver(ConfigurationValidator $violations, mixed $value): string
    {
        if (is_string($value) && in_array($value, ['jsonb', 'eav'], true)) {
            return $value;
        }

        $violations->add('storage_driver', 'must be jsonb or eav');

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
            $violations->add('storage_by_entity', 'must be an entity map');

            return [];
        }

        $map = [];
        foreach ($value as $entity => $driver) {
            if (! is_string($entity) || preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $entity) !== 1
                || ! is_string($driver) || ! in_array($driver, ['jsonb', 'eav'], true)) {
                $violations->add('storage_by_entity', 'must map each entity to jsonb or eav');

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
            $violations->add('definitions', 'must be a map of entity definitions');

            return [];
        }

        $definitions = [];
        foreach ($value as $entity => $fields) {
            if (! is_string($entity) || $entity === '' || ! is_array($fields) || ! array_is_list($fields)) {
                $violations->add('definitions', 'must be a map of entity field lists');

                continue;
            }

            $rows = [];
            foreach ($fields as $index => $field) {
                if (! is_array($field) || array_is_list($field) || ! is_string($field['code'] ?? null) || ! is_string($field['type'] ?? null)) {
                    $violations->add('definitions.'.$entity.'.'.$index, 'must be a field map with code and type');

                    continue;
                }

                if (array_key_exists('storage_driver', $field) || array_key_exists('storage_engine', $field)) {
                    $violations->add('definitions.'.$entity.'.'.$index, 'per-field storage driver is not supported');

                    continue;
                }

                $rows[] = $field;
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
            $violations->add('drivers', 'must be a map');

            return [];
        }

        $drivers = [];
        foreach ($value as $name => $enabled) {
            if (! is_string($name) || ! is_bool($enabled)) {
                $violations->add('drivers', 'must be a map of booleans');

                continue;
            }

            $drivers[$name] = $enabled;
        }

        return $drivers;
    }
}
