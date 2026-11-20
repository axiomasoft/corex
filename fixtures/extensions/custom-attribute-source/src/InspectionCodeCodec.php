<?php

declare(strict_types=1);

namespace ThirdParty\Attributes;

use CoreX\CustomFields\Types\Contracts\FieldCodec;
use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use InvalidArgumentException;

final class InspectionCodeCodec implements FieldCodec
{
    private const string Prefix = 'inspection:';

    public static function canonical(mixed $value): string
    {
        if (! is_string($value) || preg_match('/\A[A-Z]-(?!0{1,6}\z)[0-9]{1,6}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('An inspection code must have a letter and a positive number.');
        }

        return $value;
    }

    public function encode(FieldValue $value): JsonDocument
    {
        $decoded = JsonDocuments::decode($value->document);

        return JsonDocuments::capture($decoded === null ? null : self::Prefix.self::canonical($decoded));
    }

    public function decode(JsonDocument $document): FieldValue
    {
        $encoded = JsonDocuments::decode($document);

        if ($encoded === null) {
            return FieldValue::from(null);
        }

        if (! is_string($encoded) || ! str_starts_with($encoded, self::Prefix)) {
            throw new InvalidArgumentException('Unknown inspection-code representation.');
        }

        return FieldValue::from(self::canonical(substr($encoded, strlen(self::Prefix))));
    }
}
