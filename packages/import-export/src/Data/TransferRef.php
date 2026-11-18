<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class TransferRef
{
    public function __construct(public string $id)
    {
        Validation::opaque(value: $id, name: 'transfer id');
    }
}
