<?php

declare(strict_types=1);

namespace CoreX\Operations\Pagination\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use InvalidArgumentException;

/** Immutable field, direction and type projection. The last key is the unique tie-breaker. */
final readonly class OrderingRegistry implements \CoreX\Operations\Pagination\Contracts\OrderingRegistry
{
    /**
     * @param  array<string, array{direction: string, fields: list<string>, types: list<string>}>  $orderings
     */
    private function __construct(private array $orderings) {}

    /** @param list<Operation> $operations */
    public static function fromOperations(array $operations): self
    {
        $orderings = [];

        foreach ($operations as $operation) {
            if (JsonDocuments::optionalMap($operation->pagination) === null) {
                continue;
            }

            $descriptor = self::descriptor($operation);
            $existing = $orderings[$descriptor['ordering']] ?? null;
            unset($descriptor['ordering']);

            if ($existing !== null && $existing !== $descriptor) {
                throw new InvalidArgumentException('Ordering version ['.JsonDocuments::optionalMap($operation->pagination)['ordering'].'] conflicts.');
            }

            $orderings[JsonDocuments::optionalMap($operation->pagination)['ordering']] = $descriptor;
        }

        ksort($orderings);

        return new self($orderings);
    }

    /**
     * @return array{direction: string, fields: list<string>, types: list<string>}|null
     */
    public function find(string $ordering): ?array
    {
        return $this->orderings[$ordering] ?? null;
    }

    public function canonicalBytes(): string
    {
        return JsonCanonical::encode($this->orderings);
    }

    /**
     * @return array{ordering: string, direction: string, fields: list<string>, types: list<string>}
     */
    private static function descriptor(Operation $operation): array
    {
        $pagination = JsonDocuments::optionalMap($operation->pagination) ?? [];
        $ordering = $pagination['ordering'] ?? null;
        $direction = $pagination['direction'] ?? null;
        $fields = $pagination['fields'] ?? null;
        $types = $pagination['types'] ?? null;
        $unique = $pagination['unique'] ?? null;

        if (! is_string($ordering) || $ordering === ''
            || ! in_array($direction, ['asc', 'desc'], true)
            || ! is_array($fields) || ! array_is_list($fields) || $fields === []
            || ! is_array($types) || ! array_is_list($types)
            || count($fields) !== count($types)
            || ! is_string($unique) || $unique === '' || $unique !== $fields[array_key_last($fields)]) {
            throw new InvalidArgumentException('Operation ['.$operation->id.'] ordering must end with a unique tie-breaker.');
        }

        $normalizedFields = [];
        $normalizedTypes = [];

        foreach ($fields as $index => $field) {
            $type = $types[$index];

            if (! is_string($field) || $field === '' || in_array($field, $normalizedFields, true)
                || ! in_array($type, ['string', 'int', 'float', 'bool'], true)) {
                throw new InvalidArgumentException('Operation ['.$operation->id.'] ordering fields are invalid.');
            }

            $normalizedFields[] = $field;
            $normalizedTypes[] = $type;
        }

        return [
            'ordering' => $ordering,
            'direction' => $direction,
            'fields' => $normalizedFields,
            'types' => $normalizedTypes,
        ];
    }
}
