<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\CustomFields\Internal\Support\Validation;
use InvalidArgumentException;
use Packman\Attributes\Api;

/** @phpstan-import-type JsonMap from ValueBag */
#[Api(since: '1.0')]
final readonly class AttributeDefinition
{
    /**
     * @param  JsonMap  $constraints
     * @param  list<string>  $readKeys
     * @param  list<string>  $writeKeys
     * @param  JsonMap  $meta
     */
    public function __construct(
        public AttributeId $id,
        public string $entity,
        public string $key,
        public int $revision,
        public string $type,
        public AttributeLifecycle $lifecycle,
        public bool $required,
        public bool $searchable,
        public array $constraints,
        public array $readKeys,
        public array $writeKeys,
        public IndexPolicy $index,
        public array $meta = [],
    ) {
        if ($this->revision < 1) {
            throw new InvalidArgumentException('Attribute revision must be at least 1.');
        }

        if ($this->entity === '' || $this->key === '' || $this->type === '') {
            throw new InvalidArgumentException('Attribute entity, key, and type are required.');
        }

        $legacy = ($this->meta['legacy_identity'] ?? false) === true;

        if ($legacy && ! $this->id->isLegacy()) {
            throw new InvalidArgumentException('Legacy attribute identity must be a UUIDv5 bridge.');
        }

        if (! $legacy && ! $this->id->isV7()) {
            throw new InvalidArgumentException('New attribute ids must be UUIDv7.');
        }
    }

    /**
     * @param  list<self>  $definitions
     * @return list<self>
     */
    public static function canonicalList(array $definitions): array
    {
        $byId = [];
        $keys = [];
        foreach ($definitions as $definition) {
            $id = $definition->id->value;
            $key = $definition->entity."\0".$definition->key;

            if (isset($keys[$key])) {
                throw new InvalidArgumentException("Duplicate attribute key [{$definition->key}].");
            }

            $keys[$key] = $id;
            $previous = $byId[$id] ?? null;

            if ($previous instanceof self && $previous->revision === $definition->revision) {
                throw new InvalidArgumentException("Duplicate attribute id [{$id}].");
            }

            if ($previous instanceof self) {
                $previous->lifecycle->assertTransition($definition->lifecycle);
            }

            $byId[$id] = $definition;
        }

        usort($definitions, static fn (self $left, self $right): int => [$left->id->value, $left->revision] <=> [$right->id->value, $right->revision]);

        return $definitions;
    }

    /** @return array{constraints: JsonMap, entity: string, id: string, index: string, key: string, lifecycle: string, meta: JsonMap, read_keys: list<string>, required: bool, revision: int, searchable: bool, type: string, write_keys: list<string>} */
    public function canonical(): array
    {
        $constraints = self::sortMap($this->constraints);
        $meta = self::sortMap($this->meta);
        $constraints = Validation::validatedMap(value: $constraints, name: 'constraints');
        $meta = Validation::validatedMap(value: $meta, name: 'meta');

        return [
            'constraints' => $constraints,
            'entity' => $this->entity,
            'id' => $this->id->value,
            'index' => $this->index->value,
            'key' => $this->key,
            'lifecycle' => $this->lifecycle->value,
            'meta' => $meta,
            'read_keys' => $this->readKeys,
            'required' => $this->required,
            'revision' => $this->revision,
            'searchable' => $this->searchable,
            'type' => $this->type,
            'write_keys' => $this->writeKeys,
        ];
    }

    /** @return array{code: string, constraints: JsonMap, id: string, index: string, key: string, lifecycle: string, meta: JsonMap, read_keys: list<string>, required: bool, revision: int, searchable: bool, type: string, write_keys: list<string>} */
    public function snapshotRow(): array
    {
        return [
            'code' => $this->key,
            'constraints' => $this->constraints,
            'id' => $this->id->value,
            'index' => $this->index->value,
            'key' => $this->key,
            'lifecycle' => $this->lifecycle->value,
            'meta' => $this->meta,
            'read_keys' => $this->readKeys,
            'required' => $this->required,
            'revision' => $this->revision,
            'searchable' => $this->searchable,
            'type' => $this->type,
            'write_keys' => $this->writeKeys,
        ];
    }

    /**
     * @param  list<self>  $definitions
     */
    public static function schemaHash(string $entity, array $definitions): string
    {
        $canonical = array_map(
            static fn (self $definition): array => $definition->canonical(),
            self::canonicalList($definitions),
        );
        $payload = json_encode($canonical, JSON_THROW_ON_ERROR);

        return hash('sha256', $entity."\n".$payload);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function sortMap(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => is_array($item) ? self::sortMap($item) : $item,
                $value,
            );
        }

        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortMap($item);
            }
        }

        return $value;
    }
}
