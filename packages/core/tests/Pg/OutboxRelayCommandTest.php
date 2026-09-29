<?php

declare(strict_types=1);

use CoreX\Config\Config;
use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Contracts\MessagePublisher;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Exceptions\OutboxLeaseLost;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

function outboxRelayCommandBindPublisher(DurableMessagePublisher $publisher): void
{
    app()->instance(DurableMessagePublisher::class, $publisher);
    app()->instance(MessagePublisher::class, $publisher);
}

beforeEach(function (): void {
    outboxRelayMigrate();
    outboxRelayClock(new DateTimeImmutable('2026-06-15T12:00:00+00:00'));
    outboxAppendBindRegistry(outboxAppendDeclaredContract());
});

it('publishes two pending messages in one pass', function (): void {
    $publisher = new class implements DurableMessagePublisher
    {
        public function publish(MessageEnvelope $message): void {}
    };
    outboxRelayCommandBindPublisher($publisher);

    outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000021']);
    outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000022']);

    Artisan::call('corex:messaging:relay', ['--once' => true]);

    expect(Artisan::output())->toContain('published 2')
        ->and(DB::connection('pgsql')->table(Config::outboxMessagesTable())->where('status', 'published')->count())->toBe(2);
})->group('pg');

it('defers the second message when the publisher throws', function (): void {
    $calls = 0;
    $publisher = new class($calls) implements DurableMessagePublisher
    {
        public function __construct(private int &$calls) {}

        public function publish(MessageEnvelope $message): void
        {
            $this->calls++;

            if ($this->calls === 2) {
                throw new RuntimeException('publish-failed');
            }
        }
    };
    outboxRelayCommandBindPublisher($publisher);

    outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000031']);
    outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000032']);

    $this->artisan('corex:messaging:relay', ['--once' => true])
        ->expectsOutputToContain('deferred 1')
        ->assertSuccessful();

    $now = app(ClockInterface::class)->now();
    $second = DB::connection('pgsql')->table(Config::outboxMessagesTable())
        ->where('id', '01932f5a-0000-7000-8000-000000000032')
        ->first();

    $availableAt = new DateTimeImmutable((string) $second->available_at);

    expect(DB::connection('pgsql')->table(Config::outboxMessagesTable())->where('status', 'published')->count())->toBe(1)
        ->and($second->status)->toBe('pending')
        ->and($availableAt->getTimestamp())->toBe($now->modify('+2 seconds')->getTimestamp());
})->group('pg');

it('warns on OutboxLeaseLost without deferring', function (): void {
    $calls = 0;
    $publisher = new class($calls) implements DurableMessagePublisher
    {
        public function __construct(private int &$calls) {}

        public function publish(MessageEnvelope $message): void
        {
            $this->calls++;

            if ($this->calls === 2) {
                throw OutboxLeaseLost::superseded($message->messageId);
            }
        }
    };
    outboxRelayCommandBindPublisher($publisher);

    outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000041']);
    outboxRelayInsertRow(['id' => '01932f5a-0000-7000-8000-000000000042']);

    $this->artisan('corex:messaging:relay', ['--once' => true])
        ->expectsOutputToContain('deferred 0')
        ->expectsOutputToContain('superseded')
        ->assertSuccessful();

    $second = DB::connection('pgsql')->table(Config::outboxMessagesTable())
        ->where('id', '01932f5a-0000-7000-8000-000000000042')
        ->first();

    expect($second->status)->toBe('publishing');
})->group('pg');

it('runs relay once in the test environment', function (): void {
    $publisher = new class implements DurableMessagePublisher
    {
        public function publish(MessageEnvelope $message): void {}
    };
    outboxRelayCommandBindPublisher($publisher);

    $this->artisan('corex:messaging:relay', ['--once' => true])->assertSuccessful();
})->group('pg');
