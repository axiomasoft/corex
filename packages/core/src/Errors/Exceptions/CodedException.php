<?php

declare(strict_types=1);

namespace CoreX\Errors\Exceptions;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use RuntimeException;

/** Implementations describe failures with safe data, independently of their diagnostic message. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
abstract class CodedException extends RuntimeException implements DescribesError
{
    abstract public function errorDescriptor(): ErrorDescriptor;
}
