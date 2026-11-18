<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DocumentCursor
{
    public function __construct(public ?string $value) {}
}
