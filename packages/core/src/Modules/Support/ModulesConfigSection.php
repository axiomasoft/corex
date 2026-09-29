<?php

declare(strict_types=1);

namespace CoreX\Modules\Support;

use CoreX\Config\Contracts\ConfigReportSection;

/** @internal Configuration report composition. */
final class ModulesConfigSection implements ConfigReportSection
{
    public function order(): int
    {
        return 25;
    }

    public function options(): array
    {
        return [ModulesOptions::class => ['corex-modules']];
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
