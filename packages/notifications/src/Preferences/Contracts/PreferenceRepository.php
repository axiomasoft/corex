<?php

declare(strict_types=1);

namespace CoreX\Notifications\Preferences\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Preferences\Data\NotificationPreference;
use CoreX\Notifications\Preferences\Data\PreferenceInput;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface PreferenceRepository
{
    public function get(NotificationContext $context, string $eventType): NotificationPreference;

    public function set(NotificationContext $context, PreferenceInput $input): NotificationPreference;
}
