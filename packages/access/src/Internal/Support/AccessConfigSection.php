<?php

declare(strict_types=1);

namespace CoreX\Access\Internal\Support;

use CoreX\Access\Laravel\AuthOptions;
use CoreX\Config\Contracts\ConfigReportSection;

/** @internal Configuration report composition. */
final class AccessConfigSection implements ConfigReportSection
{
    public function order(): int
    {
        return 20;
    }

    public function options(): array
    {
        return [AuthOptions::class => ['corex-access']];
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
        return [];
    }

    public function adapters(): array
    {
        return [];
    }
}
