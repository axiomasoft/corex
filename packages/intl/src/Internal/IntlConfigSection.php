<?php

declare(strict_types=1);

namespace CoreX\Intl\Internal;

use CoreX\Config\Contracts\ConfigReportSection;

/** @internal Configuration report composition. */
final class IntlConfigSection implements ConfigReportSection
{
    public function order(): int
    {
        return 40;
    }

    public function options(): array
    {
        return [];
    }

    public function secretKeys(): array
    {
        return ['corex-intl.reference.cursor_secret'];
    }

    public function secretDefaults(): array
    {
        return ['corex-intl.reference.cursor_secret' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'];
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
