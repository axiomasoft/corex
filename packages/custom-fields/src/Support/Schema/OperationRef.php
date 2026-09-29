<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Support\Schema;

use CoreX\CustomFields\Support\Validation;

final readonly class OperationRef
{
    public function __construct(public string $id)
    {
        Validation::opaque(value: $id, name: 'operation reference');
    }
}
