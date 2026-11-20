<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface FieldConverterProvider
{
    public function id(): string;

    public function version(): int;

    public function converter(): FieldValueConverter;
}
