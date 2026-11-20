<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Contracts;

use CoreX\CustomFields\Types\Data\FieldTypeId;
use Packman\Attributes\Internal;

#[Internal]
interface FieldTypes
{
    public function resolve(FieldTypeId $type, int $version): FieldType;

    public function converter(string $id, int $version): FieldValueConverter;
}
