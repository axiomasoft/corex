<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Exceptions;

use CoreX\Attributes\Api;
use DomainException;

#[Api(since: '1.0')]
final class PlanningConflict extends DomainException
{
    public static function forRequestKey(string $requestKey): self
    {
        return new self("Planning request [{$requestKey}] already exists with a different payload.");
    }
}
