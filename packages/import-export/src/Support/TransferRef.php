<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Support;

final readonly class TransferRef
{
    public function __construct(public string $id)
    {
        Validation::opaque(value: $id, name: 'transfer id');
    }
}
