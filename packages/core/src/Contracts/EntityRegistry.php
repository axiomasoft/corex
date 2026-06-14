<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Registry\EntityDefinition;

/**
 * Central registry of ecosystem entities. Modules register their models in
 * their service provider's boot(); the registry is the single source of the
 * polymorphic morph map (ADR-003) and drives auto-CRUD / search / admin later.
 */
interface EntityRegistry
{
    /**
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $metadata
     */
    public function register(string $modelClass, array $metadata = []): void;

    /** @return array<class-string, EntityDefinition> */
    public function all(): array;

    /** @param class-string $modelClass */
    public function definition(string $modelClass): ?EntityDefinition;

    /** @param class-string $modelClass */
    public function morphAlias(string $modelClass): ?string;

    /** @return class-string|null */
    public function classForAlias(string $alias): ?string;

    /** @return array<string, class-string> */
    public function morphMap(): array;
}
