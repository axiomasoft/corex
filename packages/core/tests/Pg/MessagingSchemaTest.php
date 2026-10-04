<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(__DIR__.'/../../database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->assertSuccessful();
});

it('creates messaging tables with expected columns', function (): void {
    expect(Schema::hasTable('sys_outbox_messages'))->toBeTrue()
        ->and(Schema::hasTable('sys_inbox_messages'))->toBeTrue();

    $outboxColumns = collect(Schema::getColumnListing('sys_outbox_messages'))->sort()->values()->all();
    $expectedOutbox = [
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
    expect($inboxColumns)->toBe(['consumer', 'message_id', 'name', 'processed_at']);
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

    expect(collect($columns)->pluck('column_name')->all())->toBe(['consumer', 'message_id']);
})->group('pg');

it('creates partial indexes on sys_outbox_messages hot paths', function (): void {
    $indexNames = [
        'sys_outbox_messages_claim_idx',
        'sys_outbox_messages_reclaim_idx',
        'sys_outbox_messages_published_idx',
        'sys_outbox_messages_dead_idx',
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

it('uses configured messaging table names for tables and indexes', function (): void {
    $originalOutboxTable = config('corex.tables.outbox_messages');
    $originalInboxTable = config('corex.tables.inbox_messages');
    $outboxTable = 'custom_outbox_messages';
    $inboxTable = 'custom_inbox_messages';

    try {
        config([
            'corex.tables.outbox_messages' => $outboxTable,
            'corex.tables.inbox_messages' => $inboxTable,
        ]);
        app()->forgetInstance(CoreOptions::class);

        DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
        DB::statement('CREATE SCHEMA public');
        $this->artisan('migrate', [
            '--database' => 'pgsql',
            '--path' => realpath(__DIR__.'/../../database/migrations/pg'),
            '--realpath' => true,
            '--force' => true,
        ])->assertSuccessful();

        expect(Schema::hasTable($outboxTable))->toBeTrue()
            ->and(Schema::hasTable($inboxTable))->toBeTrue()
            ->and(Schema::hasTable('sys_outbox_messages'))->toBeFalse()
            ->and(Schema::hasTable('sys_inbox_messages'))->toBeFalse();

        $outboxIndexes = DB::table('pg_indexes')
            ->where('tablename', $outboxTable)
            ->whereIn('indexname', [
                'sys_outbox_messages_claim_idx',
                'sys_outbox_messages_reclaim_idx',
                'sys_outbox_messages_published_idx',
                'sys_outbox_messages_dead_idx',
            ])
            ->pluck('indexdef', 'indexname');

        expect($outboxIndexes)->toHaveCount(4);
        foreach ($outboxIndexes as $indexDefinition) {
            expect($indexDefinition)->toContain('WHERE');
        }

        expect(DB::table('pg_indexes')
            ->where('tablename', $inboxTable)
            ->where('indexname', 'sys_inbox_messages_processed_idx')
            ->exists())->toBeTrue();
    } finally {
        config([
            'corex.tables.outbox_messages' => $originalOutboxTable,
            'corex.tables.inbox_messages' => $originalInboxTable,
        ]);
        app()->forgetInstance(CoreOptions::class);
    }
})->group('pg');

it('rejects duplicate inbox consumer and message_id pairs', function (): void {
    $now = now()->toIso8601String();

    DB::table('sys_inbox_messages')->insert([
        'consumer' => 'test-consumer',
        'message_id' => '01932f5a-0000-7000-8000-000000000001',
        'name' => 'example.event.v1',
        'processed_at' => $now,
    ]);

    DB::table('sys_inbox_messages')->insert([
        'consumer' => 'test-consumer',
        'message_id' => '01932f5a-0000-7000-8000-000000000001',
        'name' => 'example.event.v1',
        'processed_at' => $now,
    ]);
})->throws(QueryException::class)->group('pg');
