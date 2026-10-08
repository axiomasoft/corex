<?php

declare(strict_types=1);

namespace CoreX\Database\Enums;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
enum SchemaLifecycle: string
{
    case Platform = 'platform';
    case Module = 'module';
}
