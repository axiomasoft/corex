<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\CustomFields\Internal\Support\Validation;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
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
