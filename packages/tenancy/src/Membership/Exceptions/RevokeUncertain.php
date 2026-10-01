<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class RevokeUncertain extends RuntimeException
{
    public function __construct(public readonly string $requestId)
    {
        parent::__construct('Central revoke outcome uncertain; retain original request ID and payload.');
    }
}
