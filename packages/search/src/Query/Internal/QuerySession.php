<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Search\Errors\Exceptions\GenerationChanged;
use CoreX\Search\Errors\Exceptions\IndexNotReady;
use CoreX\Search\Errors\Exceptions\InvalidSearchCursor;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Errors\Exceptions\SearchWitnessChanged;
use CoreX\Search\Index\Contracts\ActiveGenerations;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Projection\Contracts\ProjectionSnapshotCapabilities;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\ExactCandidateDriver;
use CoreX\Search\Query\Contracts\LiveCandidateDriver;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\SearchCount;
use CoreX\Search\Query\Data\SearchFacets;
use CoreX\Search\Query\Data\SearchFilter;
use CoreX\Search\Query\Data\SearchPage;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\Query\Data\SearchSort;
use CoreX\Search\Query\Data\SearchStatistics;
use Packman\Attributes\Internal;

/** One verified caller and one query. No mutable state is retained across calls. */
#[Internal]
final class QuerySession
{
    private int $remaining;

    private string $binding;

    private LoadedHits $returned;

    private ?SearchPage $produced = null;

    private string $identity;

    /** @var ?array{mapping:string,generation:string,witness:string,position:string} */
    private ?array $continuation = null;

    public function __construct(
        private readonly ProjectionContext $context,
        private readonly SearchQuery $query,
        private readonly CandidateDriver $driver,
        private readonly FieldUsePolicy $policy,
        private readonly SearchProjectionContributor $projection,
        private readonly AuthorizedHydrator $hydrator,
        private readonly CurrentWorkScope $current,
        private readonly ?ActiveGenerations $generations,
        private readonly SearchCursor $cursor,
        private readonly string $backend,
        private readonly string $cluster,
        int $budget,
    ) {
        $this->remaining = $budget;
    }

    public function run(): SearchPage
    {
        $this->identity = SearchCursor::identity($this->context->scope, $this->query);

        if ($this->query->cursor !== null) {
            $this->continuation = $this->cursor->decode($this->identity, $this->query->cursor);
        }

        for ($attempt = 0; ; $attempt++) {
            try {
                $exactRequest = $this->driver instanceof ExactCandidateDriver && ($this->query->count || $this->query->facets !== [])
                    && (! $this->projection instanceof ProjectionSnapshotCapabilities || $this->projection->supportsReadOnlySnapshot());

                if ($exactRequest) {
                    $admission = $this->plan();
                    $admission->verifyLowering($this->driver->capabilities(), $this->query);

                    if ($this->continuation !== null) {
                        $this->position(SearchNamespace::generation($this->target($admission->mapping)), $admission->witness);
                    }
                }
                $this->produced = null;
                $page = $exactRequest
                    ? $this->driver->inSnapshot($this->context->scope, $this->scan(...))
                    : $this->scan(false);

                return $this->release($page);
            } catch (SearchWitnessChanged $changed) {
                if ($this->query->cursor !== null || $attempt >= 1 || $this->remaining < 1) {
                    throw $changed;
                }
            }
        }
    }

    private function release(SearchPage $page): SearchPage
    {
        if ($page !== $this->produced) {
            throw SearchFeatureUnavailable::unsupported();
        }

        // A PostgreSQL repeatable-read snapshot can hide a revocation committed while it ran.
        $this->current->assert($this->context->scope);
        $plan = $this->plan();

        if ($this->binding !== $this->binding($plan)) {
            throw SearchWitnessChanged::changed();
        }
        $live = $this->hydrator->hydrate($this->context, $plan, $this->query, $this->returned->candidates);

        if ($live->fingerprint() !== $this->returned->fingerprint() || $this->binding !== $this->binding($this->plan())) {
            throw SearchWitnessChanged::changed();
        }

        return $page;
    }

    private function plan(): SearchPlan
    {
        $schema = $this->projection->describe($this->context);

        if ($this->continuation !== null && ! hash_equals($this->continuation['mapping'], $schema->revision)) {
            throw GenerationChanged::changed();
        }

        return SearchPlan::compile($this->context, $this->query, $schema, $this->policy);
    }

    private function target(string $mapping): IndexTarget
    {
        $context = $this->context;
        $target = $this->driver instanceof LiveCandidateDriver
            ? $this->driver->target($context->scope, $context->entity, $mapping)
            : $this->generations?->active($context->scope, $context->entity);

        if ($target === null || SearchNamespace::scope($target->scope) !== SearchNamespace::scope($context->scope)
            || $target->backend !== $this->backend || $target->cluster !== $this->cluster || $target->entity->value !== $context->entity->value
            || $target->mappingDigest !== $mapping) {
            throw $this->query->cursor === null ? IndexNotReady::unpublished() : GenerationChanged::changed();
        }

        return $target;
    }

    private function binding(SearchPlan $plan): string
    {
        return $plan->witness.SearchNamespace::generation($this->target($plan->mapping));
    }

    private function scan(bool $exact): SearchPage
    {
        $plan = $this->plan();
        $plan->verifyLowering($this->driver->capabilities(), $this->query);
        $target = $this->target($plan->mapping);
        $generation = SearchNamespace::generation($target);
        $this->binding = $plan->witness.$generation;
        $position = $this->position($generation, $plan->witness);
        $hits = [];
        $revisions = [];
        $confirmed = [];
        $seen = [];
        $positions = [];
        do {
            $limit = min($this->remaining, $this->query->limit - count($hits));
            $page = $this->driver->candidates($this->request($target, $plan, $plan->recordIds, $position, $limit));
            $size = count($page->candidates);

            if ($size > $limit || ($page->nextPosition !== null && ($size === 0 || $page->nextPosition === $position || isset($positions[$page->nextPosition])))) {
                throw SearchFeatureUnavailable::unsupported();
            }
            $this->remaining -= $size;
            foreach ($page->candidates as $candidate) {
                $ref = $candidate->record;

                if (SearchNamespace::generation($candidate->generation) !== $generation
                    || $candidate->generation->mappingDigest !== $target->mappingDigest
                    || $ref->entity !== $this->context->entity->value || ! hash_equals($target->documentId($ref), $candidate->documentId) || isset($seen[$ref->id])
                    || ($plan->recordIds !== null && ! in_array($ref->id, $plan->recordIds, true))) {
                    throw SearchFeatureUnavailable::unsupported();
                }
                $seen[$ref->id] = true;
            }
            $loaded = $this->hydrator->hydrate($this->context, $plan, $this->query, $page->candidates);
            array_push($hits, ...$loaded->hits);
            $revisions += $loaded->revisions;
            array_push($confirmed, ...$loaded->candidates);
            $position = $page->nextPosition;

            if ($position !== null) {
                $positions[$position] = true;
            }
        } while ($position !== null && $this->remaining > 0 && count($hits) < $this->query->limit);

        $stats = $this->statistics($exact, $target, $plan);
        $this->returned = new LoadedHits($hits, $revisions, $confirmed);

        return $this->produced = new SearchPage($hits, $position === null ? null : $this->cursor->encode($this->identity, $plan->mapping, $generation, $plan->witness, $position),
            new SearchCount($this->query->count, $stats->count), new SearchFacets($this->query->facets !== [], $stats->facets));
    }

    private function position(string $generation, string $witness): ?string
    {
        if ($this->continuation === null) {
            return null;
        }

        if (! hash_equals($this->continuation['generation'], $generation)) {
            throw GenerationChanged::changed();
        }

        if (! hash_equals($this->continuation['witness'], $witness)) {
            throw InvalidSearchCursor::invalid();
        }

        return $this->continuation['position'];
    }

    /** @param ?list<string> $corpus */
    private function request(IndexTarget $target, SearchPlan $plan, ?array $corpus, ?string $position, int $limit): CandidateRequest
    {
        $q = $this->query;

        return new CandidateRequest($this->context->scope, $target, $q->text, array_map($plan->physicalField(...), $q->searchFields),
            array_map(static fn (SearchFilter $filter): SearchFilter => new SearchFilter($plan->physicalField($filter->field), $filter->values), $plan->filters),
            array_map(static fn (SearchSort $sort): SearchSort => new SearchSort($plan->physicalField($sort->field), $sort->descending), $q->sort),
            $corpus, $position, $limit);
    }

    private function statistics(bool $exact, IndexTarget $target, SearchPlan $plan): SearchStatistics
    {
        if (! $exact || ! $this->driver instanceof ExactCandidateDriver) {
            return new SearchStatistics(null, null);
        }
        $count = null;

        if ($this->query->count) {
            $request = $this->request($target, $plan, $plan->aggregateCorpus(''), null, 1);
            $count = $this->driver->statistics($request, true, [])->count;
        }
        $facets = [];
        foreach ($this->query->facets as $field) {
            $request = $this->request($target, $plan, $plan->aggregateCorpus($field), null, 1);
            $physical = $plan->physicalField($field);
            $stats = $this->driver->statistics($request, false, [$physical]);

            if (! isset($stats->facets[$physical])) {
                return new SearchStatistics($count, null);
            }
            $facets[$field] = $stats->facets[$physical];
        }

        return new SearchStatistics($count, $facets === [] ? null : $facets);
    }
}
