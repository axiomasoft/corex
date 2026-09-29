<?php

declare(strict_types=1);

namespace CoreX\Config\Exceptions;

use InvalidArgumentException;

final class InvalidConfiguration extends InvalidArgumentException
{
    /** @param list<string> $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(
            'Invalid configuration ('.count($violations).'): '.implode('; ', $violations),
        );
    }
}
