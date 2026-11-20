<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Tests\Support;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Types\Contracts\FieldCodec;
use CoreX\CustomFields\Types\Contracts\FieldType;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldOperatorSet;
use CoreX\CustomFields\Types\Data\FieldTypeDescriptor;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Enums\FieldUse;

final readonly class ExtensionType implements FieldCodec, FieldType
{
    public function __construct(private string $name = 'test.serial', private string $codec = 'test.wire', private int $version = 1) {}

    public function id(): FieldTypeId
    {
        return new FieldTypeId($this->name);
    }

    public function definition(): FieldTypeDescriptor
    {
        return new FieldTypeDescriptor($this->codec, $this->version, $this, [FieldUse::Read, FieldUse::Write, FieldUse::Filter]);
    }

    public function normalize(FieldInput $input, FieldDefinition $field): FieldValue
    {
        $value = json_decode($input->document->canonicalJson, flags: JSON_THROW_ON_ERROR);

        return FieldValue::from($value === null ? null : strtoupper(trim($value)));
    }

    public function operators(FieldDefinition $field): FieldOperatorSet
    {
        return new FieldOperatorSet([FieldOperator::Eq]);
    }

    public function encode(FieldValue $value): JsonDocument
    {
        $text = json_decode($value->document->canonicalJson, flags: JSON_THROW_ON_ERROR);

        return new JsonDocument(json_encode($text === null ? null : 'wire:'.$text, JSON_THROW_ON_ERROR));
    }

    public function decode(JsonDocument $document): FieldValue
    {
        $text = json_decode($document->canonicalJson, flags: JSON_THROW_ON_ERROR);

        return FieldValue::from($text === null ? null : substr($text, 5));
    }
}
