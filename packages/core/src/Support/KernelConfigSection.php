<?php

declare(strict_types=1);

namespace CoreX\Support;

use CoreX\Contracts\ConfigReportSection;
use CoreX\Enums\PubSubDriver;
use CoreX\Messaging\RedisPubSub;

/** @internal Configuration report composition. */
final class KernelConfigSection implements ConfigReportSection
{
    public function order(): int
    {
        return 0;
    }

    public function options(): array
    {
        return [CoreOptions::class => ['corex']];
    }

    public function secretKeys(): array
    {
        return [];
    }

    public function secretDefaults(): array
    {
        return [];
    }

    public function drivers(): array
    {
        $driver = config('corex.pubsub.driver');

        return ['driver pubsub.driver='.$this->scalar($driver)];
    }

    public function adapters(): array
    {
        $driver = config('corex.pubsub.driver');
        $selected = $driver === PubSubDriver::Redis || $driver === 'redis';
        $available = class_exists(RedisPubSub::class);

        return [['line' => 'adapter pubsub.redis='.($selected ? ($available ? 'available' : 'missing') : 'not-selected'), 'error' => $selected && ! $available ? 'adapter pubsub.redis missing' : null]];
    }

    private function scalar(mixed $value): string
    {
        if ($value instanceof PubSubDriver) {
            return $value->value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_string($value) || is_int($value) ? (string) $value : 'absent';
    }
}
