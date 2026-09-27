<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

use InvalidArgumentException;

final readonly class EntityReference
{
    public function __construct(public string $handle, public string $id)
    {
        if ($handle === '' || $id === '' || strlen($handle) > 128 || strlen($id) > 128) {
            throw new InvalidArgumentException('Invalid notification entity reference.');
        }
    }
}
