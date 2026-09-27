<?php

declare(strict_types=1);

namespace CoreX\Notifications\Contracts;

use CoreX\Notifications\Data\NotificationContext;
use CoreX\Notifications\Data\NotificationPreference;
use CoreX\Notifications\Data\PreferenceInput;

interface PreferenceRepository
{
    public function get(NotificationContext $context, string $eventType): NotificationPreference;

    public function set(NotificationContext $context, PreferenceInput $input): NotificationPreference;
}
