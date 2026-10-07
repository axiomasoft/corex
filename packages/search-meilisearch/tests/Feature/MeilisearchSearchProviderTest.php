<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Data\SearchFilter;
use CoreX\Search\Data\SearchQuery;
use CoreX\SearchMeilisearch\Internal\MeilisearchSearchProvider;
use CoreX\SearchMeilisearch\Tests\MeilisearchTestCase;

uses(MeilisearchTestCase::class)->group('meilisearch');

it('returns candidate identifiers only from an isolated local Meilisearch service', function (): void {
    $index = $this->index('query');
    $task = $index->addDocuments([
        ['id' => 'machine-1', 'title' => 'Hydraulic pump'],
        ['id' => 'machine-2', 'title' => 'Hydraulic valve'],
    ], 'id');
    $this->client->waitForTask($task['taskUid']);
    $adapter = new MeilisearchSearchProvider(client: $this->client, indexUid: $index->getUid());
    $scope = DataScope::fromTenantContext(
        context: new TenantContext(account: new AccountRef(id: 'account-a', slug: 'account-a', status: 'active', features: [], limits: []), boxed: false),
        connectionIdentity: 'pgsql:account-a',
        actorId: 'operator-a',
        restoreEpoch: str_repeat('b', 64),
    );

    $page = $adapter->query(
        scope: $scope,
        query: new SearchQuery(entity: 'machine', schemaHash: str_repeat('a', 64), text: 'hydraulic'),
    );

    $limited = $adapter->query(scope: $scope, query: new SearchQuery(entity: 'machine', schemaHash: str_repeat('a', 64), text: 'hydraulic', limit: 1));

    expect($page->candidates)->toHaveCount(2)
        ->and(array_map(static fn ($candidate): string => $candidate->record->id, $page->candidates))->toContain('machine-1', 'machine-2')
        ->and($page->candidates[0]->record->entity)->toBe('machine')
        ->and($page->truncated)->toBeFalse()
        ->and($limited->candidates)->toHaveCount(1)
        ->and($limited->truncated)->toBeTrue()
        ->and($limited->nextAfterId)->toBeNull()
        ->and(fn (): mixed => $adapter->query(
            scope: $scope,
            query: new SearchQuery(entity: 'machine', schemaHash: str_repeat('a', 64), text: 'hydraulic', afterId: 'unsupported'),
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn (): mixed => $adapter->query(
            scope: $scope,
            query: new SearchQuery(entity: 'machine', schemaHash: str_repeat('a', 64), text: 'hydraulic', filters: [new SearchFilter(field: 'status', values: ['active'])]),
        ))->toThrow(InvalidArgumentException::class);
})->group('meilisearch');
