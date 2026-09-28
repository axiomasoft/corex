<?php

declare(strict_types=1);

namespace CoreX\Auth\Support;

use CoreX\Contracts\ConfigReportSection;

/** @internal Configuration report composition. */
final class AccessConfigSection implements ConfigReportSection
{
    public function order(): int
    {
        return 20;
    }

    public function options(): array
    {
        return [AuthOptions::class => ['corex-auth']];
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
