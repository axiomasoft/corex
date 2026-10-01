<?php

declare(strict_types=1);

use CoreX\CustomFields\Data\FieldSelection;
use CoreX\CustomFields\Data\RecordSelection;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\InvalidAttributeValue;
use CoreX\CustomFields\Internal\KeyConflict;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\CustomFields\Internal\Values\JsonbAttributeStore;
use CoreX\CustomFields\Internal\VersionConflict;
use CoreX\CustomFields\Tests\PgTestCase;
use CoreX\CustomFields\Tests\Support\PgTestSupport;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('writes reads replays and versions an isolated JSONB attribute document', function (): void {
    $store = new JsonbAttributeStore(
        connection: DB::connection(),
        tables: PgTestSupport::tables(),
        catalogue: PgTestSupport::catalogue('contact', [['code' => 'email', 'type' => 'email']]),
        authorizer: jsonbAuthorizer(),
    );
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
    $store = new JsonbAttributeStore(
        connection: DB::connection(),
        tables: PgTestSupport::tables(),
        catalogue: PgTestSupport::catalogue('contact', [['code' => 'email', 'type' => 'email']]),
        authorizer: jsonbAuthorizer(),
    );

    expect(fn (): mixed => $store->read(
        jsonbScope(connectionIdentity: 'pgsql:another_test'),
        new RecordRef(entity: 'contact', id: 'record-1'),
        new FieldSelection(fields: ['email']),
    ))->toThrow(ScopeDenied::class);
})->group('pg');

it('loads a bounded JSONB record selection with one documents query', function (): void {
    $store = new JsonbAttributeStore(
        connection: DB::connection(),
        tables: PgTestSupport::tables(),
        catalogue: PgTestSupport::catalogue('contact', [['code' => 'email', 'type' => 'email']]),
        authorizer: jsonbAuthorizer(),
    );
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

it('rejects writes for fields that are absent from the active schema snapshot', function (): void {
    $store = new JsonbAttributeStore(
        connection: DB::connection(),
        tables: PgTestSupport::tables(),
        catalogue: PgTestSupport::catalogue('contact', [['code' => 'email', 'type' => 'email']]),
        authorizer: jsonbAuthorizer(),
    );

    expect(fn (): mixed => $store->write(
        jsonbScope(),
        new RecordRef(entity: 'contact', id: 'record-1'),
        new ValuePatch(values: ['unknown' => 'value']),
        new WriteToken(operationKey: 'write-unknown', payloadHash: str_repeat('b', 64), expectedRecordVersion: 'absent'),
    ))->toThrow(InvalidAttributeValue::class);
})->group('pg');
