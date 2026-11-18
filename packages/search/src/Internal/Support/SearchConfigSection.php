<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Support;

use Packman\Attributes\Internal;
use Packman\Config\Contracts\ConfigReportSectionInterface as ConfigReportSection;

/** @internal Configuration report composition. */
#[Internal]
final class SearchConfigSection implements ConfigReportSection
{
    public function order(): int
    {
        return 50;
    }

    public function options(): array
    {
        return [];
    }

    public function secretKeys(): array
    {
        return ['corex-search.cursor_secret'];
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
