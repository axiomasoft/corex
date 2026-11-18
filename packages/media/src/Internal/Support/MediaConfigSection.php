<?php

declare(strict_types=1);

namespace CoreX\Media\Internal\Support;

use Packman\Attributes\Internal;
use Packman\Config\Contracts\ConfigReportSectionInterface as ConfigReportSection;

/** @internal Configuration report composition. */
#[Internal]
final class MediaConfigSection implements ConfigReportSection
{
    public function order(): int
    {
        return 30;
    }

    public function options(): array
    {
        return [MediaOptions::class => ['corex-media']];
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
