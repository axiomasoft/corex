<?php

declare(strict_types=1);

use CoreX\Attributes\Internal\Engine\EavAttributeStore;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Attributes\Tests\PgTestCase;
use CoreX\Attributes\Tests\Support\PgTestSupport;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('stores independently indexed EAV values with the same version boundary', function (): void {
    $store = new EavAttributeStore(
        connection: DB::connection(),
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

    $values = $store->read(jsonbScope(), $record, new FieldSelection(fields: ['score']));

    expect($written->status)->toBe('applied')
        ->and($values->values)->toBe(['score' => 5])
        ->and(DB::table('sys_attribute_eav_values')->where('entity', 'contact')->where('record_id', 'record-eav')->count())->toBe(2);
})->group('pg');

it('loads a 50 by 30 EAV selection with one values query', function (): void {
    $fields = array_map(static fn (int $number): string => sprintf('field_%02d', $number), range(1, 30));
    $store = new EavAttributeStore(
        connection: DB::connection(),
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
    $batch = $store->loadMany(jsonbScope(), new RecordSelection(records: $records), new FieldSelection(fields: $fields));
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($batch->records)->toHaveCount(50)
        ->and($batch->records[42]->values)->toHaveCount(30)
        ->and($queries)->toHaveCount(1);
})->group('pg');
