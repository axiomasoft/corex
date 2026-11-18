<?php

declare(strict_types=1);

namespace CoreX\Notifications\Delivery\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class RecipientReference
{
    public function __construct(public string $kind, public string $id)
    {
        if ($kind === '' || $id === '' || strlen($kind) > 64 || strlen($id) > 128) {
            throw new InvalidArgumentException('Invalid notification recipient.');
        }
    }
}
