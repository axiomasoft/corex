<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\CustomFields\Internal\Support\Validation;

final readonly class OperationRef
{
    public function __construct(public string $id)
    {
        Validation::opaque(value: $id, name: 'operation reference');
    }
}
