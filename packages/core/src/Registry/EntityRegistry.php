<?php

declare(strict_types=1);

namespace CoreX\Registry;

use CoreX\Contracts\EntityRegistry as EntityRegistryContract;
use Illuminate\Support\Str;

final class EntityRegistry implements EntityRegistryContract
{
    /** @var array<class-string, EntityDefinition> */
    private array $entities = [];

    public function register(string $modelClass, array $metadata = []): void
    {
        $alias = isset($metadata['alias'])
            ? (string) $metadata['alias']
            : $this->defaultAlias($modelClass);

        $this->entities[$modelClass] = new EntityDefinition(
            modelClass: $modelClass,
            morphAlias: $alias,
            searchable: (bool) ($metadata['searchable'] ?? false),
            auditable: (bool) ($metadata['auditable'] ?? false),
            api: (bool) ($metadata['api'] ?? false),
            metadata: $metadata,
        );
    }

    public function all(): array
    {
        return $this->entities;
    }

    public function definition(string $modelClass): ?EntityDefinition
    {
        return $this->entities[$modelClass] ?? null;
    }

    public function morphAlias(string $modelClass): ?string
    {
        return $this->definition($modelClass)?->morphAlias;
    }

    public function classForAlias(string $alias): ?string
    {
        foreach ($this->entities as $definition) {
            if ($definition->morphAlias === $alias) {
                return $definition->modelClass;
            }
        }

        return null;
    }

    public function morphMap(): array
    {
        $map = [];

        foreach ($this->entities as $definition) {
            $map[$definition->morphAlias] = $definition->modelClass;
        }

        return $map;
    }

    /** @param class-string $modelClass */
    private function defaultAlias(string $modelClass): string
    {
        return Str::snake(class_basename($modelClass));
    }
}
