<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/Messaging.php';

use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Contracts\MessagePublisher;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

function messagePublisherBindDurable(DurableMessagePublisher $publisher): void
{
    app()->instance(DurableMessagePublisher::class, $publisher);
    app()->instance(MessagePublisher::class, $publisher);
}

beforeEach(function (): void {
    messagingWorkScope();
    messagingInstallTable('outbox');
    outboxAppendBindRegistry(outboxAppendDeclaredContract());
    $this->app->instance(ClockInterface::class, new FrozenClock(new DateTimeImmutable('2026-06-15T12:00:00+00:00')));
});

it('admits a durable publisher binding and marks a leased row published', function (): void {
    $publisher = new class implements DurableMessagePublisher
    {
        /** @var list<string> */
        public array $publishedIds = [];

        public function publish(MessageEnvelope $message): void
        {
            $this->publishedIds[] = $message->messageId;
        }
    };
    messagePublisherBindDurable($publisher);

    $envelope = messagingSampleEnvelope(['messageId' => '01932f5a-0000-7000-8000-000000000010']);
    DB::transaction(fn () => resolve(Outbox::class)->append(DB::connection(), $envelope));

    $this->artisan('corex:messaging:relay', ['--once' => true])->assertSuccessful();

    expect($publisher->publishedIds)->toBe([$envelope->messageId])
        ->and(DB::table('sys_outbox_messages')->value('status'))->toBe('published');
});

it('refuses relay when no durable publisher is bound', function (): void {
    expect(fn () => $this->artisan('corex:messaging:relay', ['--once' => true]))
        ->toThrow(BindingResolutionException::class);
});

it('refuses a null-transport publisher before markPublished', function (): void {
    $this->app->scoped(MessagePublisher::class, static fn (): MessagePublisher => new class implements MessagePublisher
    {
        public function publish(MessageEnvelope $message): void {}
    });

    DB::transaction(fn () => resolve(Outbox::class)->append(DB::connection(), messagingSampleEnvelope(['messageId' => '01932f5a-0000-7000-8000-000000000011'])));

    expect(fn () => $this->artisan('corex:messaging:relay', ['--once' => true]))
        ->toThrow(BindingResolutionException::class);

    expect(DB::table('sys_outbox_messages')->value('status'))->toBe('pending');
});

it('refuses a redis-publish publisher before markPublished', function (): void {
    $this->app->scoped(MessagePublisher::class, static fn (): MessagePublisher => new class implements MessagePublisher
    {
        public function publish(MessageEnvelope $message): void {}
    });

    DB::transaction(fn () => resolve(Outbox::class)->append(DB::connection(), messagingSampleEnvelope(['messageId' => '01932f5a-0000-7000-8000-000000000012'])));

    expect(fn () => $this->artisan('corex:messaging:relay', ['--once' => true]))
        ->toThrow(BindingResolutionException::class);

    expect(DB::table('sys_outbox_messages')->value('status'))->toBe('pending');
});
