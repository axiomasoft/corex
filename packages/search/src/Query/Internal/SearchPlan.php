<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Internal;

use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Enums\FieldUsePlanKind;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionField;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Projection\Enums\FieldType;
use CoreX\Search\Query\Data\DriverCapabilities;
use CoreX\Search\Query\Data\SearchFilter;
use CoreX\Search\Query\Data\SearchQuery;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SearchPlan
{
    /**
     * @param  array<string,ProjectionField>  $fields
     * @param  array<string,FieldUsePlan>  $grants
     * @param  list<FieldUseRequest>  $operations
     * @param  list<SearchFilter>  $filters
     * @param  ?list<string>  $recordIds
     */
    private function __construct(
        public string $mapping,
        public array $fields,
        public array $grants,
        public array $operations,
        public array $filters,
        public ?array $recordIds,
        public string $witness,
    ) {}

    public static function compile(ProjectionContext $context, SearchQuery $query, ProjectionFields $schema, FieldUsePolicy $policy): self
    {
        $fields = [];
        foreach ($schema->fields as $field) {
            $fields[$field->id] = $field;
        }
        $uses = [[FieldUse::Read, []]];
        $readsForPredicates = $readsForAggregates = [];
        foreach ([
            [FieldUse::Search, $query->searchFields],
            [FieldUse::Filter, array_column($query->filters, 'field')],
            [FieldUse::Sort, array_column($query->sort, 'field')],
            [FieldUse::Read, $query->fields],
            [FieldUse::Aggregate, $query->facets],
        ] as [$use, $ids]) {
            foreach (array_unique($ids) as $id) {
                $field = $fields[$id] ?? null;

                if ($field === null || ($use !== FieldUse::Read && ! in_array($use, $field->uses, true))
                    || ($use === FieldUse::Search && $field->type !== FieldType::Text)) {
                    throw SearchFeatureUnavailable::unsupported();
                }
                $uses[] = [$use, [$id]];

                if ($field->authorizationField !== null && $field->authorizationField !== $id) {
                    $uses[] = [$use, [$field->authorizationField]];
                }

                if ($field->predicateRequiresRead && $use !== FieldUse::Read) {
                    $read = array_unique([$id, $field->authorizationField ?? $id]);
                    foreach ($read as $source) {
                        $uses[] = [FieldUse::Read, [$source]];
                    }

                    if ($use === FieldUse::Aggregate) {
                        $readsForAggregates[$id] = $read;
                    } else {
                        $readsForPredicates += array_fill_keys($read, true);
                    }
                }
            }
        }

        if ($query->count) {
            $uses[] = [FieldUse::Aggregate, []];
        }
        $grants = [];
        $operations = [];
        $corpus = null;
        foreach ($uses as [$use, $ids]) {
            $request = new FieldUseRequest(fields: new FieldSelection($ids), use: $use);
            $field = $ids[0] ?? '';
            $key = $use->value.'|'.$field;

            if (isset($grants[$key])) {
                continue;
            }
            $grant = $policy->plan(scope: $context->scope, entity: $context->entity, request: $request);
            $grants[$key] = $grant;

            if ($use === FieldUse::Read && $ids !== []) {
                continue;
            }

            if ($grant->kind === FieldUsePlanKind::Denied) {
                throw SearchFeatureUnavailable::unsupported();
            }

            if ($use === FieldUse::Aggregate) {
                continue;
            }
            $operations[] = $request;
            $corpus = self::intersect(corpus: $corpus, grant: $grant);
        }
        foreach (array_keys($readsForPredicates) as $field) {
            self::requireRead(read: $grants['read|'.$field], corpus: $corpus);
            $operations[] = new FieldUseRequest(fields: new FieldSelection([$field]), use: FieldUse::Read);
        }
        foreach ($readsForAggregates as $fieldsToRead) {
            $aggregate = $corpus;
            foreach ($fieldsToRead as $field) {
                $aggregate = self::intersect(corpus: $aggregate, grant: $grants['aggregate|'.$field]);
            }
            foreach ($fieldsToRead as $field) {
                self::requireRead(read: $grants['read|'.$field], corpus: $aggregate);
            }
        }
        $filters = [];
        foreach ($query->filters as $filter) {
            $field = $fields[$filter->field];

            if (array_any($filter->values, static fn (string|int|bool $value): bool => ! $field->type->accepts($value))) {
                throw SearchFeatureUnavailable::unsupported();
            }
            $values = $field->filter?->normalize($filter->values, $grants['filter|'.$filter->field]) ?? $filter->values;

            if (array_any($values, static fn (string|int|bool $value): bool => ! $field->type->accepts($value))) {
                throw SearchFeatureUnavailable::unsupported();
            }
            $filters[] = new SearchFilter(field: $filter->field, values: $values);
        }

        return new self(mapping: $schema->revision, fields: $fields, grants: $grants, operations: $operations, filters: $filters, recordIds: $corpus,
            witness: hash('sha256', json_encode([$schema->revision, $grants], JSON_THROW_ON_ERROR)));
    }

    public function verifyLowering(DriverCapabilities $capabilities, SearchQuery $query): void
    {
        if (($query->text !== null && ! $capabilities->text) || ($query->filters !== [] && ! $capabilities->filter)
            || ($query->sort !== [] && ! $capabilities->sort) || ($this->recordIds !== null && ! $capabilities->scopedCorpus)) {
            throw SearchFeatureUnavailable::unsupported();
        }
    }

    public function mayRead(string $field, string $id): bool
    {
        $descriptor = $this->fields[$field];

        return in_array(FieldUse::Read, $descriptor->uses, true)
            && $this->mayReadField(field: $field, record: $id)
            && ($descriptor->authorizationField === null || $this->mayReadField(field: $descriptor->authorizationField, record: $id));
    }

    public function physicalField(string $field): string
    {
        return $this->fields[$field]->physicalId ?? $field;
    }

    /** @return ?list<string> */
    public function aggregateCorpus(string $field): ?array
    {
        $corpus = self::intersect(corpus: $this->recordIds, grant: $this->grants['aggregate|'.$field]);
        $authorizationField = $this->fields[$field]->authorizationField ?? null;

        return $authorizationField === null ? $corpus : self::intersect(corpus: $corpus, grant: $this->grants['aggregate|'.$authorizationField]);
    }

    private function mayReadField(string $field, string $record): bool
    {
        $grant = $this->grants['read|'.$field];

        return $grant->kind === FieldUsePlanKind::Unconditional ||
            ($grant->kind === FieldUsePlanKind::ScopedPredicate && in_array($record, $grant->recordIds, true));
    }

    /** @param ?list<string> $corpus */
    private static function requireRead(FieldUsePlan $read, ?array $corpus): void
    {
        if ($read->kind === FieldUsePlanKind::Denied ||
            ($read->kind === FieldUsePlanKind::ScopedPredicate && ($corpus === null || array_diff($corpus, $read->recordIds) !== []))) {
            throw SearchFeatureUnavailable::unsupported();
        }
    }

    /** @param ?list<string> $corpus
     * @return ?list<string> */
    private static function intersect(?array $corpus, FieldUsePlan $grant): ?array
    {
        if ($grant->kind === FieldUsePlanKind::ScopedPredicate) {
            $ids = array_values(array_unique($grant->recordIds));
            sort($ids, SORT_STRING);

            return $corpus === null ? $ids : array_values(array_intersect($corpus, $ids));
        }

        return $corpus;
    }
}
