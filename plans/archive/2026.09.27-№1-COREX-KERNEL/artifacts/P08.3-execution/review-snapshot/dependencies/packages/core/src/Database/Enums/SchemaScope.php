<?php

declare(strict_types=1);

namespace CoreX\Database\Enums;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
enum SchemaScope: string
{
    case Central = 'central';
    case Tenant = 'tenant';
    case Dedicated = 'dedicated';
}
