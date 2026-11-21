<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ReferenceCursor
{
    public function __construct(public string $position, public string $revision) {}
}
