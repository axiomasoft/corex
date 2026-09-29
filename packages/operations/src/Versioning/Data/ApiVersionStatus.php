<?php

declare(strict_types=1);

namespace CoreX\Operations\Versioning\Data;

enum ApiVersionStatus: string
{
    case Stable = 'stable';
    case Deprecated = 'deprecated';
    case Retired = 'retired';
}
