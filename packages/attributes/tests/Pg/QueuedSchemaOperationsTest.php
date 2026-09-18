<?php

declare(strict_types=1);

use CoreX\Attributes\Internal\Engine\QueuedSchemaOperations;
use CoreX\Attributes\Internal\Engine\SchemaOperationWorker;
use CoreX\Attributes\Internal\ScopeDenied;
use CoreX\Attributes\Support\Schema\OperationKey;
use CoreX\Attributes\Support\Schema\SchemaChange;
use CoreX\Attributes\Tests\PgTestCase;
use CoreX\Attributes\Tests\Support\PgTestSupport;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Support\Data\CallableDataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class);

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
    $key = new OperationKey(key: 'schema-1', payloadHash: str_repeat('a', 64));

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
    $key = new OperationKey(key: 'schema-replay', payloadHash: str_repeat('a', 64));

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
        new OperationKey(key: 'schema-reclaim', payloadHash: str_repeat('b', 64)),
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
        new OperationKey(key: 'schema-drift', payloadHash: str_repeat('c', 64)),
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
        new OperationKey(key: 'schema-stale-epoch', payloadHash: str_repeat('e', 64)),
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
        new OperationKey(key: 'schema-unapproved', payloadHash: str_repeat('d', 64)),
    ))->toThrow(ScopeDenied::class);

    expect(DB::table('sys_attribute_schema_operations')->count())->toBe(0);
})->group('pg');

it('requires a 30-day grace period and export digest before removing a managed index', function (): void {
    $hash = str_repeat('f', 64);
    $operations = queuedSchemaOperations($hash);
    $add = $operations->request(
        jsonbScope(),
        new SchemaChange(entity: 'contact', kind: 'add', expectedSchemaHash: $hash, definition: ['field' => 'retired_score'], approvalDigest: $hash),
        new OperationKey(key: 'schema-retire-add', payloadHash: str_repeat('b', 64)),
    );
    schemaWorker()->run(jsonbScope(), $add->operation);

    $drop = $operations->request(
        jsonbScope(),
        new SchemaChange(entity: 'contact', kind: 'drop', expectedSchemaHash: $hash, definition: [
            'field' => 'retired_score',
            'retired_at' => now()->subDays(31)->toAtomString(),
            'export_hash' => str_repeat('c', 64),
        ], approvalDigest: $hash),
        new OperationKey(key: 'schema-retire-drop', payloadHash: str_repeat('d', 64)),
    );
    schemaWorker()->run(jsonbScope(), $drop->operation);

    $index = 'sys_attr_doc_'.substr(hash('sha256', "contact\nretired_score"), 0, 24);

    expect($operations->inspect(jsonbScope(), $drop->operation)->status)->toBe('verified')
        ->and(DB::table('pg_indexes')->where('indexname', $index)->exists())->toBeFalse()
        ->and($operations->verify(jsonbScope(), 'contact')->status)->toBe('clean');
})->group('pg');
