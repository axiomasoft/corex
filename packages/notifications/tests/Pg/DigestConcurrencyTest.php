<?php

declare(strict_types=1);

use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Notifications\Tests\PgTestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('holds a PostgreSQL frozen digest run lock across independent tick connections', function (): void {
    $notificationId = '22222222-2222-7222-8222-222222222222';
    $runId = '33333333-3333-7333-8333-333333333333';
    DB::table('ntf_notifications')->insert([
        'account_id' => 'account-1',
        'account_incarnation' => 'incarnation-1',
        'workspace_scope' => 'account',
        'source' => 'pg-fixture',
        'id' => $notificationId,
        'recipient_kind' => 'user',
        'recipient_id' => 'recipient-1',
        'event_id' => 'pg-digest-lock',
        'request_digest' => hash('sha256', 'pg-digest-lock'),
        'event_type' => 'document.ready',
        'template_action' => 'document-ready',
        'payload' => json_encode([], JSON_THROW_ON_ERROR),
        'revision' => '1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $scope = (new ArrayDataScopeCodec)->encode(
        CallerWorkScopeFixture::scope('digest-concurrency', null, 'recipient', str_repeat('a', 64)),
    );
    $scopeJson = json_encode($scope, JSON_THROW_ON_ERROR);
    DB::table('ntf_digest_runs')->insert([
        'account_id' => 'account-1',
        'account_incarnation' => 'incarnation-1',
        'workspace_scope' => 'account',
        'scope_key' => hash('sha256', $scopeJson),
        'scope' => $scopeJson,
        'scope_seal' => str_repeat('a', 64),
        'id' => $runId,
        'recipient_kind' => 'user',
        'recipient_id' => 'recipient-1',
        'period' => 'hourly',
        'period_start' => now()->subHour(),
        'period_end' => now(),
        'batch_seq' => 0,
        'membership_hash' => hash('sha256', $notificationId),
        'state' => 'frozen',
        'created_at' => now(),
    ]);
    DB::table('ntf_digest_items')->insert([
        'id' => '44444444-4444-7444-8444-444444444444',
        'run_id' => $runId,
        'notification_id' => $notificationId,
        'channel' => 'in_app',
    ]);

    $connection = config('database.connections.pgsql');
    $dsn = "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}";
    $second = new PDO($dsn, $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    DB::beginTransaction();
    DB::table('ntf_digest_runs')->where('id', $runId)->lockForUpdate()->first();
    $second->exec("SET lock_timeout = '100ms'");

    try {
        expect(fn (): bool => $second->query("SELECT id FROM ntf_digest_runs WHERE id = '{$runId}' FOR UPDATE") !== false)->toThrow(PDOException::class);
    } finally {
        DB::rollBack();
    }
});
