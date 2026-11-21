<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class LifecyclePlanRef
{
    public function __construct(public string $id)
    {
        if (! Str::isUuid($id)) {
            throw new InvalidArgumentException('Invalid lifecycle plan ID.');
        }
    }
}
