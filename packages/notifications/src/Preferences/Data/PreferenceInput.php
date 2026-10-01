<?php

declare(strict_types=1);

namespace CoreX\Notifications\Preferences\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class PreferenceInput
{
    /** @param list<string> $channels */
    public function __construct(public string $eventType, public array $channels, public string $digest, public string $expectedVersion)
    {
        if ($eventType === '' || strlen($eventType) > 128 || $expectedVersion === '' || ! in_array($digest, ['instant', 'hourly', 'daily', 'off'], true) || $channels === [] || count($channels) !== count(array_unique($channels))) {
            throw new InvalidArgumentException('Invalid notification preference input.');
        }
    }
}
