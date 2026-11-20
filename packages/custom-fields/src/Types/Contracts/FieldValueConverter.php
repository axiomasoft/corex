<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Contracts;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Types\Data\FieldValue;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface FieldValueConverter
{
    public function convert(FieldValue $value, FieldDefinition $from, FieldDefinition $to): FieldValue;
}
