<?php

declare(strict_types=1);

namespace CoreX\Operations\Versioning\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum ApiVersionStatus: string
{
    case Stable = 'stable';
    case Deprecated = 'deprecated';
    case Retired = 'retired';
}
