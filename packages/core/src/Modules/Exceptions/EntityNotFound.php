<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class EntityNotFound extends RuntimeException
{
    public function __construct(string $handle)
    {
        parent::__construct(sprintf('Entity [%s] is not registered.', $handle));
    }
}
