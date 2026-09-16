<?php

declare(strict_types=1);

use CoreX\Notifications\Tests\PgTestCase;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('holds a PostgreSQL delivery row lock across independent connections', function (): void {
    $id = '11111111-1111-7111-8111-111111111111';
    $notification = '22222222-2222-7222-8222-222222222222';
    DB::table('ntf_notifications')->insert([
        'id' => $notification,
        'recipient_kind' => 'user',
        'recipient_id' => 'recipient-1',
        'request_key' => 'pg-delivery-lock',
        'request_digest' => hash('sha256', 'pg-delivery-lock'),
        'event_type' => 'document.ready',
        'template_key' => 'document-ready',
        'payload' => json_encode([], JSON_THROW_ON_ERROR),
        'revision' => '1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('ntf_deliveries')->insert([
        'id' => $id,
        'notification_id' => $notification,
        'channel' => 'in_app',
        'delivery_key' => hash('sha256', 'pg-delivery-lock:in_app'),
        'state' => 'pending',
        'claim_epoch' => '0',
        'attempts' => 0,
        'admitted_revision' => '1',
    ]);

    $connection = config('database.connections.pgsql');
    $dsn = "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}";
    $second = new PDO($dsn, $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    DB::beginTransaction();
    DB::table('ntf_deliveries')->where('id', $id)->lockForUpdate()->first();
    $second->exec("SET lock_timeout = '100ms'");

    try {
        expect(fn (): bool => $second->query("SELECT id FROM ntf_deliveries WHERE id = '{$id}' FOR UPDATE") !== false)->toThrow(PDOException::class);
    } finally {
        DB::rollBack();
    }
});
