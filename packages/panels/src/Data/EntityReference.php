<?php

declare(strict_types=1);

namespace CoreX\Panels\Data;

use InvalidArgumentException;

final readonly class EntityReference
{
    public function __construct(public string $handle, public string $id)
    {
        if ($handle === '' || $id === '') {
            throw new InvalidArgumentException('Entity references require a handle and an id.');
        }
    }
}
