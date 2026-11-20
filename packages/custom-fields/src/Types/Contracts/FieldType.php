<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Contracts;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldOperatorSet;
use CoreX\CustomFields\Types\Data\FieldTypeDescriptor;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\CustomFields\Types\Data\FieldValue;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface FieldType
{
    public function id(): FieldTypeId;

    public function definition(): FieldTypeDescriptor;

    public function normalize(FieldInput $input, FieldDefinition $field): FieldValue;

    public function operators(FieldDefinition $field): FieldOperatorSet;
}
