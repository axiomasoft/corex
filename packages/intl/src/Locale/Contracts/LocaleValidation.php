<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface LocaleValidation
{
    public function validate(string $tag): string;
}
