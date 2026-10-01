<?php

declare(strict_types=1);

namespace CoreX\Panels\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class EntityReference
{
    public function __construct(public string $handle, public string $id)
    {
        if ($handle === '' || $id === '') {
            throw new InvalidArgumentException('Entity references require a handle and an id.');
        }
    }
}
