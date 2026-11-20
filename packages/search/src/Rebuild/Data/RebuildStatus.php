<?php

declare(strict_types=1);

namespace CoreX\Search\Rebuild\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class RebuildStatus
{
    public function __construct(public RebuildRef $ref, public int $destination, public ?int $previous, public ?int $barrier, public string $state) {}
}
