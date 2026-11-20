<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Support;

use CoreX\Context\Contracts\NamedWorkPrincipal;
use CoreX\Messaging\Enums\PubSubDriver;
use CoreX\Scheduling\Contracts\DueWakeupSweeper;
use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Contracts\AccountResources;
use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks;
use CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Contracts\ConfigReportSectionInterface as ConfigReportSection;
use Tenant\Contracts\Runtime\TenantRuntime;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TenancyConfigSection implements ConfigReportSection
{
    public function order(): int
    {
        return 10;
    }

    public function options(): array
    {
        return [TenancyOptions::class => ['corex-tenancy']];
    }

    public function secretKeys(): array
    {
        return ['corex-tenancy.oidc.client_secret'];
    }

    public function secretDefaults(): array
    {
        return [];
    }

    public function drivers(): array
    {
        $driver = config('corex-tenancy.wakeup.driver');
        $enabled = config('corex-tenancy.oidc.enabled');

        return ['driver corex-tenancy.wakeup.driver='.$this->scalar($driver), 'driver corex-tenancy.oidc.enabled='.$this->scalar($enabled)];
    }

    public function adapters(): array
    {
        $selected = config('corex-tenancy.wakeup.driver') === 'root_registry';
        $missing = array_filter([DueWakeupSweeper::class, NamedWorkPrincipal::class], static fn (string $contract): bool => ! app()->bound($contract));
        $available = $missing === [];
        $adapters = [[
            'line' => 'adapter wakeup.root_registry='.($selected ? ($available ? 'available' : 'missing') : 'not-selected'),
            'error' => $selected && ! $available ? 'adapter wakeup.root_registry missing '.implode(', ', $missing) : null,
        ]];

        foreach ([TenantRuntime::class, AccountIsolationMapper::class, AccountResources::class, TemplateIntegrityChecks::class, TemplateIntegrityVerifier::class] as $contract) {
            $bound = app()->bound($contract);
            $adapters[] = ['line' => 'adapter '.$contract.'='.($bound ? 'available' : 'missing'), 'error' => $bound ? null : 'Required tenancy host adapter '.$contract.' is missing'];
        }

        return $adapters;
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
