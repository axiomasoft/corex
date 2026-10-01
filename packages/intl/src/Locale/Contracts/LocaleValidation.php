<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface LocaleValidation
{
    public function validate(string $tag): string;
}
