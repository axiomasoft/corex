<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Internal;

use CoreX\Data\Data\RecordRef;
use CoreX\Search\Errors\Exceptions\SearchWitnessChanged;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Projection\Data\ProjectionSelection;
use Packman\Attributes\Internal;

#[Internal]
final readonly class CompositeProjection implements SearchProjectionContributor
{
    /** @param iterable<SearchProjectionContributor> $contributors */
    public function __construct(private iterable $contributors) {}

    public function describe(ProjectionContext $context): ProjectionFields
    {
        $fields = [];
        $revisions = [];
        foreach ($this->contributors as $contributor) {
            $schema = $contributor->describe($context);
            $revisions[] = $schema->revision;
            array_push($fields, ...$schema->fields);
        }

        usort($fields, static fn ($a, $b): int => strcmp($a->id, $b->id));
        sort($revisions, SORT_STRING);
        $mapping = [];
        foreach ($fields as $field) {
            $uses = array_column($field->uses, 'value');
            sort($uses, SORT_STRING);
            $mapping[] = [$field->id, $field->type->value, $uses];
        }

        return new ProjectionFields($fields, hash('sha256', json_encode([1, $context->entity->value, $revisions, $mapping], JSON_THROW_ON_ERROR)));
    }

    public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
    {
        $values = [];
        foreach ($this->contributors as $contributor) {
            $owned = array_column($contributor->describe($context)->fields, 'id');
            $fields = array_values(array_intersect($selection->fields, $owned));

            if ($fields === []) {
                continue;
            }
            $part = $contributor->project($context, $record, new ProjectionSelection($fields));

            if (array_diff(array_keys($part->values), $fields) !== [] || array_intersect_key($part->values, $values) !== []) {
                throw SearchWitnessChanged::changed();
            }
            $values += $part->values;
        }

        return new ProjectedValues($values);
    }
}
