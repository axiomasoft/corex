<?php

declare(strict_types=1);

namespace ThirdParty\Attributes;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Types\Contracts\FieldConverterProvider;
use CoreX\CustomFields\Types\Contracts\FieldValueConverter;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldValue;

final class InspectionCodeConverterProvider implements FieldConverterProvider, FieldValueConverter
{
    public const string Id = 'vendor.inspection-code';

    public function id(): string
    {
        return self::Id;
    }

    public function version(): int
    {
        return 1;
    }

    public function converter(): FieldValueConverter
    {
        return $this;
    }

    public function convert(FieldValue $value, FieldDefinition $from, FieldDefinition $to): FieldValue
    {
        return (new InspectionCodeType)->normalize(new FieldInput($value->document), $to);
    }
}
