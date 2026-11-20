<?php

declare(strict_types=1);

namespace ThirdParty\Attributes;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Types\Contracts\FieldType;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldOperatorSet;
use CoreX\CustomFields\Types\Data\FieldTypeDescriptor;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Laravel\JsonDocuments;

final class InspectionCodeType implements FieldType
{
    public const string Id = 'vendor.type';

    public function id(): FieldTypeId
    {
        return new FieldTypeId(self::Id);
    }

    public function definition(): FieldTypeDescriptor
    {
        return new FieldTypeDescriptor(
            codecId: 'vendor.type',
            codecVersion: 1,
            codec: new InspectionCodeCodec,
            uses: [FieldUse::Read, FieldUse::Write, FieldUse::Filter],
            conversions: [InspectionCodeConverterProvider::Id => 1],
        );
    }

    public function normalize(FieldInput $input, FieldDefinition $field): FieldValue
    {
        $value = JsonDocuments::decode($input->document);

        if ($value === null && $field->nullable) {
            return FieldValue::from(null);
        }

        return FieldValue::from(InspectionCodeCodec::canonical(is_string($value) ? strtoupper(trim($value)) : $value));
    }

    public function operators(FieldDefinition $field): FieldOperatorSet
    {
        return new FieldOperatorSet([FieldOperator::Eq, FieldOperator::IsNull, FieldOperator::IsAbsent]);
    }
}
