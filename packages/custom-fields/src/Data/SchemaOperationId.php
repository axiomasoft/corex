<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\CustomFields\Internal\Support\Validation;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class SchemaOperationId
{
    public function __construct(
        public string $key,
        public string $payloadHash,
    ) {
        Validation::opaque(value: $key, name: 'operation key');
        Validation::sha256(value: $payloadHash, name: 'operation payload hash');
    }
}
