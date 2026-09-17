<?php

declare(strict_types=1);

use CoreX\Search\Internal\Query\PolicySearchHydrator;
use CoreX\Search\Support\Candidate;
use CoreX\Search\Support\CandidatePage;
use CoreX\Search\Support\CurrentSearchRecord;
use CoreX\Search\Support\SearchSnapshot;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;

it('drops deleted, stale and policy-denied candidates before returning schema-approved fields', function (): void {
    $schema = new SearchSnapshot(
        entity: 'equipment', schemaHash: str_repeat('a', 64), table: 'equipment', idColumn: 'id', searchableColumns: ['title'], resultColumns: ['id', 'title'],
    );
    $allowed = new RecordRef(entity: 'equipment', id: 'allowed');
    $denied = new RecordRef(entity: 'equipment', id: 'denied');
    $stale = new RecordRef(entity: 'equipment', id: 'stale');
    $scope = new DataScope(
        context: new TenantContext(account: new AccountRef(id: 'account-a', slug: 'account-a', status: 'active', features: [], limits: []), boxed: false),
        connectionIdentity: 'pgsql:account-a', actorId: 'operator-a', authorize: static fn (RecordRef $record): bool => $record->id === 'allowed', restoreEpoch: str_repeat('b', 64),
    );
    $hydrator = new PolicySearchHydrator(static function (DataScope $_scope, RecordRef $record): ?CurrentSearchRecord {
        if ($record->id === 'deleted') {
            return null;
        }

        return new CurrentSearchRecord(
            record: $record,
            schemaHash: $record->id === 'stale' ? str_repeat('c', 64) : str_repeat('a', 64),
            revision: str_repeat('d', 64),
            fields: ['id' => $record->id, 'title' => 'Pump', 'internal_note' => 'must not leak'],
        );
    });

    $page = $hydrator->authorize(
        scope: $scope,
        candidates: new CandidatePage(candidates: [new Candidate($allowed), new Candidate($denied), new Candidate($stale), new Candidate(new RecordRef(entity: 'equipment', id: 'deleted'))], nextAfterId: 'v1.cursor', truncated: true),
        schema: $schema,
    );

    expect($page->records)->toBe([['id' => 'allowed', 'title' => 'Pump']])
        ->and($page->nextAfterId)->toBe('v1.cursor')
        ->and($page->truncated)->toBeTrue();
});
