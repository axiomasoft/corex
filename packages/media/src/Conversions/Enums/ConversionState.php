<?php

declare(strict_types=1);

namespace CoreX\Media\Conversions\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum ConversionState: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
