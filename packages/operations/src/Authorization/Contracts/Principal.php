<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface Principal
{
    public function id(): string;
}
