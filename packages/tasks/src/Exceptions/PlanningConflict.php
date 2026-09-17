<?php

declare(strict_types=1);

namespace CoreX\Tasks\Exceptions;

use DomainException;

final class PlanningConflict extends DomainException
{
    public static function forRequestKey(string $requestKey): self
    {
        return new self("Planning request [{$requestKey}] already exists with a different payload.");
    }
}
