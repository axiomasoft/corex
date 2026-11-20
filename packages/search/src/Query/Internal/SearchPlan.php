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
use CoreX\Search\Query\Data\SearchQuery;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SearchPlan
{
    /**
     * @param  array<string,ProjectionField>  $fields
     * @param  array<string,FieldUsePlan>  $grants
     * @param  list<FieldUseRequest>  $operations
     * @param  ?list<string>  $recordIds
     */
    private function __construct(
        public string $mapping,
        public array $fields,
        public array $grants,
        public array $operations,
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
            }
        }

        if ($query->count) {
            $uses[] = [FieldUse::Aggregate, []];
        }
        foreach ($query->filters as $filter) {
            foreach ($filter->values as $value) {
                if (! $fields[$filter->field]->type->accepts($value)) {
                    throw SearchFeatureUnavailable::unsupported();
                }
            }
        }

        $grants = [];
        $operations = [];
        $corpus = null;
        foreach ($uses as [$use, $ids]) {
            $request = new FieldUseRequest(new FieldSelection($ids), $use);
            $field = $ids[0] ?? '';
            $grant = $policy->plan($context->scope, $context->entity, $request);
            $grants[$use->value.'|'.$field] = $grant;

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
            $corpus = self::intersect($corpus, $grant);
        }

        return new self($schema->revision, $fields, $grants, $operations, $corpus,
            hash('sha256', json_encode([$schema->revision, $grants], JSON_THROW_ON_ERROR)));
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
        $grant = $this->grants['read|'.$field];

        return in_array(FieldUse::Read, $this->fields[$field]->uses, true)
            && ($grant->kind === FieldUsePlanKind::Unconditional ||
                ($grant->kind === FieldUsePlanKind::ScopedPredicate && in_array($id, $grant->recordIds, true)));
    }

    /** @return ?list<string> */
    public function aggregateCorpus(string $field): ?array
    {
        return self::intersect($this->recordIds, $this->grants['aggregate|'.$field]);
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
