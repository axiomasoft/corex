<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data;

final readonly class TransferRef
{
    public function __construct(public string $id)
    {
        Validation::opaque(value: $id, name: 'transfer id');
    }
}
