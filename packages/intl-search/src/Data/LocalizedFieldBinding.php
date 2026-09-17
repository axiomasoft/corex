<?php

declare(strict_types=1);

namespace CoreX\IntlSearch\Data;

final readonly class LocalizedFieldBinding
{
    public function __construct(
        public string $column,
        public string $declaredField,
        public string $schemaHash,
    ) {}
}
