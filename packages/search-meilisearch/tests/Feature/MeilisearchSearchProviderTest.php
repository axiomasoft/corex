<?php

declare(strict_types=1);

use CoreX\Data\Data\RecordRef;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexDocument;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\SearchFilter;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use CoreX\SearchMeilisearch\Internal\MeilisearchCandidateDriver;
use CoreX\SearchMeilisearch\Tests\MeilisearchTestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Meilisearch\Exceptions\ApiException;

uses(MeilisearchTestCase::class)->group('meilisearch');

it('returns only exact UID candidates and continues by record ref in a shared Meilisearch cluster', function (): void {
    $context = $this->context();
    $otherContext = $this->context('space-b');
    $a = $this->target($context);
    $b = $this->target($otherContext);
    $currentA = new CallerWorkScopeFixture($context, expected: $a->scope);
    $currentB = new CallerWorkScopeFixture($otherContext, expected: $b->scope);
    $indexA = $this->adapter($currentA);
    $indexB = $this->adapter($currentB);
    $this->provision($indexA, $a);
    $this->provision($indexB, $b);
    $this->await($indexA->submit(new IndexBatch($a, 'a', IndexAction::Upsert, [
        new IndexDocument(new RecordRef('machine', 'machine-1'), new ProjectedValues(['title' => 'Hydraulic pump', 'private' => 'redacted'])),
        new IndexDocument(new RecordRef('machine', 'machine-2'), new ProjectedValues(['title' => 'Hydraulic valve'])),
        new IndexDocument(new RecordRef('machine', 'machine/ü'), new ProjectedValues(['title' => 'Hydraulic axle'])),
    ])));
    $this->await($indexB->submit(new IndexBatch($b, 'b', IndexAction::Upsert, [
        new IndexDocument(new RecordRef('machine', 'machine-1'), new ProjectedValues(['title' => 'Pneumatic brake'])),
    ])));
    $driver = new MeilisearchCandidateDriver($this->scopedClient(...), $currentA, 'cluster-test');
    $request = static fn (?string $position): CandidateRequest => new CandidateRequest($a->scope, $a, 'hydraulic', ['title'], [], [], null, $position, 1);
    $first = $driver->candidates($request(null));
    $second = $driver->candidates($request($first->nextPosition));
    $third = $driver->candidates($request($second->nextPosition));
    $end = $driver->candidates($request($third->nextPosition));
    $scoped = $driver->candidates(new CandidateRequest($a->scope, $a, 'hydraulic', ['title'], [], [], ['machine-2'], null, 10));
    $hidden = $driver->candidates(new CandidateRequest($a->scope, $a, 'redacted', ['title'], [], [], null, null, 10));
    $fromB = new MeilisearchCandidateDriver($this->scopedClient(...), $currentB, 'cluster-test');
    $reader = $this->scopedClient($a, MeilisearchAccess::Search);
    $writer = $this->scopedClient($a, MeilisearchAccess::Index);

    expect($first->candidates[0]->record->id)->toBe('machine-1')
        ->and($first->candidates[0]->documentId)->toBe($a->documentId(new RecordRef('machine', 'machine-1')))
        ->and($first->candidates[0]->textWitnesses?->digests)->toBe(['title' => new ProjectedValues(['title' => 'Hydraulic pump'])->fieldWitness('title')])
        ->and($second->candidates[0]->record->id)->toBe('machine-2')
        ->and($third->candidates[0]->record->id)->toBe('machine/ü')
        ->and($end->candidates)->toBe([])
        ->and($end->nextPosition)->toBeNull()
        ->and(array_map(static fn (Candidate $candidate): string => $candidate->record->id, $scoped->candidates))->toBe(['machine-2'])
        ->and($hidden->candidates)->toBe([])
        ->and($fromB->candidates(new CandidateRequest($b->scope, $b, 'hydraulic', ['title'], [], [], null, null, 10))->candidates)->toBe([])
        ->and(fn () => $reader->index($b->uid)->search('hydraulic'))->toThrow(ApiException::class)
        ->and(fn () => $reader->index($a->uid)->addDocuments([['id' => 'forbidden']], 'id'))->toThrow(ApiException::class)
        ->and(fn () => $writer->index($b->uid)->addDocuments([['id' => 'forbidden']], 'id'))->toThrow(ApiException::class)
        ->and(fn () => $writer->index($a->uid)->search('hydraulic'))->toThrow(ApiException::class)
        ->and(fn () => $driver->candidates(new CandidateRequest($a->scope, $b, null, [], [], [], null, null, 10)))->toThrow(SearchFeatureUnavailable::class)
        ->and(fn () => $driver->candidates(new CandidateRequest($a->scope, $a, null, [], [new SearchFilter('private', ['redacted'])], [], null, null, 10)))->toThrow(SearchFeatureUnavailable::class);
});
