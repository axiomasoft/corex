<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

final readonly class OperationResult
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $data,
        public bool $replayed = false,
        public array $metadata = [],
    ) {}
}
