<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Support;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Messaging\Enums\PubSubDriver;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TenancyConfigSection implements ConfigReportSection
{
    public function order(): int
    {
        return 10;
    }

    public function options(): array
    {
        return [TenancyOptions::class => ['tenancy', 'corex-tenancy']];
    }

    public function secretKeys(): array
    {
        return ['tenancy.oidc.client_secret'];
    }

    public function secretDefaults(): array
    {
        return [];
    }

    public function drivers(): array
    {
        $driver = config('corex-tenancy.wakeup.driver');
        $enabled = config('tenancy.oidc.enabled');

        return ['driver tenancy.wakeup.driver='.$this->scalar($driver), 'driver tenancy.oidc.enabled='.$this->scalar($enabled)];
    }

    public function adapters(): array
    {
        $selected = config('corex-tenancy.wakeup.driver') === 'root_registry';
        $available = class_exists('CoreX\\Wakeup\\WakeupServiceProvider');

        return [['line' => 'adapter wakeup.root_registry='.($selected ? ($available ? 'available' : 'missing') : 'not-selected'), 'error' => $selected && ! $available ? 'adapter wakeup.root_registry missing' : null]];
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
