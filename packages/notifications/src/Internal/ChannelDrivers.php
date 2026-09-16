<?php

declare(strict_types=1);

namespace CoreX\Notifications\Internal;

use CoreX\Notifications\Contracts\NotificationChannelDriver;

final class ChannelDrivers
{
    /** @var array<string, NotificationChannelDriver> */
    private array $drivers = [];

    /** @param iterable<NotificationChannelDriver> $drivers */
    public function __construct(iterable $drivers)
    {
        foreach ($drivers as $driver) {
            $key = $driver->descriptor()->key;

            if ($key === '' || isset($this->drivers[$key])) {
                throw new NotificationOperationConflict("Notification channel [{$key}] is not uniquely registered.");
            }

            $this->drivers[$key] = $driver;
        }
    }

    public function find(string $channel): ?NotificationChannelDriver
    {
        return $this->drivers[$channel] ?? null;
    }
}
