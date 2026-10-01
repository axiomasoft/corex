<?php

declare(strict_types=1);

namespace CoreX\Notifications\Preferences\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class NotificationPreference
{
    /** @param list<string> $channels */
    public function __construct(public string $version, public array $channels, public string $digest)
    {
        if ($version === '' || ! in_array($digest, ['instant', 'hourly', 'daily', 'off'], true) || $channels === [] || count($channels) !== count(array_unique($channels))) {
            throw new InvalidArgumentException('Invalid notification preference.');
        }
    }
}
