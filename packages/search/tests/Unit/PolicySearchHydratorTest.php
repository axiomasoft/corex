<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\Search\Data\Candidate;
use CoreX\Search\Data\CandidatePage;
use CoreX\Search\Data\CurrentSearchRecord;
use CoreX\Search\Data\SearchSnapshot;
use CoreX\Search\Internal\Query\PolicySearchHydrator;

it('drops deleted, stale and policy-denied candidates before returning schema-approved fields', function (): void {
    $schema = new SearchSnapshot(
        entity: 'equipment', schemaHash: str_repeat('a', 64), table: 'equipment', idColumn: 'id', searchableColumns: ['title'], resultColumns: ['id', 'title'],
    );
    $allowed = new RecordRef(entity: 'equipment', id: 'allowed');
    $denied = new RecordRef(entity: 'equipment', id: 'denied');
    $stale = new RecordRef(entity: 'equipment', id: 'stale');
    $scope = DataScope::fromTenantContext(
        context: new TenantContext(account: new AccountRef(id: 'account-a', slug: 'account-a', status: 'active', features: [], limits: []), boxed: false),
        connectionIdentity: 'pgsql:account-a', actorId: 'operator-a', restoreEpoch: str_repeat('b', 64),
    );
    $hydrator = new PolicySearchHydrator(
        static function (DataScope $_scope, RecordRef $record): ?CurrentSearchRecord {
            if ($record->id === 'deleted') {
                return null;
            }

            return new CurrentSearchRecord(
                record: $record,
                schemaHash: $record->id === 'stale' ? str_repeat('c', 64) : str_repeat('a', 64),
                revision: str_repeat('d', 64),
                fields: ['id' => $record->id, 'title' => 'Pump', 'internal_note' => 'must not leak'],
            );
        },
        new CallableDataScopeAuthorizer(
            static fn (DataScope $_scope, string $entity, string $subjectId): bool => $subjectId === 'allowed',
        ),
    );

    $page = $hydrator->authorize(
        scope: $scope,
        candidates: new CandidatePage(candidates: [new Candidate($allowed), new Candidate($denied), new Candidate($stale), new Candidate(new RecordRef(entity: 'equipment', id: 'deleted'))], nextAfterId: 'v1.cursor', truncated: true),
        schema: $schema,
    );

    expect($page->records)->toBe([['id' => 'allowed', 'title' => 'Pump']])
        ->and($page->nextAfterId)->toBe('v1.cursor')
        ->and($page->truncated)->toBeTrue();
});

it('authorizes the returned field projection for the current actor', function (): void {
    $schema = new SearchSnapshot(
        entity: 'employees', schemaHash: str_repeat('a', 64), table: 'employees', idColumn: 'id', searchableColumns: ['name'], resultColumns: ['id', 'name', 'salary'],
    );
    $record = new RecordRef(entity: 'employees', id: 'employee-1');
    $seen = [];
    $hydrator = new PolicySearchHydrator(
        static fn (): CurrentSearchRecord => new CurrentSearchRecord(
            record: $record, schemaHash: $schema->schemaHash, revision: str_repeat('d', 64),
            fields: ['id' => 'employee-1', 'name' => 'Alice', 'salary' => 100000, 'private_note' => 'hidden'],
        ),
        new CallableDataScopeAuthorizer(
            static function (DataScope $scope, string $entity, string $subjectId, array $fields) use (&$seen): bool {
                $seen[] = $fields;

                return $scope->actorId === 'payroll' || ! in_array('salary', $fields, true);
            },
        ),
    );
    $pages = [];
    foreach (['employee', 'payroll'] as $actor) {
        $scope = DataScope::fromTenantContext(
            context: new TenantContext(account: new AccountRef(id: 'account-a', slug: 'account-a', status: 'active', features: [], limits: []), boxed: false),
            connectionIdentity: 'pgsql:account-a', actorId: $actor, restoreEpoch: str_repeat('b', 64),
        );
        $pages[] = $hydrator->authorize($scope, new CandidatePage(candidates: [new Candidate($record)], nextAfterId: null, truncated: false), $schema)->records;
    }

    expect($pages[0])->toBe([])
        ->and($pages[1])->toBe([['id' => 'employee-1', 'name' => 'Alice', 'salary' => 100000]])
        ->and($seen)->toBe([['id', 'name', 'salary'], ['id', 'name', 'salary']]);
});
