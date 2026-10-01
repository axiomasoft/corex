<?php

declare(strict_types=1);

namespace CoreX\Config\Exceptions;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
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
