<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class InboxPage
{
    /** @param list<NotificationRef> $items */
    public function __construct(public array $items, public ?string $cursor) {}
}
