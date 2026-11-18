<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Internal;

use Packman\Attributes\Internal;
use Packman\Config\Contracts\ConfigReportSectionInterface as ConfigReportSection;

/** @internal Configuration report composition. */
#[Internal]
final class MeilisearchConfigSection implements ConfigReportSection
{
    public function order(): int
    {
        return 60;
    }

    public function options(): array
    {
        return [];
    }

    public function secretKeys(): array
    {
        return ['corex-search-meilisearch.key'];
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
