<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');
});

it('creates messaging tables with expected columns', function (): void {
    expect(Schema::hasTable('sys_outbox_messages'))->toBeTrue()
        ->and(Schema::hasTable('sys_inbox_messages'))->toBeTrue();

    $outboxColumns = collect(Schema::getColumnListing('sys_outbox_messages'))->sort()->values()->all();
    $expectedOutbox = [
        'account_id', 'account_incarnation', 'workspace_scope', 'deployment_id', 'binding_id', 'binding_generation', 'restore_epoch', 'digest', 'scope_seal', 'claim_token', 'message_id',
        'attempts',
        'available_at',
        'causation_id',
        'contract_version',
        'correlation_id',
        'created_at',
        'dead_at',
        'envelope_version',
        'failure_class',
        'id',
        'last_error',
        'lease_owner',
        'lease_until',
        'metadata',
        'name',
        'occurred_at',
        'payload',
        'pii_class',
        'published_at',
        'scope',
        'source',
        'status',
        'subject',
        'updated_at',
    ];
    sort($expectedOutbox);
    expect($outboxColumns)->toBe($expectedOutbox);

    $inboxColumns = collect(Schema::getColumnListing('sys_inbox_messages'))->sort()->values()->all();
    expect($inboxColumns)->toBe(['account_id', 'account_incarnation', 'binding_generation', 'binding_id', 'consumer', 'consumer_version', 'contract_version', 'deployment_id', 'digest', 'message_id', 'name', 'processed_at', 'restore_epoch', 'source', 'workspace_scope']);
})->group('pg');

it('defines a composite primary key on sys_inbox_messages', function (): void {
    $columns = DB::select(
        <<<'SQL'
        select kcu.column_name, kcu.ordinal_position
        from information_schema.table_constraints tc
        join information_schema.key_column_usage kcu
            on tc.constraint_name = kcu.constraint_name
            and tc.table_schema = kcu.table_schema
            and tc.table_name = kcu.table_name
        where tc.table_name = 'sys_inbox_messages'
          and tc.constraint_type = 'PRIMARY KEY'
        order by kcu.ordinal_position
        SQL,
    );

    expect(collect($columns)->pluck('column_name')->all())->toBe(['account_id', 'account_incarnation', 'workspace_scope', 'consumer', 'source', 'message_id']);
})->group('pg');

it('creates partial indexes on sys_outbox_messages hot paths', function (): void {
    $indexNames = [
        'sys_outbox_messages_claim_ix',
        'sys_outbox_messages_reclaim_ix',
        'sys_outbox_messages_published_ix',
        'sys_outbox_messages_dead_ix',
    ];

    foreach ($indexNames as $indexName) {
        $row = DB::selectOne(
            'select indexdef from pg_indexes where tablename = ? and indexname = ?',
            ['sys_outbox_messages', $indexName],
        );

        expect($row)->not->toBeNull()
            ->and(str_contains((string) $row->indexdef, 'WHERE'))->toBeTrue();
    }
})->group('pg');

it('indexes the shipped inbox table for retention', function (): void {
    expect(DB::table('pg_indexes')
        ->where('tablename', 'sys_inbox_messages')
        ->where('indexname', 'sys_inbox_messages_processed_ix')
        ->exists())->toBeTrue();
})->group('pg');

it('rejects duplicate inbox consumer and message_id pairs', function (): void {
    $now = now()->toIso8601String();

    DB::table('sys_inbox_messages')->insert([
        'account_id' => 'a', 'account_incarnation' => 'i', 'workspace_scope' => 'account', 'deployment_id' => 'd',
        'binding_id' => 'b', 'binding_generation' => 1, 'restore_epoch' => str_repeat('a', 64), 'digest' => str_repeat('f', 64),
        'source' => 'urn:fact', 'consumer_version' => 1, 'contract_version' => 1,
        'consumer' => 'test-consumer',
        'message_id' => '01932f5a-0000-7000-8000-000000000001',
        'name' => 'example.event.v1',
        'processed_at' => $now,
    ]);

    expect(DB::table('sys_inbox_messages')->count())->toBe(1);
    DB::table('sys_inbox_messages')->insert([
        'account_id' => 'a', 'account_incarnation' => 'i', 'workspace_scope' => 'account', 'deployment_id' => 'd',
        'binding_id' => 'b', 'binding_generation' => 1, 'restore_epoch' => str_repeat('a', 64), 'digest' => str_repeat('f', 64),
        'source' => 'urn:fact', 'consumer_version' => 1, 'contract_version' => 1,
        'consumer' => 'test-consumer',
        'message_id' => '01932f5a-0000-7000-8000-000000000001',
        'name' => 'example.event.v1',
        'processed_at' => $now,
    ]);
})->throws(QueryException::class)->group('pg');
