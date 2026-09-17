<?php

declare(strict_types=1);

use CoreX\Attributes\Internal\Engine\JsonbAttributeStore;
use CoreX\Attributes\Internal\KeyConflict;
use CoreX\Attributes\Internal\ScopeDenied;
use CoreX\Attributes\Internal\VersionConflict;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Attributes\Tests\PgTestCase;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class);

function jsonbScope(string $connectionIdentity = 'pgsql:corex_test', ?string $restoreEpoch = null, ?Closure $authorize = null): DataScope
{
    return new DataScope(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: $connectionIdentity,
        actorId: 'user-1',
        authorize: $authorize ?? static fn (): bool => true,
        restoreEpoch: $restoreEpoch ?? str_repeat('a', 64),
    );
}

it('writes reads replays and versions an isolated JSONB attribute document', function (): void {
    $store = new JsonbAttributeStore(DB::connection());
    $record = new RecordRef(entity: 'contact', id: 'record-1');
    $patch = new ValuePatch(values: ['email' => 'owner@example.test']);
    $token = new WriteToken(operationKey: 'write-1', payloadHash: str_repeat('b', 64), expectedRecordVersion: 'absent');

    $written = $store->write(jsonbScope(), $record, $patch, $token);
    $replayed = $store->write(jsonbScope(), $record, $patch, $token);
    $read = $store->read(jsonbScope(), $record, new FieldSelection(fields: ['email']));

    expect($written->status)->toBe('applied')
        ->and($replayed->status)->toBe('replayed')
        ->and($read->values)->toBe(['email' => 'owner@example.test']);

    expect(fn () => $store->write(
        jsonbScope(),
        $record,
        new ValuePatch(values: ['email' => 'new@example.test']),
        new WriteToken(operationKey: 'write-2', payloadHash: str_repeat('c', 64), expectedRecordVersion: 'absent'),
    ))->toThrow(VersionConflict::class);

    expect(fn () => $store->write(
        jsonbScope(),
        $record,
        $patch,
        new WriteToken(operationKey: 'write-1', payloadHash: str_repeat('d', 64), expectedRecordVersion: $written->recordVersion),
    ))->toThrow(KeyConflict::class);
})->group('pg');

it('rejects a scope whose physical database identity differs from the bound connection', function (): void {
    $store = new JsonbAttributeStore(DB::connection());

    expect(fn (): mixed => $store->read(
        jsonbScope(connectionIdentity: 'pgsql:another_test'),
        new RecordRef(entity: 'contact', id: 'record-1'),
        new FieldSelection(fields: ['email']),
    ))->toThrow(ScopeDenied::class);
})->group('pg');

it('loads a bounded JSONB record selection with one documents query', function (): void {
    $store = new JsonbAttributeStore(DB::connection());
    $records = array_map(static fn (int $number): RecordRef => new RecordRef(entity: 'contact', id: "jsonb-batch-{$number}"), range(1, 50));

    foreach ($records as $number => $record) {
        $store->write(
            jsonbScope(),
            $record,
            new ValuePatch(values: ['email' => "record-{$number}@example.test"]),
            new WriteToken(operationKey: "jsonb-batch-{$number}", payloadHash: hash('sha256', "jsonb-batch-{$number}"), expectedRecordVersion: 'absent'),
        );
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $batch = $store->loadMany(jsonbScope(), new RecordSelection(records: $records), new FieldSelection(fields: ['email']));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($batch->records)->toHaveCount(50)
        ->and($batch->records[42]->values)->toBe(['email' => 'record-42@example.test'])
        ->and($queries)->toHaveCount(1);
})->group('pg');
