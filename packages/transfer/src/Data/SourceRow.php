<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data;

final readonly class SourceRow
{
    /** @param array<string, null|bool|int|string|array<array-key, mixed>> $values */
    public function __construct(public string $ordinal, public array $values, public string $sourceHash)
    {
        Validation::unsignedInteger(value: $ordinal, name: 'source ordinal');
        Validation::jsonMap(value: $values, name: 'source row');
        Validation::sha256(value: $sourceHash, name: 'source hash');
    }
}
