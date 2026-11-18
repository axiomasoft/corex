<?php

declare(strict_types=1);

namespace CoreX\Testing;

use Packman\Config\Contracts\ConfigReportSectionInterface as ConfigReportSection;

final class ConfigReportTestSection implements ConfigReportSection
{
    public function order(): int
    {
        return 1;
    }

    public function options(): array
    {
        return [];
    }

    public function secretKeys(): array
    {
        return ['corex.test_secret', 'corex.test_cursor_secret', 'corex.test_default_secret'];
    }

    public function secretDefaults(): array
    {
        return ['corex.test_default_secret' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'];
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
