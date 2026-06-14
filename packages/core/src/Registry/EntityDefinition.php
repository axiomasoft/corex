<?php

declare(strict_types=1);

namespace CoreX\Registry;

/**
 * Immutable description of a registered entity. Drives the morph map now and
 * auto-CRUD / search / admin generation in downstream modules.
 */
final class EntityDefinition
{
    /**
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $morphAlias,
        public readonly bool $searchable = false,
        public readonly bool $auditable = false,
        public readonly bool $api = false,
        public readonly array $metadata = [],
    ) {}

    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
