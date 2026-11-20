<?php

declare(strict_types=1);

use CoreX\CustomFields\Data\RecordSelection;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\Values\EavAttributeStore;
use CoreX\CustomFields\Tests\PgTestCase;
use CoreX\CustomFields\Tests\Support\PgTestSupport;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('stores independently indexed EAV values with the same version boundary', function (): void {
    $store = new EavAttributeStore(
        connection: DB::connection(),
        current: jsonbCurrent(),
        tables: PgTestSupport::tables(),
        catalogue: PgTestSupport::catalogue('contact', [
            ['code' => 'status', 'type' => 'text'],
            ['code' => 'score', 'type' => 'int'],
        ]),
        authorizer: jsonbAuthorizer(),
    );
    $record = new RecordRef(entity: 'contact', id: 'record-eav');
    $written = $store->write(
        jsonbScope(),
        $record,
        new ValuePatch(values: ['status' => 'lead', 'score' => 5]),
        new WriteToken(operationKey: 'eav-write-1', payloadHash: str_repeat('e', 64), expectedRecordVersion: 'absent'),
    );

    $values = $store->read(jsonbScope(), $record, jsonbSelection('contact', ['score']));

    expect($written->status)->toBe('applied')
        ->and($values->values)->toBe(['score' => 5])
        ->and(DB::table('sys_attribute_eav_values')->where('entity', 'contact')->where('record_id', 'record-eav')->count())->toBe(2);
})->group('pg');

it('loads a 50 by 30 EAV selection with one values query', function (): void {
    $fields = array_map(static fn (int $number): string => sprintf('field_%02d', $number), range(1, 30));
    $store = new EavAttributeStore(
        connection: DB::connection(),
        current: jsonbCurrent(),
        tables: PgTestSupport::tables(),
        catalogue: PgTestSupport::catalogue('contact', array_map(
            static fn (string $field): array => ['code' => $field, 'type' => 'text'],
            $fields,
        )),
        authorizer: jsonbAuthorizer(),
    );
    $records = array_map(static fn (int $number): RecordRef => new RecordRef(entity: 'contact', id: "batch-{$number}"), range(1, 50));

    foreach ($records as $number => $record) {
        $values = array_combine($fields, array_map(static fn (string $field): string => "{$field}-{$number}", $fields));
        $store->write(
            jsonbScope(),
            $record,
            new ValuePatch(values: $values),
            new WriteToken(operationKey: "eav-batch-{$number}", payloadHash: hash('sha256', "eav-batch-{$number}"), expectedRecordVersion: 'absent'),
        );
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $batch = $store->loadMany(jsonbScope(), new RecordSelection(records: $records), jsonbSelection('contact', $fields));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($batch->records)->toHaveCount(50)
        ->and($batch->records[42]->values)->toHaveCount(30)
        ->and($queries)->toHaveCount(1);
})->group('pg');
