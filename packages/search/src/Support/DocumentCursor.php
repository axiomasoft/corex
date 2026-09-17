<?php

declare(strict_types=1);

namespace CoreX\Search\Support;

final readonly class DocumentCursor
{
    public function __construct(public ?string $value) {}
}
