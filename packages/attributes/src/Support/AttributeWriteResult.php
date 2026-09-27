<?php

declare(strict_types=1);

namespace CoreX\Attributes\Support;

use InvalidArgumentException;

final readonly class AttributeWriteResult
{
    public function __construct(
        public string $status,
        public string $recordVersion,
        public string $payloadHash,
    ) {
        if (! in_array($status, ['applied', 'replayed'], strict: true)) {
            throw new InvalidArgumentException('Write result status must be applied or replayed.');
        }

        Validation::opaque(value: $recordVersion, name: 'record version');
        Validation::sha256(value: $payloadHash, name: 'payload hash');
    }
}
