<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Values;

use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\FieldSelection;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\InvalidAttributeValue;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\DataScope;
use DateTimeImmutable;
use Exception;

final readonly class FieldValueValidator
{
    public function __construct(private FieldCatalogue $catalogue) {}

    public function validateSelection(DataScope $scope, RecordRef $record, FieldSelection $selection): void
    {
        $fields = $this->fields($this->catalogue->snapshot(scope: $scope, entity: $record->entity));

        foreach ($selection->fields as $field) {
            if (! isset($fields[$field])) {
                throw new InvalidAttributeValue("Attribute field [{$field}] is not active for this entity.");
            }
        }
    }

    public function validatePatch(DataScope $scope, RecordRef $record, ValuePatch $patch): void
    {
        $snapshot = $this->catalogue->snapshot(scope: $scope, entity: $record->entity);

        $this->validateTypes(snapshot: $snapshot, patch: $patch);
        $this->validateConstraints(snapshot: $snapshot, patch: $patch);
    }

    public function validateTypes(FieldSnapshot $snapshot, ValuePatch $patch): void
    {
        $fields = $this->fields($snapshot);

        foreach ($patch->values as $code => $value) {
            $field = $fields[$code] ?? null;

            if ($field === null) {
                throw new InvalidAttributeValue("Attribute field [{$code}] is not active for this entity.");
            }

            if ($value === null) {
                continue;
            }

            if (! $this->matches($field['type'], $value)) {
                throw new InvalidAttributeValue("Attribute field [{$code}] does not contain a valid {$field['type']} value.");
            }
        }
    }

    public function validateConstraints(FieldSnapshot $snapshot, ValuePatch $patch): void
    {
        $fields = $this->fields($snapshot);

        foreach ($patch->values as $code => $value) {
            $field = $fields[$code] ?? null;

            if ($field === null) {
                throw new InvalidAttributeValue("Attribute field [{$code}] is not active for this entity.");
            }

            if ($value === null) {
                if ($field['required']) {
                    throw new InvalidAttributeValue("Required attribute field [{$code}] cannot be null.");
                }

                continue;
            }

            $this->assertConstraints(code: (string) $code, field: $field, value: $value);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function fields(FieldSnapshot $snapshot): array
    {
        $fields = [];

        foreach ($snapshot->activeFields as $field) {
            $code = is_array($field) ? ($field['code'] ?? $field['key'] ?? null) : null;

            if (! is_array($field) || ! is_string($code) || ! is_string($field['type'] ?? null)) {
                throw new InvalidAttributeValue('The field catalogue returned an invalid active-field descriptor.');
            }

            $field['code'] = $code;
            $field['required'] = (bool) ($field['required'] ?? false);
            $field['constraints'] = is_array($field['constraints'] ?? null) ? $field['constraints'] : [];
            $fields[$code] = $field;
        }

        return $fields;
    }

    /** @param array<string, mixed> $field */
    private function assertConstraints(string $code, array $field, mixed $value): void
    {
        $constraints = $field['constraints'];

        foreach ($constraints as $constraint => $expected) {
            $valid = match ($constraint) {
                'enum' => $this->matchesEnum($value, $expected),
                'min', 'min_length' => $this->isBound($expected)
                    && $this->measure($value) >= $expected,
                'max', 'max_length' => $this->isBound($expected)
                    && $this->measure($value) <= $expected,
                'format' => is_string($expected) && $this->matchesFormat($expected, $value),
                'reference_prefix' => is_string($value) && is_string($expected)
                    && $expected !== '' && str_starts_with($value, $expected),
                default => false,
            };

            if (! $valid) {
                throw new InvalidAttributeValue("Attribute field [{$code}] violates its declared constraints.");
            }
        }
    }

    private function matchesEnum(mixed $value, mixed $expected): bool
    {
        if (! is_array($expected) || ! array_is_list($expected) || $expected === []) {
            return false;
        }

        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $item) {
                if (! in_array($item, $expected, true)) {
                    return false;
                }
            }

            return true;
        }

        return in_array($value, $expected, true);
    }

    private function measure(mixed $value): int|float
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/D', $value) === 1) {
            return (float) $value;
        }

        if (is_string($value)) {
            return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        }

        if (is_array($value)) {
            return count($value);
        }

        return INF;
    }

    private function isBound(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }

    private function matchesFormat(string $format, mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return match ($format) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'uuid' => preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/Di', $value) === 1,
            'date' => $this->isDate($value),
            'datetime' => $this->isDateTime($value),
            'phone' => preg_match('/\A\+?[0-9][0-9 ()-]{5,31}\z/D', $value) === 1,
            default => false,
        };
    }

    private function matches(string $type, mixed $value): bool
    {
        return match ($type) {
            'text' => is_string($value),
            'int' => is_int($value),
            'decimal' => is_string($value) && preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/D', $value) === 1,
            'bool' => is_bool($value),
            'date' => is_string($value) && $this->isDate($value),
            'datetime' => is_string($value) && $this->isDateTime($value),
            'select' => is_string($value),
            'multiselect' => is_array($value) && array_is_list($value) && $this->allStrings($value),
            'reference', 'file' => is_string($value) && $value !== '',
            'money' => is_array($value) && is_int($value['minor'] ?? null) && is_string($value['currency'] ?? null) && preg_match('/\A[A-Z]{3}\z/D', $value['currency']) === 1,
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'phone' => is_string($value) && preg_match('/\A\+?[0-9][0-9 ()-]{5,31}\z/D', $value) === 1,
            'json' => is_array($value),
            default => false,
        };
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** @param array<array-key, mixed> $values */
    private function allStrings(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                return false;
            }
        }

        return true;
    }

    private function isDateTime(string $value): bool
    {
        try {
            new DateTimeImmutable($value);
        } catch (Exception) {
            return false;
        }

        return str_contains($value, 'T');
    }
}
