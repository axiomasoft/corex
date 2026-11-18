<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class LocalizedFieldBinding
{
    public function __construct(
        public string $column,
        public string $declaredField,
        public string $schemaHash,
    ) {}
}
