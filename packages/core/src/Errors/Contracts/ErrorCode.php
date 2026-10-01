<?php

declare(strict_types=1);

namespace CoreX\Errors\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ErrorCode
{
    public function value(): string;
}
