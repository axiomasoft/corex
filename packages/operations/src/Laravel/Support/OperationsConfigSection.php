<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel\Support;

use Packman\Attributes\Internal;
use Packman\Config\Contracts\ConfigReportSectionInterface;

#[Internal]
final class OperationsConfigSection implements ConfigReportSectionInterface
{
    public function order(): int
    {
        return 20;
    }

    public function options(): array
    {
        return [ApiOptions::class => ['corex-operations']];
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
        return config('corex-operations.require_physical_tenancy') === false
            ? ['NOTICE [opt-out] corex-operations.require_physical_tenancy=false']
            : [];
    }

    public function adapters(): array
    {
        return [];
    }
}
