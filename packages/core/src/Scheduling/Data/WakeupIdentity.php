<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class WakeupIdentity
{
    public function __construct(public string $kind, public string $requestKey)
    {
        if (preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/', $kind) !== 1 || $requestKey === ''
            || strlen($requestKey) > 128 || preg_match('//u', $requestKey) !== 1 || str_contains($requestKey, "\0")) {
            throw new InvalidArgumentException('Invalid wakeup identity.');
        }
    }
}
