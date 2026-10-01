<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\Attributes\Api;
use CoreX\CustomFields\Internal\Support\Validation;
use InvalidArgumentException;

/** @phpstan-import-type JsonMap from \CoreX\CustomFields\Data\ValueBag */
#[Api(since: '1.0')]
final readonly class SchemaChange
{
    /** @param JsonMap $definition */
    public function __construct(
        public string $entity,
        public string $kind,
        public string $expectedSchemaHash,
        public array $definition,
        public string $approvalDigest,
    ) {
        Validation::entity(entity: $entity);

        if (! in_array($kind, ['add', 'change', 'retire', 'drop'], strict: true)) {
            throw new InvalidArgumentException('Schema change kind is unsupported.');
        }

        Validation::sha256(value: $expectedSchemaHash, name: 'expected schema hash');
        Validation::jsonMap(value: $definition, name: 'schema definition');
        Validation::sha256(value: $approvalDigest, name: 'approval digest');
    }
}
