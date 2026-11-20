<?php

declare(strict_types=1);

namespace CoreX\Data\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class EntityHandle
{
    public function __construct(public string $value)
    {
        if (preg_match('/\A[a-z][a-z0-9._-]{0,127}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Invalid entity handle.');
        }
    }
}
