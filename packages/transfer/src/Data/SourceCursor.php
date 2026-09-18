<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data;

final readonly class SourceCursor
{
    public function __construct(public string $ordinal, public ?string $boundaryByteOffset, public string $sourceHash)
    {
        Validation::unsignedInteger(value: $ordinal, name: 'source ordinal');

        if ($boundaryByteOffset !== null) {
            Validation::unsignedInteger(value: $boundaryByteOffset, name: 'source boundary offset');
        }
        Validation::sha256(value: $sourceHash, name: 'source hash');
    }
}
