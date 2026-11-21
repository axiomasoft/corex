<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Internal;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Errors\Exceptions\SearchWitnessChanged;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Projection\Data\RecordRefs;
use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\SearchHit;
use CoreX\Search\Query\Data\SearchQuery;
use Packman\Attributes\Internal;

#[Internal]
final readonly class AuthorizedHydrator
{
    public function __construct(
        private SearchSource $source,
        private SearchProjectionContributor $projection,
        private DataScopeAuthorizer $authorization,
    ) {}

    /** @param list<Candidate> $candidates */
    public function hydrate(ProjectionContext $context, SearchPlan $plan, SearchQuery $query, array $candidates): LoadedHits
    {
        $allowed = [];
        foreach ($candidates as $candidate) {
            $ref = $candidate->record;
            foreach ($plan->operations as $request) {
                if (! $this->authorization->authorize($context->scope, $ref, $request->fields, $request->use)->allowed) {
                    continue 2;
                }
            }
            $allowed[$ref->id] = $candidate;
        }

        if ($allowed === []) {
            return new LoadedHits([], [], []);
        }

        $rows = [];
        foreach ($this->source->load($context->scope, new RecordRefs(array_column($allowed, 'record')))->records as $row) {
            $id = $row->record->id;

            if (! isset($allowed[$id]) || $allowed[$id]->record->entity !== $row->record->entity || isset($rows[$id])) {
                throw SearchWitnessChanged::changed();
            }
            $rows[$id] = $row;
        }

        $hits = [];
        $revisions = [];
        $confirmed = [];
        $textMapping = [];
        foreach ($query->searchFields as $field) {
            $textMapping[$field] = $plan->fields[$field]->physicalId ?? $field;
        }
        $textFields = new ProjectionSelection(array_values($textMapping));
        foreach ($allowed as $id => $candidate) {
            $ref = $candidate->record;
            $row = $rows[$id] ?? null;

            if ($row === null) {
                continue;
            }

            $read = [];
            foreach ($query->fields as $field) {
                $underlying = $plan->fields[$field]->authorizationField;

                if ($plan->mayRead($field, $ref->id)
                    && $this->authorization->authorize($context->scope, $ref, new FieldSelection([$field]), FieldUse::Read)->allowed
                    && ($underlying === null || $this->authorization->authorize($context->scope, $ref, new FieldSelection([$underlying]), FieldUse::Read)->allowed)) {
                    $read[] = $field;
                }
            }
            $selected = array_values(array_unique([...$read, ...$query->searchFields, ...array_column($plan->filters, 'field')]));
            $values = array_intersect_key($row->values->values, array_flip($selected));

            if ($selected !== []) {
                $part = $this->projection->project($context, $ref, new ProjectionSelection($selected));
                foreach ($part->values as $field => $value) {
                    if (! in_array($field, $selected, true) || (array_key_exists($field, $values) && $values[$field] !== $value)) {
                        throw SearchWitnessChanged::changed();
                    }
                    $values[$field] = $value;
                }
            }

            foreach ($values as $field => $value) {
                if (! $plan->fields[$field]->type->accepts($value)) {
                    throw SearchWitnessChanged::changed();
                }
            }

            if ($query->text !== null) {
                $physicalText = [];
                foreach ($textMapping as $field => $physical) {
                    if (array_key_exists($field, $values)) {
                        $physicalText[$physical] = $values[$field];
                    }
                }

                if ($candidate->textWitnesses?->matches(new ProjectedValues($physicalText), $textFields) !== true) {
                    continue;
                }
            }
            foreach ($plan->filters as $filter) {
                if (! array_key_exists($filter->field, $values) || ! in_array($values[$filter->field], $filter->values, true)) {
                    continue 2;
                }
            }
            $confirmed[] = $candidate;
            $hits[] = new SearchHit($ref, new ProjectedValues(array_intersect_key($values, array_flip($read))));
            $revisions[$ref->id] = $row->revision;
        }

        return new LoadedHits($hits, $revisions, $confirmed);
    }
}
