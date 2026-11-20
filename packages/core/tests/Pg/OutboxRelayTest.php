<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/Messaging.php';

use CoreX\Messaging\Data\LeasedMessage;
use CoreX\Messaging\Exceptions\OutboxLeaseLost;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

beforeEach(function (): void {
    outboxRelayMigrate();
    outboxRelayClock(new DateTimeImmutable('2026-06-15T12:00:00+00:00'));
});

it('leases a pending row into publishing with owner, lease window, and attempt count', function (): void {
    $id = outboxRelayInsertRow();
    $relay = outboxRelayService();
    $now = resolve(ClockInterface::class)->now();

    $leased = $relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 10);

    expect($leased)->toHaveCount(1)
        ->and($leased[0])->toBeInstanceOf(LeasedMessage::class)
        ->and($leased[0]->leaseOwner)->toBe(OUTBOX_RELAY_OWNER)
        ->and($leased[0]->attempt)->toBe(1)
        ->and($leased[0]->leaseUntil->getTimestamp())->toBe($now->modify('+120 seconds')->getTimestamp())
        ->and($leased[0]->envelope->messageId)->toBe($id);

    $row = DB::table('sys_outbox_messages')->where('message_id', $id)->first();

    expect($row->status)->toBe('publishing')
        ->and($row->lease_owner)->toBe(OUTBOX_RELAY_OWNER)
        ->and($row->attempts)->toBe(1);
})->group('pg');

it('does not lease rows with a future available_at', function (): void {
    $now = resolve(ClockInterface::class)->now();
    outboxRelayInsertRow(['available_at' => $now->modify('+1 hour')]);

    $leased = outboxRelayService()->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 10);

    expect($leased)->toBe([])
        ->and(DB::table('sys_outbox_messages')->value('status'))->toBe('pending');
})->group('pg');

it('reclaims expired publishing rows, marks exhausted leases dead, and fences corrupt envelopes', function (): void {
    $now = resolve(ClockInterface::class)->now();
    $reclaimId = outboxRelayInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000002',
        'status' => 'publishing',
        'lease_owner' => OUTBOX_RELAY_OTHER_OWNER,
        'lease_until' => $now->modify('-1 minute'),
        'attempts' => 2,
    ]);
    $deadId = outboxRelayInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000003',
        'status' => 'publishing',
        'lease_owner' => OUTBOX_RELAY_OTHER_OWNER,
        'lease_until' => $now->modify('-1 minute'),
        'attempts' => 8,
    ]);
    $relay = outboxRelayService(maxAttempts: 8);
    $leased = $relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 1);

    expect($leased)->toHaveCount(1)
        ->and($leased[0]->envelope->messageId)->toBe($reclaimId)
        ->and($leased[0]->attempt)->toBe(3);

    $relay->markPublished($leased[0]);

    $deadRow = DB::table('sys_outbox_messages')->where('message_id', $deadId)->first();
    expect($deadRow->status)->toBe('dead')
        ->and($deadRow->failure_class)->toBe('lease-expiry-attempts-exhausted');
})->group('pg');

it('quarantines corrupt envelopes without spending the rest of the delivery budget', function (): void {
    $corruptId = outboxRelayInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000004',
        'scope_raw' => '[]',
    ]);
    $relay = outboxRelayService(maxAttempts: 1);

    expect($relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 1))->toBe([]);

    $row = DB::table('sys_outbox_messages')->where('message_id', $corruptId)->first();
    expect($row->status)->toBe('quarantined')
        ->and($row->attempts)->toBe(1)
        ->and($row->failure_class)->toBe('invalid-fact');
})->group('pg');

it('refuses markPublished when the lease owner no longer matches', function (): void {
    $id = outboxRelayInsertRow();
    $relay = outboxRelayService();
    $leased = $relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 1)[0];

    $wrongOwner = new LeasedMessage(
        envelope: $leased->envelope,
        leaseOwner: OUTBOX_RELAY_OTHER_OWNER,
        attempt: $leased->attempt,
        leaseUntil: $leased->leaseUntil, rowId: $leased->rowId, claimToken: $leased->claimToken,
    );

    expect(fn (): mixed => $relay->markPublished($wrongOwner))
        ->toThrow(OutboxLeaseLost::class, 'may already have reached its destination');

    expect(DB::table('sys_outbox_messages')->where('message_id', $id)->value('status'))->toBe('publishing');
})->group('pg');

it('applies deterministic exponential backoff on the first four publish failures', function (): void {
    $relay = outboxRelayService(maxAttempts: 8);
    $baseline = new DateTimeImmutable('2026-06-15T12:00:00+00:00');
    outboxRelayClock($baseline);
    $expectedDelays = [2, 4, 8, 16];

    $messageIds = [
        '01932f5a-0000-7000-8000-000000000011',
        '01932f5a-0000-7000-8000-000000000012',
        '01932f5a-0000-7000-8000-000000000013',
        '01932f5a-0000-7000-8000-000000000014',
    ];

    foreach ($expectedDelays as $attempt => $delay) {
        $messageId = $messageIds[$attempt];
        outboxRelayInsertRow(['id' => $messageId, 'attempts' => $attempt]);
        $leased = $relay->lease(OUTBOX_RELAY_OWNER, 1);
        expect($leased)->toHaveCount(1);

        $relay->releaseWithBackoff(
            $leased[0],
            new RuntimeException('publish-failed'),
        );

        $availableAt = new DateTimeImmutable((string) DB::table('sys_outbox_messages')->where('message_id', $messageId)->value('available_at'));
        expect($availableAt->getTimestamp())->toBe($baseline->modify("+{$delay} seconds")->getTimestamp());
    }
})->group('pg');

it('marks a row dead after the eighth failed attempt', function (): void {
    $now = resolve(ClockInterface::class)->now();
    $id = outboxRelayInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000006',
        'attempts' => 7,
        'available_at' => $now,
    ]);
    $relay = outboxRelayService(maxAttempts: 8);

    $leased = $relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 1);
    expect($leased)->toHaveCount(1)
        ->and($leased[0]->attempt)->toBe(8);

    $relay->releaseWithBackoff($leased[0], new RuntimeException('publish-failed'));

    $row = DB::table('sys_outbox_messages')->where('message_id', $id)->first();
    expect($row->status)->toBe('dead')
        ->and($row->dead_at)->not->toBeNull()
        ->and($row->failure_class)->toBe('attempts-exhausted');
})->group('pg');

it('stores only the safe error class from an external failure', function (): void {
    outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000008']);
    $relay = outboxRelayService();
    $leased = $relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 1)[0];

    $relay->releaseWithBackoff($leased, new RuntimeException(str_repeat('x', 2_500)));

    $error = (string) DB::table('sys_outbox_messages')->value('last_error');
    expect($error)->toBe(RuntimeException::class)->not->toContain('xxx');
})->group('pg');

it('skips locked pending rows with SKIP LOCKED and blocks without it', function (): void {
    $id = outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000007']);
    $table = 'sys_outbox_messages';
    $connection = config('database.connections.pgsql');
    $dsn = "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}";
    $second = new PDO($dsn, $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    DB::beginTransaction();
    DB::table($table)->where('message_id', $id)->lock('for update skip locked')->first();

    $skipped = $second->query(
        "SELECT id FROM {$table} WHERE status = 'pending' FOR UPDATE SKIP LOCKED",
    )->fetchAll();

    expect($skipped)->toBe([]);

    $second->exec("SET lock_timeout = '100ms'");

    try {
        expect(fn (): mixed => $second->query("SELECT id FROM {$table} WHERE message_id = '{$id}' FOR UPDATE"))
            ->toThrow(PDOException::class);
    } finally {
        DB::rollBack();
    }
})->group('pg');

it('invalidates the old token when the same worker name reclaims and never publishes cancelled work', function (): void {
    $id = outboxRelayInsertRow();
    $first = outboxRelayService(leaseSeconds: 10)->lease(OUTBOX_RELAY_OWNER, 1)[0];
    outboxRelayClock($first->leaseUntil->modify('+1 second'));
    $relay = outboxRelayService(leaseSeconds: 10);
    $second = $relay->lease(OUTBOX_RELAY_OWNER, 1)[0];
    expect($second->claimToken)->not->toBe($first->claimToken)
        ->and(fn () => $relay->markPublished($first))->toThrow(OutboxLeaseLost::class);
    DB::table('sys_outbox_messages')->where('message_id', $id)->update(['status' => 'cancelled']);
    expect(fn () => $relay->assertClaim($second))->toThrow(OutboxLeaseLost::class)
        ->and(fn () => $relay->markPublished($second))->toThrow(OutboxLeaseLost::class)
        ->and($relay->lease(OUTBOX_RELAY_OTHER_OWNER, 10))->toBe([]);
})->group('pg');
