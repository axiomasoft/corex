<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class PlanDigest
{
    public function __construct(public string $value)
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid lifecycle plan digest.');
        }
    }
}
