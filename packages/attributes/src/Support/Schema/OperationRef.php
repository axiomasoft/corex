<?php

declare(strict_types=1);

namespace CoreX\Attributes\Support\Schema;

use CoreX\Attributes\Support\Validation;

final readonly class OperationRef
{
    public function __construct(public string $id)
    {
        Validation::opaque(value: $id, name: 'operation reference');
    }
}
