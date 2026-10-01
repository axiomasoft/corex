<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\Attributes\Api;
use CoreX\CustomFields\Internal\Support\Validation;

#[Api(since: '1.0')]
final readonly class OperationRef
{
    public function __construct(public string $id)
    {
        Validation::opaque(value: $id, name: 'operation reference');
    }
}
