<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\CustomFields\Internal\Support\Validation;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class SchemaReceipt
{
    public function __construct(
        public OperationRef $operation,
        public string $status,
        public string $schemaHash,
        public ?string $errorCode,
    ) {
        if (! in_array($status, ['pending', 'running', 'verified', 'failed', 'unknown'], strict: true)) {
            throw new InvalidArgumentException('Schema receipt status is unsupported.');
        }

        Validation::sha256(value: $schemaHash, name: 'schema hash');

        if ($errorCode !== null) {
            Validation::opaque(value: $errorCode, name: 'schema error code');
        }

        if (($status === 'failed') !== ($errorCode !== null)) {
            throw new InvalidArgumentException('Only a failed schema receipt may carry an error code.');
        }
    }
}
