<?php

declare(strict_types=1);

namespace App\FieldSearch;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Types\Contracts\FieldConverterProvider;
use CoreX\CustomFields\Types\Contracts\FieldValueConverter;
use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\Data\Laravel\JsonDocuments;
use InvalidArgumentException;

final class IntegerConverter implements FieldConverterProvider, FieldValueConverter
{
    public function id(): string
    {
        return 'equipment.priority-integer';
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
        $text = JsonDocuments::decode($value->document);

        if (! is_string($text) || preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/D', $text) !== 1) {
            throw new InvalidArgumentException('The equipment source priority is outside its conversion contract.');
        }

        return FieldValue::from((int) $text);
    }
}
