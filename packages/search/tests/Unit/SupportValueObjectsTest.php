<?php

declare(strict_types=1);

use CoreX\Search\Internal\UnsupportedSearchProvider;
use CoreX\Search\Support\Candidate;
use CoreX\Search\Support\CandidatePage;
use CoreX\Search\Support\CurrentSearchRecord;
use CoreX\Search\Support\DocumentBatch;
use CoreX\Search\Support\DocumentCursor;
use CoreX\Search\Support\IndexBatch;
use CoreX\Search\Support\IndexOutcome;
use CoreX\Search\Support\IndexSubmission;
use CoreX\Search\Support\IndexSwap;
use CoreX\Search\Support\SearchCapabilities;
use CoreX\Search\Support\SearchPage;
use CoreX\Search\Support\SearchQuery;
use CoreX\Search\Support\SearchSnapshot;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;

it('keeps neutral search value objects typed and unsupported providers empty', function (): void {
    $record = new RecordRef(entity: 'equipment', id: 'pump-1');
    $scope = new DataScope(
        context: new TenantContext(account: new AccountRef(id: 'account-a', slug: 'account-a', status: 'active', features: [], limits: []), boxed: false),
        connectionIdentity: 'pgsql:account-a', actorId: 'operator-a', restoreEpoch: str_repeat('a', 64),
    );
    $provider = new UnsupportedSearchProvider;
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
