<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\Attributes\Api;
use CoreX\CustomFields\Internal\Support\Validation;
use InvalidArgumentException;

/** @phpstan-import-type JsonMap from ValueBag */
#[Api(since: '1.0')]
final readonly class DriftReport
{
    /** @param list<JsonMap> $drift */
    public function __construct(
        public string $entity,
        public string $schemaHash,
        public array $drift,
        public string $status,
    ) {
        Validation::entity(entity: $entity);
        Validation::sha256(value: $schemaHash, name: 'schema hash');
        Validation::jsonMapList(values: $drift, name: 'schema drift');

        if (! in_array($status, ['clean', 'drifted', 'unknown'], strict: true)) {
            throw new InvalidArgumentException('Drift report status is unsupported.');
        }
    }
}
