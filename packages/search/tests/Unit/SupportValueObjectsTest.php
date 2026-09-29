<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\DataScope;
use CoreX\Search\Data\Candidate;
use CoreX\Search\Data\CandidatePage;
use CoreX\Search\Data\CurrentSearchRecord;
use CoreX\Search\Data\DocumentBatch;
use CoreX\Search\Data\DocumentCursor;
use CoreX\Search\Data\IndexBatch;
use CoreX\Search\Data\IndexOutcome;
use CoreX\Search\Data\IndexSubmission;
use CoreX\Search\Data\IndexSwap;
use CoreX\Search\Data\SearchCapabilities;
use CoreX\Search\Data\SearchPage;
use CoreX\Search\Data\SearchQuery;
use CoreX\Search\Data\SearchSnapshot;
use CoreX\Search\Internal\UnavailableSearchProvider;

it('keeps neutral search value objects typed and unsupported providers empty', function (): void {
    $record = new RecordRef(entity: 'equipment', id: 'pump-1');
    $scope = DataScope::fromTenantContext(
        context: new TenantContext(account: new AccountRef(id: 'account-a', slug: 'account-a', status: 'active', features: [], limits: []), boxed: false),
        connectionIdentity: 'pgsql:account-a', actorId: 'operator-a', restoreEpoch: str_repeat('a', 64),
    );
    $provider = new UnavailableSearchProvider;
    $page = $provider->query(scope: $scope, query: new SearchQuery(entity: 'equipment', schemaHash: str_repeat('b', 64), text: 'pump'));

    expect($provider->capabilities())->toEqual(new SearchCapabilities(fullText: false))
        ->and($page)->toEqual(new CandidatePage(candidates: [], nextAfterId: null, truncated: false))
        ->and((new Candidate($record))->record)->toBe($record)
        ->and((new SearchPage(records: [['id' => 'pump-1']], nextAfterId: null, truncated: false))->records)->toHaveCount(1)
        ->and((new DocumentBatch(documents: [['id' => 'pump-1']], next: new DocumentCursor(value: 'cursor')))->next->value)->toBe('cursor')
        ->and((new IndexBatch(generation: 'g1', documents: [['id' => 'pump-1']]))->generation)->toBe('g1')
        ->and((new IndexSubmission(taskId: 'task-1', generation: 'g1'))->taskId)->toBe('task-1')
        ->and((new IndexOutcome(status: 'succeeded'))->status)->toBe('succeeded')
        ->and((new IndexSwap(fromGeneration: 'g1', toGeneration: 'g2'))->toGeneration)->toBe('g2');
});

it('rejects unsafe search snapshot and current record values', function (): void {
    expect(fn (): SearchSnapshot => new SearchSnapshot(entity: 'equipment', schemaHash: str_repeat('a', 64), table: 'bad-table', idColumn: 'id', searchableColumns: ['title']))->toThrow(InvalidArgumentException::class)
        ->and(fn (): CurrentSearchRecord => new CurrentSearchRecord(record: new RecordRef(entity: 'equipment', id: 'pump-1'), schemaHash: str_repeat('a', 64), revision: str_repeat('b', 64), fields: ['bad-field' => 'unsafe']))->toThrow(InvalidArgumentException::class);
});
