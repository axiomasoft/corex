<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

use InvalidArgumentException;

final readonly class InboxQuery
{
    public function __construct(public ?string $before, public int $limit = 100)
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Notification inbox limit must be between 1 and 100.');
        }
    }
}
