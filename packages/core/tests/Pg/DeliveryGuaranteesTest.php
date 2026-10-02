<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\MessagePublisher;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Internal\SqlOutboxRelay;
use CoreX\Testing\Fixtures\RecordingPublisher;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Psr\Clock\ClockInterface;

const DG_RELAY_OWNER_A = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

const DG_RELAY_OWNER_B = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';

function deliveryGuaranteesBindPublisher(DurableMessagePublisher $publisher): void
{
    app()->instance(DurableMessagePublisher::class, $publisher);
    app()->instance(MessagePublisher::class, $publisher);
}

function deliveryGuaranteesInstallBusinessTable(): void
{
    Schema::connection('pgsql')->create('dg_business_effects', function (Blueprint $table): void {
        $table->uuid('message_id')->primary();
        $table->string('consumer', 120);
        $table->timestampsTz();
    });
}

function deliveryGuaranteesInstallDurableStore(): void
{
    Schema::connection('pgsql')->create('dg_durable_store', function (Blueprint $table): void {
        $table->uuid('message_id')->primary();
        $table->string('name', 160);
        $table->text('payload');
        $table->timestampTz('stored_at');
    });
}

/**
 * @return list<bool>
 */
function deliveryGuaranteesConsume(MessageEnvelope $envelope, string $consumer = 'delivery.guarantees.v1'): array
{
    $inbox = app(Inbox::class);
    $claims = [];

    DB::connection('pgsql')->transaction(function () use ($inbox, $envelope, $consumer, &$claims): void {
        $claims[] = $inbox->claim(
            consumer: $consumer,
            messageId: $envelope->messageId,
            name: $envelope->name,
        );

        if ($claims[array_key_last($claims)] === false) {
            return;
        }

        DB::connection('pgsql')->table('dg_business_effects')->insert([
            'message_id' => $envelope->messageId,
            'consumer' => $consumer,
            'created_at' => app(ClockInterface::class)->now(),
            'updated_at' => app(ClockInterface::class)->now(),
        ]);
    });

    return $claims;
}

function deliveryGuaranteesRelayOnConnection(string $connectionName): SqlOutboxRelay
{
    return new SqlOutboxRelay(
        connection: DB::connection($connectionName),
        clock: app(ClockInterface::class),
        leaseSeconds: 120,
        maxAttempts: 8,
        backoffCapSeconds: 300,
    );
}

beforeEach(function (): void {
    outboxRelayMigrate();
    outboxRelayClock(new DateTimeImmutable('2026-06-15T12:00:00+00:00'));
    outboxAppendBindRegistry(outboxAppendDeclaredContract());
    deliveryGuaranteesInstallBusinessTable();
    deliveryGuaranteesInstallDurableStore();

    config([
        'database.connections.dg_relay_a' => array_merge(config('database.connections.pgsql'), ['name' => 'dg_relay_a']),
        'database.connections.dg_relay_b' => array_merge(config('database.connections.pgsql'), ['name' => 'dg_relay_b']),
    ]);
});

it('proves at-least-once when the relay dies after durable publish but before markPublished', function (): void {
    $messageId = outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000101']);
    $relay = outboxRelayService(leaseSeconds: 60);
    $leased = $relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 1);
    expect($leased)->toHaveCount(1);

    $publisher = new RecordingPublisher(mode: RecordingPublisher::MODE_THROW_AFTER_RECORD);
    deliveryGuaranteesBindPublisher($publisher);

    try {
        $publisher->publish($leased[0]->envelope);
    } catch (RuntimeException) {
        // Relay process died before markPublished; no releaseWithBackoff — crash, not defer.
    }

    expect(DB::table(app(CoreOptions::class)->outboxMessagesTable)->where('id', $messageId)->value('status'))->toBe('publishing')
        ->and($publisher->recordedMessageIds)->toBe([$messageId]);

    outboxRelayClock(new DateTimeImmutable('2026-06-15T13:05:00+00:00'));

    $rowBeforeReclaim = DB::table(app(CoreOptions::class)->outboxMessagesTable)->where('id', $messageId)->first();
    expect($rowBeforeReclaim->status)->toBe('publishing');

    $reclaimRelay = outboxRelayService(leaseSeconds: 60);
    $secondLease = $reclaimRelay->lease(leaseOwner: OUTBOX_RELAY_OTHER_OWNER, batchSize: 1);
    expect($secondLease)->toHaveCount(1)
        ->and($secondLease[0]->envelope->messageId)->toBe($messageId);

    $publisher->mode = RecordingPublisher::MODE_SUCCEED;
    $publisher->publish($secondLease[0]->envelope);
    $reclaimRelay->markPublished($secondLease[0]);

    expect($publisher->recordedMessageIds)->toBe([$messageId, $messageId]);
})->group('pg');

it('does not repeat the business effect when the same messageId is consumed twice', function (): void {
    $envelope = messagingSampleEnvelope(['messageId' => '01932f5a-0000-7000-8000-000000000102']);

    $firstClaim = deliveryGuaranteesConsume($envelope)[0];
    $secondClaim = deliveryGuaranteesConsume($envelope)[0];

    expect($firstClaim)->toBeTrue()
        ->and($secondClaim)->toBeFalse()
        ->and(DB::table('dg_business_effects')->count())->toBe(1);
})->group('pg');

it('partitions concurrent relay leases without overlap or blocking', function (): void {
    foreach (range(1, 4) as $index) {
        outboxRelayInsertRow(['id' => sprintf('01932f5a-0000-7000-8000-0000000001%02d', $index)]);
    }

    $relayA = deliveryGuaranteesRelayOnConnection('dg_relay_a');
    $relayB = deliveryGuaranteesRelayOnConnection('dg_relay_b');

    $leasedA = $relayA->lease(leaseOwner: DG_RELAY_OWNER_A, batchSize: 2);
    $leasedB = $relayB->lease(leaseOwner: DG_RELAY_OWNER_B, batchSize: 2);

    $idsA = array_map(static fn ($message) => $message->envelope->messageId, $leasedA);
    $idsB = array_map(static fn ($message) => $message->envelope->messageId, $leasedB);

    expect($idsA)->toHaveCount(2)
        ->and($idsB)->toHaveCount(2)
        ->and(array_intersect($idsA, $idsB))->toBe([]);

    $heldId = outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000120']);
    $table = app(CoreOptions::class)->outboxMessagesTable;

    DB::connection('dg_relay_a')->beginTransaction();
    DB::connection('dg_relay_a')->table($table)->where('id', $heldId)->lockForUpdate()->first();

    $skippedWhileHeld = $relayB->lease(leaseOwner: DG_RELAY_OWNER_B, batchSize: 10);
    $skippedIds = array_map(static fn ($message) => $message->envelope->messageId, $skippedWhileHeld);

    expect($skippedIds)->not->toContain($heldId);

    DB::connection('dg_relay_a')->rollBack();
})->group('pg');

it('rolls back outbox rows with the business transaction and commits both on success', function (): void {
    $outbox = app(Outbox::class);
    $messageId = '01932f5a-0000-7000-8000-000000000103';

    try {
        DB::connection('pgsql')->transaction(function () use ($outbox, $messageId): void {
            DB::connection('pgsql')->table('dg_business_effects')->insert([
                'message_id' => $messageId,
                'consumer' => 'rollback.probe',
                'created_at' => app(ClockInterface::class)->now(),
                'updated_at' => app(ClockInterface::class)->now(),
            ]);
            $outbox->append(messagingSampleEnvelope(['messageId' => $messageId]));

            throw new RuntimeException('force-rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('force-rollback');
    }

    expect(DB::table('dg_business_effects')->count())->toBe(0)
        ->and(DB::table(app(CoreOptions::class)->outboxMessagesTable)->count())->toBe(0);

    DB::connection('pgsql')->transaction(function () use ($outbox, $messageId): void {
        DB::connection('pgsql')->table('dg_business_effects')->insert([
            'message_id' => $messageId,
            'consumer' => 'rollback.probe',
            'created_at' => app(ClockInterface::class)->now(),
            'updated_at' => app(ClockInterface::class)->now(),
        ]);
        $outbox->append(messagingSampleEnvelope(['messageId' => $messageId]));
    });

    expect(DB::table('dg_business_effects')->count())->toBe(1)
        ->and(DB::table(app(CoreOptions::class)->outboxMessagesTable)->count())->toBe(1);
})->group('pg');

it('hands off durably to a restarted consumer and rejects lossy publishers before markPublished', function (): void {
    $durablePublisher = new class implements DurableMessagePublisher
    {
        public function publish(MessageEnvelope $message): void
        {
            DB::connection('pgsql')->table('dg_durable_store')->insert([
                'message_id' => $message->messageId,
                'name' => $message->name,
                'payload' => $message->payload->encode(),
                'stored_at' => app(ClockInterface::class)->now(),
            ]);
        }
    };
    deliveryGuaranteesBindPublisher($durablePublisher);

    $messageId = outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000104']);
    Artisan::call('corex:messaging:relay', ['--once' => true]);

    expect(DB::table(app(CoreOptions::class)->outboxMessagesTable)->where('id', $messageId)->value('status'))->toBe('published')
        ->and(DB::table('dg_durable_store')->count())->toBe(1);

    $stored = DB::table('dg_durable_store')->where('message_id', $messageId)->first();
    $envelope = messagingSampleEnvelope(['messageId' => $messageId]);

    deliveryGuaranteesConsume($envelope);
    deliveryGuaranteesConsume($envelope);

    expect(DB::table('dg_business_effects')->count())->toBe(1);

    outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000105']);
    app()->forgetInstance(DurableMessagePublisher::class);
    app()->forgetInstance(MessagePublisher::class);
    app()->scoped(MessagePublisher::class, static fn (): MessagePublisher => new class implements MessagePublisher
    {
        public function publish(MessageEnvelope $message): void {}
    });

    expect(fn () => Artisan::call('corex:messaging:relay', ['--once' => true]))
        ->toThrow(BindingResolutionException::class);

    expect(DB::table(app(CoreOptions::class)->outboxMessagesTable)->where('id', '01932f5a-0000-7000-8000-000000000105')->value('status'))
        ->toBe('pending');
})->group('pg');

it('terminates expired publishing rows at maxAttempts without another publish and fences corrupt envelopes', function (): void {
    $now = app(ClockInterface::class)->now();
    $exhaustedId = outboxRelayInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000106',
        'status' => 'publishing',
        'lease_owner' => OUTBOX_RELAY_OTHER_OWNER,
        'lease_until' => $now->modify('-1 minute'),
        'attempts' => 8,
    ]);
    $publisher = new RecordingPublisher;
    deliveryGuaranteesBindPublisher($publisher);

    $relay = outboxRelayService(maxAttempts: 8);
    expect($relay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 1))->toBe([]);

    $exhaustedRow = DB::table(app(CoreOptions::class)->outboxMessagesTable)->where('id', $exhaustedId)->first();
    expect($exhaustedRow->status)->toBe('dead')
        ->and($exhaustedRow->failure_class)->toBe('lease-expiry-attempts-exhausted')
        ->and($publisher->recordedMessageIds)->toBe([]);

    $corruptId = outboxRelayInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000107',
        'scope_raw' => '[]',
    ]);
    $corruptRelay = outboxRelayService(maxAttempts: 1);
    expect($corruptRelay->lease(leaseOwner: OUTBOX_RELAY_OWNER, batchSize: 1))->toBe([]);

    $corruptRow = DB::table(app(CoreOptions::class)->outboxMessagesTable)->where('id', $corruptId)->first();
    expect($corruptRow->status)->toBe('dead')
        ->and($corruptRow->failure_class)->toBe('attempts-exhausted')
        ->and($publisher->recordedMessageIds)->toBe([]);
})->group('pg');
