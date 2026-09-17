<?php

declare(strict_types=1);

namespace CoreX\Attributes\Support;

final readonly class FieldSnapshot
{
    /**
     * @param  array<array-key, mixed>  $activeFields
     * @param  array<array-key, mixed>  $driverCapabilities
     */
    public function __construct(
        public string $entity,
        public string $schemaVersion,
        public string $schemaHash,
        public array $activeFields,
        public array $driverCapabilities,
    ) {
        Validation::entity(entity: $entity);
        Validation::opaque(value: $schemaVersion, name: 'schema version');
        Validation::sha256(value: $schemaHash, name: 'schema hash');
        Validation::jsonMapList(values: $activeFields, name: 'active fields');
        Validation::jsonMap(value: $driverCapabilities, name: 'driver capabilities');
    }
}
