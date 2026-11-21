<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class CleanupReceipt
{
    public function __construct(public CleanupAction $action, public string $evidence)
    {
        if ($evidence === '') {
            throw new InvalidArgumentException('Owner cleanup requires durable evidence.');
        }
    }
}
