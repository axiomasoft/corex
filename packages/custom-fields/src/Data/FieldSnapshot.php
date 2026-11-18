<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\CustomFields\Internal\Support\Validation;
use Packman\Attributes\Api;

/** @phpstan-import-type JsonMap from ValueBag */
#[Api(since: '1.0')]
final readonly class FieldSnapshot
{
    /**
     * @param  list<JsonMap>  $activeFields
     * @param  JsonMap  $driverCapabilities
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
