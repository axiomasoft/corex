<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Engine;

use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Internal\InvalidAttributeValue;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use DateTimeImmutable;
use Exception;

final readonly class FieldValueValidator
{
    public function __construct(private FieldCatalogue $catalogue) {}

    public function validateSelection(DataScope $scope, RecordRef $record, FieldSelection $selection): void
    {
        $fields = $this->fields(scope: $scope, record: $record);

        foreach ($selection->fields as $field) {
            if (! isset($fields[$field])) {
                throw new InvalidAttributeValue("Attribute field [{$field}] is not active for this entity.");
            }
        }
    }

    public function validatePatch(DataScope $scope, RecordRef $record, ValuePatch $patch): void
    {
        $fields = $this->fields(scope: $scope, record: $record);

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

            if (! $this->matches($field['type'], $value)) {
                throw new InvalidAttributeValue("Attribute field [{$code}] does not contain a valid {$field['type']} value.");
            }
        }
    }

    /** @return array<string, array{type: string, required: bool}> */
    private function fields(DataScope $scope, RecordRef $record): array
    {
        $snapshot = $this->catalogue->snapshot(scope: $scope, entity: $record->entity);
        $fields = [];

        foreach ($snapshot->activeFields as $field) {
            if (! is_array($field) || ! is_string($field['code'] ?? null) || ! is_string($field['type'] ?? null)) {
                throw new InvalidAttributeValue('The field catalogue returned an invalid active-field descriptor.');
            }

            $fields[$field['code']] = ['type' => $field['type'], 'required' => (bool) ($field['required'] ?? false)];
        }

        return $fields;
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
