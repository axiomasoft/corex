<?php

declare(strict_types=1);

namespace CoreX\Intl\Data;

final readonly class ReferenceIdentity
{
    public function __construct(
        public string $namespace,
        public string $sourceId,
    ) {}
}
