<?php

declare(strict_types=1);

namespace CoreX\Search\Rebuild\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class RebuildRef
{
    public function __construct(public string $id) {}
}
