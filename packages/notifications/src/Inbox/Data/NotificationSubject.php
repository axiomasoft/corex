<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

final readonly class NotificationSubject
{
    public function __construct(public ?string $notificationId, public RecipientReference $recipient, public ?EntityReference $related) {}
}
