<?php

declare(strict_types=1);

namespace CoreX\SearchCustomFields\Projection\Internal;

use CoreX\CustomFields\Query\Contracts\FieldFilter;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldProjectionCatalogue;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\CustomFields\Values\Data\RecordSelection;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Errors\Exceptions\SearchWitnessChanged;
use CoreX\Search\Projection\Contracts\ProjectionSnapshotCapabilities;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionField;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Projection\Enums\FieldType;
use Packman\Attributes\Internal;

#[Internal]
final readonly class FieldProjectionContributor implements ProjectionSnapshotCapabilities, SearchProjectionContributor
{
    public function __construct(private FieldProjectionCatalogue $catalogue, private FieldValues $values, private FieldCatalogue $visibleCatalogue, private FieldFilter $filters) {}

    public function supportsReadOnlySnapshot(): bool
    {
        // The effective schema head may require bootstrap or a shared lock.
        return false;
    }

    public function describe(ProjectionContext $context): ProjectionFields
    {
        $schema = $this->catalogue->snapshot($context->scope, $context->entity);
        $descriptors = [];
        $operatorWitness = [];
        foreach ($schema->fields as $id => $field) {
            $type = self::type($field->type->value);

            if ($field->localized || $type === null || ! in_array(FieldUse::Read, $field->uses, true)) {
                continue;
            }
            $operators = $field->operators;
            $filter = null;
            $uses = [];
            foreach ($field->uses as $use) {
                if ($use === FieldUse::Filter && $operators !== null) {
                    if (in_array(FieldOperator::Eq, $operators->operators, true) || in_array(FieldOperator::In, $operators->operators, true)) {
                        $filter = new FieldProjectionFilter($this->visibleCatalogue, $this->filters, $context, $schema, $field->id);
                        $uses[] = $use;
                    }

                    continue;
                }

                if (in_array($use, [FieldUse::Read, FieldUse::Search, FieldUse::Sort, FieldUse::Aggregate], true)
                    && ! ($use === FieldUse::Sort && $field->type->value === 'decimal')) {
                    $uses[] = $use;
                }
            }
            $descriptors[] = new ProjectionField($id, $type, $uses, filter: $filter, predicateRequiresRead: true);

            if ($filter !== null && $operators !== null) {
                $names = array_column($operators->operators, 'value');
                sort($names, SORT_STRING);
                $operatorWitness[$id] = $names;
            }
        }
        ksort($operatorWitness, SORT_STRING);

        return new ProjectionFields($descriptors, hash('sha256', json_encode([
            'corex.search.custom-fields.v1', $schema->revision->value, $schema->mappingDigest, $operatorWitness,
        ], JSON_THROW_ON_ERROR)));
    }

    public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
    {
        if ($record->entity !== $context->entity->value) {
            throw SearchWitnessChanged::changed();
        }

        $schema = $this->catalogue->snapshot($context->scope, $context->entity);
        $selected = [];
        foreach ($selection->fields as $id) {
            $field = $schema->fields[$id] ?? null;

            if ($field !== null && ! $field->localized && self::type($field->type->value) !== null && in_array(FieldUse::Read, $field->uses, true)) {
                $selected[] = $id;
            }
        }

        if ($selected === []) {
            return new ProjectedValues([]);
        }

        $snapshot = $this->values->readMany($context->scope, new RecordSelection([$record]), new FieldSelection($selected))->items[0] ?? null;

        if ($snapshot === null) {
            return new ProjectedValues([]);
        }

        if ($snapshot->record->entity !== $record->entity || $snapshot->record->id !== $record->id || $snapshot->schemaRevision->value !== $schema->revision->value) {
            throw SearchWitnessChanged::changed();
        }

        $values = [];
        foreach ($snapshot->values as $id => $value) {
            $decoded = json_decode($value->document->canonicalJson, flags: JSON_THROW_ON_ERROR);

            if (! in_array($id, $selected, true) || (! is_string($decoded) && ! is_bool($decoded) && ! is_int($decoded) && $decoded !== null)) {
                throw SearchWitnessChanged::changed();
            }
            $values[$id] = $decoded;
        }

        return new ProjectedValues($values);
    }

    private static function type(string $type): ?FieldType
    {
        return match ($type) {
            'text', 'decimal', 'date', 'datetime', 'select', 'url', 'email', 'phone' => FieldType::Text,
            'int' => FieldType::Integer,
            'bool' => FieldType::Boolean,
            default => null,
        };
    }
}
