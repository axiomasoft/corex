<?php

declare(strict_types=1);

namespace CoreX\Attributes\Support\Schema;

use CoreX\Attributes\Support\Validation;

final readonly class OperationKey
{
    public function __construct(
        public string $key,
        public string $payloadHash,
    ) {
        Validation::opaque(value: $key, name: 'operation key');
        Validation::sha256(value: $payloadHash, name: 'operation payload hash');
    }
}
