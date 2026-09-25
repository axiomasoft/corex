<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

final readonly class OperationResult
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    public function __construct(
        public array $data,
        public bool $replayed = false,
    ) {}
}
