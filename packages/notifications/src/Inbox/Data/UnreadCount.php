<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class UnreadCount
{
    private function __construct(public ?int $count, public ?string $reason) {}

    public static function exact(int $count): self
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Unread count cannot be negative.');
        }

        return new self($count, null);
    }

    public static function unavailable(string $reason): self
    {
        if (preg_match('/\A[a-z][a-z0-9._-]{0,95}\z/D', $reason) !== 1) {
            throw new InvalidArgumentException('Unread count unavailability reason is invalid.');
        }

        return new self(null, $reason);
    }
}
