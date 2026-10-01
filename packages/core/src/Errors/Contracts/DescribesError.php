<?php

declare(strict_types=1);

namespace CoreX\Errors\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Data\ErrorDescriptor;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DescribesError
{
    public function errorDescriptor(): ErrorDescriptor;
}
