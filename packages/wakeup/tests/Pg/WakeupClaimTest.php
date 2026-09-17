<?php

declare(strict_types=1);

use CoreX\Wakeup\Tests\PgTestCase;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('holds one due wakeup claim lock across independent PostgreSQL connections', function (): void {
    $id = '11111111-1111-7111-8111-111111111111';
    DB::table('sys_wakeup_requests')->insert([
        'id' => $id,
        'workspace_scope_key' => 'account',
        'kind' => 'test.reminder',
        'request_key' => 'pg-claim-lock',
        'request_digest' => hash('sha256', 'pg-claim-lock'),
        'fire_at' => now()->subMinute(),
        'payload' => json_encode([], JSON_THROW_ON_ERROR),
        'status' => 'pending',
        'generation' => 0,
        'attempt' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $connection = config('database.connections.pgsql');
    $dsn = "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}";
    $second = new PDO($dsn, $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    DB::beginTransaction();
    DB::table('sys_wakeup_requests')->where('id', $id)->lock('for update skip locked')->first();
    $second->exec("SET lock_timeout = '100ms'");

    try {
        expect(fn (): bool => $second->query("SELECT id FROM sys_wakeup_requests WHERE id = '{$id}' FOR UPDATE") !== false)
            ->toThrow(PDOException::class);
    } finally {
        DB::rollBack();
    }
});
