<?php

declare(strict_types=1);

use CoreX\Support\Config;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    test()->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(__DIR__.'/../../database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->assertSuccessful();
});

it('inserts exactly one inbox row when two connections race on the same key', function (): void {

    $table = Config::inboxMessagesTable();
    $consumer = 'inbox.concurrency.v1';
    $messageId = '55555555-5555-7555-8555-555555555555';
    $name = 'membership.expired';
    $processedAt = '2026-06-15T12:00:00+00:00';

    $connection = config('database.connections.pgsql');
    $dsn = "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}";
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

    $first = new PDO($dsn, $connection['username'], $connection['password'], $options);
    $second = new PDO($dsn, $connection['username'], $connection['password'], $options);

    $sql = <<<SQL
        INSERT INTO {$table} (consumer, message_id, name, processed_at)
        VALUES (:consumer, :message_id::uuid, :name, :processed_at::timestamptz)
        ON CONFLICT DO NOTHING
        SQL;

    // Autocommit on two independent connections: no open txn on A while B inserts
    // (holding A's row uncommitted would block B forever in this single-threaded test).
    $stmtA = $first->prepare($sql);
    $stmtA->execute([
        'consumer' => $consumer,
        'message_id' => $messageId,
        'name' => $name,
        'processed_at' => $processedAt,
    ]);
    $insertedA = $stmtA->rowCount();

    $stmtB = $second->prepare($sql);
    $stmtB->execute([
        'consumer' => $consumer,
        'message_id' => $messageId,
        'name' => $name,
        'processed_at' => $processedAt,
    ]);
    $insertedB = $stmtB->rowCount();

    expect([$insertedA, $insertedB])->toContain(1)->toContain(0)
        ->and(DB::connection('pgsql')->table($table)->count())->toBe(1);
})->group('pg');
