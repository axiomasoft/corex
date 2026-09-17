<?php

declare(strict_types=1);

use CoreX\Search\Support\SearchFilter;
use CoreX\Search\Support\SearchQuery;
use CoreX\SearchMeilisearch\Internal\MeilisearchQueryAdapter;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use Meilisearch\Client;

it('returns candidate identifiers only from an isolated local Meilisearch service', function (): void {
    $client = new Client('http://127.0.0.1:7700');
    $index = $client->index('corex-p66-query');
    $task = $index->addDocuments([
        ['id' => 'machine-1', 'title' => 'Hydraulic pump'],
        ['id' => 'machine-2', 'title' => 'Hydraulic valve'],
    ], 'id');
    $client->waitForTask($task['taskUid']);
    $adapter = new MeilisearchQueryAdapter(client: $client, indexUid: 'corex-p66-query');
    $scope = new DataScope(
        context: new TenantContext(account: new AccountRef(id: 'account-a', slug: 'account-a', status: 'active', features: [], limits: []), boxed: false),
        connectionIdentity: 'pgsql:account-a',
        actorId: 'operator-a',
        authorize: static fn (): bool => true,
        restoreEpoch: str_repeat('b', 64),
    );

    $page = $adapter->query(
        scope: $scope,
        query: new SearchQuery(entity: 'machine', schemaHash: str_repeat('a', 64), text: 'hydraulic'),
    );

    expect($page->candidates)->toHaveCount(2)
        ->and($page->candidates[0]->record->entity)->toBe('machine')
        ->and($page->candidates[0]->record->id)->toBeString()
        ->and(fn (): mixed => $adapter->query(
            scope: $scope,
            query: new SearchQuery(entity: 'machine', schemaHash: str_repeat('a', 64), text: 'hydraulic', filters: [new SearchFilter(field: 'status', values: ['active'])]),
        ))->toThrow(InvalidArgumentException::class);
})->group('meilisearch');
