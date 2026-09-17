<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Support;

final readonly class MappingSnapshot
{
    /** @param array<string, null|bool|int|string|array<array-key, mixed>> $definition */
    public function __construct(public string $version, public string $schemaHash, public array $definition, public string $sha256)
    {
        Validation::opaque(value: $version, name: 'mapping version');
        Validation::sha256(value: $schemaHash, name: 'mapping schema hash');
        Validation::jsonMap(value: $definition, name: 'mapping definition');
        Validation::sha256(value: $sha256, name: 'mapping hash');
    }
}
