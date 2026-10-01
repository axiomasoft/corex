<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Errors\Exceptions\ApiError;
use Throwable;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface ErrorMapping
{
    public function map(Throwable $error, bool $reportUnexpected = true): ApiError;
}
