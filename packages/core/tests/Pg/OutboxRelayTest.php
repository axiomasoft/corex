<?php

declare(strict_types=1);

use CoreX\Config\Internal\Config;
use CoreX\Messaging\Data\LeasedMessage;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\OutboxLeaseLost;
use CoreX\Messaging\Internal\SqlOutboxRelay;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

const OUTBOX_RELAY_OWNER = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

const OUTBOX_RELAY_OTHER_OWNER = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

function outboxRelayMigrate(): void
{
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    test()->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(__DIR__.'/../../database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->assertSuccessful();
}

function outboxRelayClock(DateTimeImmutable $frozen): void
{
    app()->instance(ClockInterface::class, new FrozenClock($frozen));
}

function outboxRelayService(
    int $leaseSeconds = 120,
    int $maxAttempts = 8,
    int $backoffCapSeconds = 300,
): SqlOutboxRelay {
    return new SqlOutboxRelay(
        connection: DB::connection('pgsql'),
        clock: app(ClockInterface::class),
        leaseSeconds: $leaseSeconds,
        maxAttempts: $maxAttempts,
        backoffCapSeconds: $backoffCapSeconds,
    );
}

function outboxRelayInsertRow(array $overrides = []): string
{
    $payload = $overrides['payload'] ?? MessagePayload::empty();
    $storedEnvelopeVersion = $overrides['envelope_version'] ?? MessageEnvelope::ENVELOPE_VERSION;
    $envelope = new MessageEnvelope(
        messageId: $overrides['id'] ?? '01932f5a-0000-7000-8000-000000000001',
        source: 'axiomasoft/corex',
        name: 'membership.expired',
        contractVersion: 1,
        occurredAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        scope: ['accountId' => 'account-1'],
        payload: $payload,
        piiClass: PiiClass::None,
        subject: 'subject-1',
        correlationId: 'corr-1',
        causationId: 'cause-1',
        metadata: ['trace' => 't1'],
        envelopeVersion: MessageEnvelope::ENVELOPE_VERSION,
    );
    $now = app(ClockInterface::class)->now();
    $id = $envelope->messageId;

    DB::table(Config::outboxMessagesTable())->insert([
        'id' => $id,
        'name' => $envelope->name,
        'contract_version' => $envelope->contractVersion,
        'envelope_version' => $storedEnvelopeVersion,
        'source' => $envelope->source,
        'subject' => $envelope->subject,
        'correlation_id' => $envelope->correlationId,
        'causation_id' => $envelope->causationId,
        'pii_class' => $envelope->piiClass->value,
        'scope' => $overrides['scope_raw'] ?? json_encode($envelope->scope, JSON_THROW_ON_ERROR),
        'payload' => $overrides['payload_raw'] ?? $envelope->payload->encode(),
        'metadata' => json_encode($envelope->metadata, JSON_THROW_ON_ERROR),
        'occurred_at' => $envelope->occurredAt,
        'available_at' => $overrides['available_at'] ?? $now,
        'status' => $overrides['status'] ?? 'pending',
        'lease_owner' => $overrides['lease_owner'] ?? null,
        'lease_until' => $overrides['lease_until'] ?? null,
        'attempts' => $overrides['attempts'] ?? 0,
        'failure_class' => $overrides['failure_class'] ?? null,
        'last_error' => $overrides['last_error'] ?? null,
        'published_at' => $overrides['published_at'] ?? null,
        'dead_at' => $overrides['dead_at'] ?? null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $id;
}

beforeEach(function (): void {
    outboxRelayMigrate();
    outboxRelayClock(new DateTimeImmutable('2026-06-15T12:00:00+00:00'));
});

it('leases a pending row into publishing with owner, lease window, and attempt count', function (): void {
    $id = outboxRelayInsertRow();
    $relay = outboxRelayService();
    $now = app(ClockInterface::class)->now();

    $leased = $relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 10);

    expect($leased)->toHaveCount(1)
        ->and($leased[0])->toBeInstanceOf(LeasedMessage::class)
        ->and($leased[0]->leaseOwner)->toBe(OUTBOX_RELAY_OWNER)
        ->and($leased[0]->attempt)->toBe(1)
        ->and($leased[0]->leaseUntil->getTimestamp())->toBe($now->modify('+120 seconds')->getTimestamp())
        ->and($leased[0]->envelope->messageId)->toBe($id);

    $row = DB::table(Config::outboxMessagesTable())->where('id', $id)->first();

    expect($row->status)->toBe('publishing')
        ->and($row->lease_owner)->toBe(OUTBOX_RELAY_OWNER)
        ->and($row->attempts)->toBe(1);
})->group('pg');

it('does not lease rows with a future available_at', function (): void {
    $now = app(ClockInterface::class)->now();
    outboxRelayInsertRow(['available_at' => $now->modify('+1 hour')]);

    $leased = outboxRelayService()->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 10);

    expect($leased)->toBe([])
        ->and(DB::table(Config::outboxMessagesTable())->value('status'))->toBe('pending');
})->group('pg');

it('reclaims expired publishing rows, marks exhausted leases dead, and fences corrupt envelopes', function (): void {
    $now = app(ClockInterface::class)->now();
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

    $deadRow = DB::table(Config::outboxMessagesTable())->where('id', $deadId)->first();
    expect($deadRow->status)->toBe('dead')
        ->and($deadRow->failure_class)->toBe('lease-expiry-attempts-exhausted');
})->group('pg');

it('fences corrupt envelope reconstruction through the same backoff budget', function (): void {
    $corruptId = outboxRelayInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000004',
        'scope_raw' => '[]',
    ]);
    $relay = outboxRelayService(maxAttempts: 1);

    expect($relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 1))->toBe([]);

    $row = DB::table(Config::outboxMessagesTable())->where('id', $corruptId)->first();
    expect($row->status)->toBe('dead')
        ->and($row->attempts)->toBe(1)
        ->and($row->failure_class)->toBe('attempts-exhausted');
})->group('pg');

it('refuses markPublished when the lease owner no longer matches', function (): void {
    $id = outboxRelayInsertRow();
    $relay = outboxRelayService();
    $leased = $relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 1)[0];

    $wrongOwner = new LeasedMessage(
        envelope: $leased->envelope,
        leaseOwner: OUTBOX_RELAY_OTHER_OWNER,
        attempt: $leased->attempt,
        leaseUntil: $leased->leaseUntil,
    );

    expect(fn (): mixed => $relay->markPublished($wrongOwner))
        ->toThrow(OutboxLeaseLost::class, 'may already have reached its destination');

    expect(DB::table(Config::outboxMessagesTable())->where('id', $id)->value('status'))->toBe('publishing');
})->group('pg');

function outboxRelayLeasedMessageFromRow(string $id, int $attempt): LeasedMessage
{
    $row = DB::table(Config::outboxMessagesTable())->where('id', $id)->first();
    $envelope = new MessageEnvelope(
        messageId: (string) $row->id,
        source: (string) $row->source,
        name: (string) $row->name,
        contractVersion: (int) $row->contract_version,
        occurredAt: new DateTimeImmutable((string) $row->occurred_at),
        scope: json_decode((string) $row->scope, true, flags: JSON_THROW_ON_ERROR),
        payload: MessagePayload::empty(),
        piiClass: PiiClass::from((string) $row->pii_class),
        subject: $row->subject === null ? null : (string) $row->subject,
        correlationId: $row->correlation_id === null ? null : (string) $row->correlation_id,
        causationId: $row->causation_id === null ? null : (string) $row->causation_id,
        metadata: json_decode((string) $row->metadata, true, flags: JSON_THROW_ON_ERROR),
        envelopeVersion: (int) $row->envelope_version,
    );

    return new LeasedMessage(
        envelope: $envelope,
        leaseOwner: (string) $row->lease_owner,
        attempt: $attempt,
        leaseUntil: new DateTimeImmutable((string) $row->lease_until),
    );
}

function outboxRelaySeedPublishing(string $id, int $attempt): void
{
    $now = app(ClockInterface::class)->now();
    outboxRelayInsertRow(['id' => $id, 'attempts' => $attempt]);
    $updated = DB::table(Config::outboxMessagesTable())->where('id', $id)->update([
        'status' => 'publishing',
        'lease_owner' => OUTBOX_RELAY_OWNER,
        'lease_until' => $now->modify('+120 seconds'),
        'updated_at' => $now,
    ]);

    expect($updated)->toBe(1);
}

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
        outboxRelaySeedPublishing(id: $messageId, attempt: $attempt + 1);

        $relay->releaseWithBackoff(
            outboxRelayLeasedMessageFromRow(id: $messageId, attempt: $attempt + 1),
            new RuntimeException('publish-failed'),
        );

        $availableAt = new DateTimeImmutable((string) DB::table(Config::outboxMessagesTable())->where('id', $messageId)->value('available_at'));
        expect($availableAt->getTimestamp())->toBe($baseline->modify("+{$delay} seconds")->getTimestamp());
    }
})->group('pg');

it('marks a row dead after the eighth failed attempt', function (): void {
    $now = app(ClockInterface::class)->now();
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

    $row = DB::table(Config::outboxMessagesTable())->where('id', $id)->first();
    expect($row->status)->toBe('dead')
        ->and($row->dead_at)->not->toBeNull()
        ->and($row->failure_class)->toBe('attempts-exhausted');
})->group('pg');

it('truncates last_error to two thousand characters', function (): void {
    outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000008']);
    $relay = outboxRelayService();
    $leased = $relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 1)[0];

    $relay->releaseWithBackoff($leased, new RuntimeException(str_repeat('x', 2_500)));

    $error = (string) DB::table(Config::outboxMessagesTable())->value('last_error');
    expect(mb_strlen($error))->toBe(2_000);
})->group('pg');

it('skips locked pending rows with SKIP LOCKED and blocks without it', function (): void {
    $id = outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000007']);
    $table = Config::outboxMessagesTable();
    $connection = config('database.connections.pgsql');
    $dsn = "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}";
    $second = new PDO($dsn, $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    DB::beginTransaction();
    DB::table($table)->where('id', $id)->lock('for update skip locked')->first();

    $skipped = $second->query(
        "SELECT id FROM {$table} WHERE status = 'pending' FOR UPDATE SKIP LOCKED",
    )->fetchAll();

    expect($skipped)->toBe([]);

    $second->exec("SET lock_timeout = '100ms'");

    try {
        expect(fn (): mixed => $second->query("SELECT id FROM {$table} WHERE id = '{$id}' FOR UPDATE"))
            ->toThrow(PDOException::class);
    } finally {
        DB::rollBack();
    }
})->group('pg');
