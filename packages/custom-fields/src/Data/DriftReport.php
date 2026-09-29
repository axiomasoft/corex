<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\CustomFields\Internal\Support\Validation;
use InvalidArgumentException;

final readonly class DriftReport
{
    /** @param array<array-key, mixed> $drift */
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
