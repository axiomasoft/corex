<?php

declare(strict_types=1);

use CoreX\CustomFields\Data\SchemaChange;
use CoreX\CustomFields\Data\SchemaOperationId;
use CoreX\CustomFields\Internal\Schema\QueuedSchemaOperations;
use CoreX\CustomFields\Internal\Schema\SchemaOperationWorker;
use CoreX\CustomFields\Internal\SchemaOperationConflict;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\CustomFields\Tests\PgTestCase;
use CoreX\CustomFields\Tests\Support\PgTestSupport;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

function queuedSchemaOperations(string $schemaHash = '', ?DataScopeAuthorizer $authorizer = null): QueuedSchemaOperations
{
    $hash = $schemaHash !== '' ? $schemaHash : str_repeat('f', 64);

    return new QueuedSchemaOperations(
        connection: DB::connection(),
        tables: PgTestSupport::tables(),
        catalogue: schemaCatalogue('contact', $hash),
        authorizer: $authorizer ?? jsonbAuthorizer(),
    );
}

function schemaWorker(?DataScopeAuthorizer $authorizer = null): SchemaOperationWorker
{
    return new SchemaOperationWorker(
        connection: DB::connection(),
        tables: PgTestSupport::tables(),
        authorizer: $authorizer ?? jsonbAuthorizer(),
    );
}

it('persists schema work as pending instead of executing DDL in the request path', function (): void {
    $hash = str_repeat('f', 64);
    $operations = queuedSchemaOperations($hash);
    $change = new SchemaChange(entity: 'contact', kind: 'add', expectedSchemaHash: $hash, definition: ['field' => 'score'], approvalDigest: $hash);
    $key = new SchemaOperationId(key: 'schema-1', payloadHash: str_repeat('a', 64));

    $receipt = $operations->request(jsonbScope(), $change, $key);
    $index = 'sys_attr_doc_'.substr(hash('sha256', "contact\nscore"), 0, 24);

    expect(DB::table('pg_indexes')->where('indexname', $index)->exists())->toBeFalse();

    schemaWorker()->run(jsonbScope(), $receipt->operation);

    expect($receipt->status)->toBe('pending')
        ->and($operations->inspect(jsonbScope(), $receipt->operation)->status)->toBe('verified')
        ->and(DB::table('sys_attribute_schema_operations')->value('attempts'))->toBe(1)
        ->and(DB::table('pg_indexes')->where('indexname', $index)->exists())->toBeTrue()
        ->and($operations->verify(jsonbScope(), 'contact')->status)->toBe('clean');
})->group('pg');

it('replays an identical schema request without creating a second operation', function (): void {
    $hash = str_repeat('f', 64);
    $operations = queuedSchemaOperations($hash);
    $change = new SchemaChange(entity: 'contact', kind: 'add', expectedSchemaHash: $hash, definition: ['field' => 'replayed_score'], approvalDigest: $hash);
    $key = new SchemaOperationId(key: 'schema-replay', payloadHash: str_repeat('a', 64));

    $first = $operations->request(jsonbScope(), $change, $key);
    $second = $operations->request(jsonbScope(), $change, $key);

    expect($second->operation->id)->toBe($first->operation->id)
        ->and(DB::table('sys_attribute_schema_operations')->count())->toBe(1);
})->group('pg');

it('reclaims a stale worker claim and completes the managed operation', function (): void {
    $hash = str_repeat('f', 64);
    $operations = queuedSchemaOperations($hash);
    $receipt = $operations->request(
        jsonbScope(),
        new SchemaChange(entity: 'contact', kind: 'add', expectedSchemaHash: $hash, definition: ['field' => 'reclaimed_score'], approvalDigest: $hash),
        new SchemaOperationId(key: 'schema-reclaim', payloadHash: str_repeat('b', 64)),
    );
    DB::table('sys_attribute_schema_operations')->where('id', $receipt->operation->id)->update([
        'status' => 'running',
        'attempts' => 1,
        'started_at' => now()->subMinutes(11),
    ]);

    schemaWorker()->run(jsonbScope(), $receipt->operation);

    expect($operations->inspect(jsonbScope(), $receipt->operation)->status)->toBe('verified')
        ->and(DB::table('sys_attribute_schema_operations')->where('id', $receipt->operation->id)->value('attempts'))->toBe(2);
})->group('pg');

it('reports catalog drift when an owned managed index disappears', function (): void {
    $hash = str_repeat('f', 64);
    $operations = queuedSchemaOperations($hash);
    $receipt = $operations->request(
        jsonbScope(),
        new SchemaChange(entity: 'contact', kind: 'add', expectedSchemaHash: $hash, definition: ['field' => 'drift_score'], approvalDigest: $hash),
        new SchemaOperationId(key: 'schema-drift', payloadHash: str_repeat('c', 64)),
    );
    schemaWorker()->run(jsonbScope(), $receipt->operation);
    $index = 'sys_attr_doc_'.substr(hash('sha256', "contact\ndrift_score"), 0, 24);
    DB::unprepared("DROP INDEX {$index}");

    $report = $operations->verify(jsonbScope(), 'contact');

    expect($report->status)->toBe('drifted')
        ->and($report->drift)->toContain(['field' => 'drift_score', 'reason' => 'missing_managed_index']);
})->group('pg');

it('denies a stale restore epoch before a worker can execute DDL', function (): void {
    $hash = str_repeat('f', 64);
    $operations = queuedSchemaOperations($hash);
    $receipt = $operations->request(
        jsonbScope(),
        new SchemaChange(entity: 'contact', kind: 'add', expectedSchemaHash: $hash, definition: ['field' => 'stale_score'], approvalDigest: $hash),
        new SchemaOperationId(key: 'schema-stale-epoch', payloadHash: str_repeat('e', 64)),
    );

    expect(fn (): mixed => schemaWorker()->run(
        jsonbScope(restoreEpoch: str_repeat('b', 64)),
        $receipt->operation,
    ))->toThrow(ScopeDenied::class);

    $index = 'sys_attr_doc_'.substr(hash('sha256', "contact\nstale_score"), 0, 24);
    expect(DB::table('pg_indexes')->where('indexname', $index)->exists())->toBeFalse()
        ->and($operations->inspect(jsonbScope(), $receipt->operation)->status)->toBe('pending');
})->group('pg');

it('requires the trusted scope to approve the exact schema approval digest', function (): void {
    $hash = str_repeat('f', 64);
    $operations = queuedSchemaOperations($hash, new CallableDataScopeAuthorizer(
        static fn (DataScope $_scope, string $entity, string $subjectId, array $fields, string $action): bool => $action !== 'schema-approve',
    ));

    expect(fn (): mixed => $operations->request(
        jsonbScope(),
        new SchemaChange(entity: 'contact', kind: 'add', expectedSchemaHash: $hash, definition: ['field' => 'unapproved_score'], approvalDigest: $hash),
        new SchemaOperationId(key: 'schema-unapproved', payloadHash: str_repeat('d', 64)),
    ))->toThrow(ScopeDenied::class);

    expect(DB::table('sys_attribute_schema_operations')->count())->toBe(0);
})->group('pg');

it('rejects a stale field snapshot as a schema conflict without writing an operation', function (): void {
    $operations = queuedSchemaOperations(str_repeat('a', 64));

    expect(fn (): mixed => $operations->request(
        jsonbScope(),
        new SchemaChange(entity: 'contact', kind: 'add', expectedSchemaHash: str_repeat('b', 64), definition: ['field' => 'conflict_score'], approvalDigest: str_repeat('a', 64)),
        new SchemaOperationId(key: 'schema-stale-hash', payloadHash: str_repeat('f', 64)),
    ))->toThrow(SchemaOperationConflict::class, 'The schema operation was requested against a stale field snapshot.');

    expect(DB::table('sys_attribute_schema_operations')->count())->toBe(0);
})->group('pg');

it('keeps a not-ready schema operation distinct from access denial', function (): void {
    $hash = str_repeat('f', 64);
    $operations = queuedSchemaOperations($hash);
    $receipt = $operations->request(
        jsonbScope(),
        new SchemaChange(entity: 'contact', kind: 'add', expectedSchemaHash: $hash, definition: ['field' => 'not_ready_score'], approvalDigest: $hash),
        new SchemaOperationId(key: 'schema-not-ready', payloadHash: str_repeat('c', 64)),
    );
    DB::table('sys_attribute_schema_operations')->where('id', $receipt->operation->id)->update([
        'status' => 'running',
        'started_at' => now(),
    ]);

    expect(fn (): mixed => schemaWorker()->run(jsonbScope(), $receipt->operation))
        ->toThrow(SchemaOperationConflict::class, 'The schema operation is not ready for execution.');

    $index = 'sys_attr_doc_'.substr(hash('sha256', "contact\nnot_ready_score"), 0, 24);
    expect(DB::table('sys_attribute_schema_operations')->where('id', $receipt->operation->id)->value('status'))->toBe('running')
        ->and(DB::table('pg_indexes')->where('indexname', $index)->exists())->toBeFalse();
})->group('pg');

it('keeps exhausted schema retries distinct from access denial', function (): void {
    $hash = str_repeat('f', 64);
    $operations = queuedSchemaOperations($hash);
    $receipt = $operations->request(
        jsonbScope(),
        new SchemaChange(entity: 'contact', kind: 'add', expectedSchemaHash: $hash, definition: ['field' => 'exhausted_score'], approvalDigest: $hash),
        new SchemaOperationId(key: 'schema-exhausted', payloadHash: str_repeat('d', 64)),
    );
    DB::table('sys_attribute_schema_operations')->where('id', $receipt->operation->id)->update([
        'attempts' => 5,
    ]);

    expect(fn (): mixed => schemaWorker()->run(jsonbScope(), $receipt->operation))
        ->toThrow(SchemaOperationConflict::class, 'The schema operation retry limit is exhausted.');

    $index = 'sys_attr_doc_'.substr(hash('sha256', "contact\nexhausted_score"), 0, 24);
    expect((array) DB::table('sys_attribute_schema_operations')->where('id', $receipt->operation->id)->first(['status', 'attempts']))->toMatchArray([
        'status' => 'pending',
        'attempts' => 5,
    ])->and(DB::table('pg_indexes')->where('indexname', $index)->exists())->toBeFalse();
})->group('pg');

it('requires a 30-day grace period and export digest before removing a managed index', function (): void {
    $hash = str_repeat('f', 64);
    $operations = queuedSchemaOperations($hash);
    $add = $operations->request(
        jsonbScope(),
        new SchemaChange(entity: 'contact', kind: 'add', expectedSchemaHash: $hash, definition: ['field' => 'retired_score'], approvalDigest: $hash),
        new SchemaOperationId(key: 'schema-retire-add', payloadHash: str_repeat('b', 64)),
    );
    schemaWorker()->run(jsonbScope(), $add->operation);

    $drop = $operations->request(
        jsonbScope(),
        new SchemaChange(entity: 'contact', kind: 'drop', expectedSchemaHash: $hash, definition: [
            'field' => 'retired_score',
            'retired_at' => now()->subDays(31)->toAtomString(),
            'export_hash' => str_repeat('c', 64),
        ], approvalDigest: $hash),
        new SchemaOperationId(key: 'schema-retire-drop', payloadHash: str_repeat('d', 64)),
    );
    schemaWorker()->run(jsonbScope(), $drop->operation);

    $index = 'sys_attr_doc_'.substr(hash('sha256', "contact\nretired_score"), 0, 24);

    expect($operations->inspect(jsonbScope(), $drop->operation)->status)->toBe('verified')
        ->and(DB::table('pg_indexes')->where('indexname', $index)->exists())->toBeFalse()
        ->and($operations->verify(jsonbScope(), 'contact')->status)->toBe('clean');
})->group('pg');
