<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ClaimGeneration
{
    public function __construct(public int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Invalid ClaimGeneration.');
        }
    }
}
