<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Internal;

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Types\Contracts\FieldConversions;
use CoreX\CustomFields\Types\Contracts\FieldConverterProvider;
use CoreX\CustomFields\Types\Contracts\FieldType;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\CustomFields\Types\Contracts\FieldValueConverter;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\Data\Data\JsonDocument;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class RegisteredFieldTypes implements FieldConversions, FieldTypes
{
    /** @var array<string,FieldType> */
    private array $types;

    /** @var array<string,FieldValueConverter> */
    private array $converters;

    /**
     * @param  iterable<mixed>  $extensions
     * @param  iterable<mixed>  $providers
     */
    public function __construct(iterable $extensions, iterable $providers)
    {
        $types = [];
        $codecs = [];
        foreach (BuiltinFieldType::IDS as $id) {
            $codecs[$id.'@1'] = true;
            $types[$id.'@1'] = new BuiltinFieldType(new FieldTypeId($id));
        }
        foreach ($extensions as $type) {
            if (! $type instanceof FieldType) {
                throw new InvalidArgumentException('Invalid tagged field type.');
            }
            $id = $type->id()->value;
            $descriptor = $type->definition();
            $key = $id.'@'.$descriptor->codecVersion;
            $codecKey = $descriptor->codecId.'@'.$descriptor->codecVersion;

            if (! str_contains($id, '.') || isset($types[$key])) {
                throw new InvalidArgumentException('Duplicate or unqualified field type.');
            }

            if (isset($codecs[$codecKey])) {
                throw new InvalidArgumentException('Duplicate field codec identity.');
            }
            $codecs[$codecKey] = true;
            $types[$key] = $type;
        }
        $this->types = $types;
        $converters = [];
        foreach ($providers as $provider) {
            if (! $provider instanceof FieldConverterProvider) {
                throw new InvalidArgumentException('Invalid tagged field converter.');
            }
            $key = $provider->id().'@'.$provider->version();

            if ($provider->id() === '' || $provider->version() < 1 || isset($converters[$key])) {
                throw new InvalidArgumentException('Invalid or duplicate field converter.');
            }
            $converters[$key] = $provider->converter();
        }
        $this->converters = $converters;
    }

    public function resolve(FieldTypeId $type, int $version): FieldType
    {
        return $this->types[$type->value.'@'.$version] ?? throw new FieldFailure(CustomFieldsErrorCode::FieldTypeUnavailable);
    }

    public function converter(string $id, int $version): FieldValueConverter
    {
        return $this->converters[$id.'@'.$version] ?? throw new FieldFailure(CustomFieldsErrorCode::FieldTypeUnavailable);
    }

    public function convert(JsonDocument $document, FieldDefinition $from, FieldDefinition $to, ?string $converterId, ?int $converterVersion): JsonDocument
    {
        $source = $this->resolve($from->type, $from->codecVersion);
        $target = $this->resolve($to->type, $to->codecVersion);
        $value = $source->definition()->codec->decode($document);

        if ($converterId !== null && $converterVersion !== null) {
            $value = $this->converter($converterId, $converterVersion)->convert($value, $from, $to);
        } elseif ($from->type->value !== $to->type->value || $from->codecVersion !== $to->codecVersion) {
            throw new InvalidArgumentException('A versioned field converter is required.');
        }

        return $target->definition()->codec->encode($target->normalize(new FieldInput($value->document), $to));
    }
}
