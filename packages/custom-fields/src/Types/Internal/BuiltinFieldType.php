<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Internal;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Types\Contracts\FieldType;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldJson;
use CoreX\CustomFields\Types\Data\FieldOperatorSet;
use CoreX\CustomFields\Types\Data\FieldTypeDescriptor;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final readonly class BuiltinFieldType implements FieldType
{
    public const array IDS = ['text', 'int', 'decimal', 'bool', 'date', 'datetime', 'select', 'multiselect', 'reference', 'file', 'money', 'url', 'email', 'phone', 'json'];

    public function __construct(private FieldTypeId $type) {}

    public function id(): FieldTypeId
    {
        return $this->type;
    }

    public function definition(): FieldTypeDescriptor
    {
        $uses = [FieldUse::Read, FieldUse::Write, FieldUse::Filter, FieldUse::Export];

        if ($this->type->value === 'text') {
            $uses[] = FieldUse::Search;
        }

        if (in_array($this->type->value, ['int', 'decimal', 'money', 'date', 'datetime', 'text', 'email', 'phone', 'url', 'select'], true)) {
            $uses[] = FieldUse::Sort;
        }

        if (! in_array($this->type->value, ['json', 'multiselect', 'file'], true)) {
            $uses[] = FieldUse::Aggregate;
        }

        return new FieldTypeDescriptor($this->type->value, 1, new JsonFieldCodec, $uses, multiple: $this->type->value === 'multiselect');
    }

    public function operators(FieldDefinition $field): FieldOperatorSet
    {
        if ($field->localizationPolicy !== 'none') {
            return new FieldOperatorSet([FieldOperator::IsNull, FieldOperator::IsAbsent]);
        }
        $ordered = [FieldOperator::Lt, FieldOperator::Lte, FieldOperator::Gt, FieldOperator::Gte, FieldOperator::Between];
        $ops = match ($this->type->value) {
            'json' => [],
            'multiselect' => [FieldOperator::Any, FieldOperator::All],
            default => [FieldOperator::Eq, FieldOperator::In],
        };

        if (in_array($this->type->value, ['int', 'decimal', 'date', 'datetime'], true)
            || ($this->type->value === 'money' && isset($field->constraints['currency']))) {
            array_push($ops, ...$ordered);
        }

        if ($this->type->value === 'text' && in_array(FieldUse::Search, $field->uses, true)) {
            array_push($ops, FieldOperator::Contains, FieldOperator::FullText);
        }

        return new FieldOperatorSet([...$ops, FieldOperator::IsNull, FieldOperator::IsAbsent]);
    }

    public function normalize(FieldInput $input, FieldDefinition $field): FieldValue
    {
        $value = json_decode($input->document->canonicalJson, flags: JSON_THROW_ON_ERROR);

        if ($value === null) {
            if (! $field->nullable) {
                throw new InvalidArgumentException('Field requires a value.');
            }

            return FieldValue::from(null);
        }

        if ($field->localizationPolicy !== 'none') {
            if (! $value instanceof stdClass) {
                throw new InvalidArgumentException('Expected localized values.');
            }
            $translations = get_object_vars($value);
            foreach (array_keys($translations) as $locale) {
                if (preg_match('/\A[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*\z/D', $locale) !== 1) {
                    throw new InvalidArgumentException('Invalid field locale.');
                }
            }

            if ($this->type->value === 'json') {
                return new FieldValue($input->document);
            }
            $map = array_map(fn (mixed $translation): mixed => $this->scalar($translation, $field), $translations);

            return new FieldValue(new JsonDocument(FieldJson::encode((object) $map)));
        }

        // Retain the original JSON number lexemes, including arbitrary-precision JSON numbers.
        if ($this->type->value === 'json') {
            return new FieldValue($input->document);
        }

        return new FieldValue(new JsonDocument(FieldJson::encode($this->scalar($value, $field))));
    }

    /** @return null|bool|int|float|string|array<array-key,mixed>|stdClass */
    private function scalar(mixed $value, FieldDefinition $field): null|bool|int|float|string|array|stdClass
    {
        $limits = $field->constraints;

        return match ($this->type->value) {
            'text', 'url', 'email', 'phone', 'date', 'datetime', 'select' => $this->text($value, $limits),
            'decimal' => $this->decimal($this->string($value), $limits),
            'int' => is_int($value) && $value >= ($limits['min'] ?? PHP_INT_MIN) && $value <= ($limits['max'] ?? PHP_INT_MAX)
                ? $value : throw new InvalidArgumentException('Invalid field integer.'),
            'bool' => is_bool($value) ? $value : throw new InvalidArgumentException('Invalid field boolean.'),
            'money' => $this->money($value, $limits),
            'reference', 'file' => $this->reference($value, $limits),
            'multiselect' => $this->choices($value, $limits),
            'json' => is_scalar($value) || $value === null || is_array($value) || $value instanceof stdClass ? $value : throw new InvalidArgumentException('Invalid JSON value.'),
            default => throw new InvalidArgumentException('Unknown field type.'),
        };
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : throw new InvalidArgumentException('Expected a field string.');
    }

    /** @param array<string,mixed> $limits */
    private function text(mixed $input, array $limits): string
    {
        $value = $this->string($input);
        $size = mb_strlen($value, 'UTF-8');
        $valid = match ($this->type->value) {
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'phone' => preg_match('/\A\+[1-9][0-9]{5,14}\z/D', $value) === 1,
            'date' => $this->validDay($value),
            'datetime' => preg_match('/\A\d{4}-\d\d-\d\dT(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/D', $value) === 1 && $this->validDay(substr($value, 0, 10)),
            'select' => $this->choice($value, $limits),
            default => true,
        };

        if (! $valid || $size < ($limits['minLength'] ?? 0) || $size > ($limits['maxLength'] ?? PHP_INT_MAX)) {
            throw new InvalidArgumentException('Invalid field string.');
        }

        return $this->type->value === 'datetime' ? (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z') : $value;
    }

    /** @param array<string,mixed> $limits
     * @return array{amount:string,currency:string} */
    private function money(mixed $value, array $limits): array
    {
        if (! $value instanceof stdClass || count(get_object_vars($value)) !== 2) {
            throw new InvalidArgumentException('Invalid money.');
        }
        $currency = $this->string($value->currency ?? null);

        if (preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1 || (isset($limits['currency']) && $currency !== $limits['currency'])) {
            throw new InvalidArgumentException('Invalid currency.');
        }

        return ['amount' => $this->decimal($this->string($value->amount ?? null), $limits), 'currency' => $currency];
    }

    /** @param array<string,mixed> $limits
     * @return array{entity:string,id:string} */
    private function reference(mixed $value, array $limits): array
    {
        if (! $value instanceof stdClass || count(get_object_vars($value)) !== 2) {
            throw new InvalidArgumentException('Invalid reference.');
        }
        $ref = new RecordRef($this->string($value->entity ?? null), $this->string($value->id ?? null));

        if (isset($limits['target']) && $ref->entity !== $limits['target']) {
            throw new InvalidArgumentException('Invalid reference target.');
        }

        return ['entity' => $ref->entity, 'id' => $ref->id];
    }

    /** @param array<string,mixed> $limits
     * @return list<string> */
    private function choices(mixed $value, array $limits): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) < ($limits['minItems'] ?? 0) || count($value) > ($limits['maxItems'] ?? PHP_INT_MAX)) {
            throw new InvalidArgumentException('Invalid field cardinality.');
        }
        $choices = [];
        foreach ($value as $item) {
            $choice = $this->string($item);

            if (! $this->choice($choice, $limits) || in_array($choice, $choices, true)) {
                throw new InvalidArgumentException('Invalid field choice.');
            }
            $choices[] = $choice;
        }
        sort($choices, SORT_STRING);

        return $choices;
    }

    private function validDay(string $value): bool
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $day !== false && $day->format('Y-m-d') === $value;
    }

    /** @param array<string,mixed> $limits */
    private function choice(string $value, array $limits): bool
    {
        $choices = $limits['choices'] ?? $limits['enum'] ?? null;

        return $choices === null || (is_array($choices) && in_array($value, $choices, true));
    }

    /** @param array<string,mixed> $limits */
    private function decimal(string $value, array $limits): string
    {
        if (strlen($value) > 1024 || preg_match('/\A(-?)([0-9]+)(?:\.([0-9]+))?\z/D', $value, $parts) !== 1) {
            throw new InvalidArgumentException('Invalid exact decimal.');
        }
        $digits = ltrim($parts[2], '0');
        $whole = $digits === '' ? '0' : $digits;
        $fraction = $parts[3] ?? '';
        $scale = $limits['scale'] ?? null;

        if ($scale !== null) {
            if (! is_int($scale) || $scale < 0 || $scale > 1000 || strlen(rtrim($fraction, '0')) > $scale) {
                throw new InvalidArgumentException('Invalid decimal scale.');
            }
            $fraction = str_pad(substr($fraction, 0, $scale), $scale, '0');
        } else {
            $fraction = rtrim($fraction, '0');
        }

        if (isset($limits['precision']) && strlen(ltrim($whole, '0')) + strlen($fraction) > $limits['precision']) {
            throw new InvalidArgumentException('Decimal exceeds field precision.');
        }
        $decimal = ($whole !== '0' || trim($fraction, '0') !== '' ? $parts[1] : '').$whole.($fraction === '' ? '' : '.'.$fraction);
        foreach (['min', 'max'] as $bound) {
            if (isset($limits[$bound])) {
                $limit = $limits[$bound];

                if (! is_int($limit) && ! is_string($limit)) {
                    throw new InvalidArgumentException('Invalid decimal bound.');
                }
                $cmp = $this->compareDecimal($decimal, $this->decimal((string) $limit, []));

                if (($bound === 'min' && $cmp < 0) || ($bound === 'max' && $cmp > 0)) {
                    throw new InvalidArgumentException('Decimal exceeds field bounds.');
                }
            }
        }

        return $decimal;
    }

    private function compareDecimal(string $a, string $b): int
    {
        $negative = str_starts_with($a, '-');

        if ($negative !== str_starts_with($b, '-')) {
            return $negative ? -1 : 1;
        }
        [$ai, $af] = array_pad(explode('.', ltrim($a, '-'), 2), 2, '');
        [$bi, $bf] = array_pad(explode('.', ltrim($b, '-'), 2), 2, '');
        $width = max(strlen($af), strlen($bf));
        $cmp = strlen($ai) <=> strlen($bi);

        if ($cmp === 0) {
            $cmp = strcmp($ai, $bi);
        }

        if ($cmp === 0) {
            $cmp = strcmp(str_pad($af, $width, '0'), str_pad($bf, $width, '0'));
        }

        return $negative ? -$cmp : $cmp;
    }
}
