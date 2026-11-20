<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SchemaOperationRef
{
    public function __construct(public string $id)
    {
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid SchemaOperationRef.');
        }
    }
}
