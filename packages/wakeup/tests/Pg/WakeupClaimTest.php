<?php

declare(strict_types=1);

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Wakeup\Contracts\DueWakeupHandler;
use CoreX\Wakeup\Contracts\DueWakeupSweeper;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use CoreX\Wakeup\Data\WakeupDelivery;
use CoreX\Wakeup\Data\WakeupRequest;
use CoreX\Wakeup\Tests\PgTestCase;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

final class PgVersionedWakeupHandler implements DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void
    {
        DB::table('wakeup_effects')->insert(['request_key' => $delivery->request->requestKey]);
    }
}

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

it('persists and dispatches a versioned wakeup envelope on PostgreSQL', function (): void {
    app()['config']->set('corex-wakeup.handlers', [
        'test.pg-versioned' => [
            'handler' => PgVersionedWakeupHandler::class,
            'version' => 'pg-versioned/1',
        ],
    ]);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();

    app(WakeupScheduler::class)->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.pg-versioned',
        requestKey: 'pg-versioned-envelope',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: ['message' => 'wake'],
    ));

    $payload = json_decode(
        (string) DB::table('sys_wakeup_requests')->where('request_key', 'pg-versioned-envelope')->value('payload'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $result = app(DueWakeupSweeper::class)->sweep($context, 10);

    expect($payload['_corex']['extension']['version'])->toBe('pg-versioned/1')
        ->and($payload['data'])->toBe(['message' => 'wake'])
        ->and($result->completed)->toBe(1)
        ->and(DB::table('wakeup_effects')->where('request_key', 'pg-versioned-envelope')->count())->toBe(1);
});
