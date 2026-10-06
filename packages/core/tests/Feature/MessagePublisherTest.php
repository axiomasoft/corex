<?php

declare(strict_types=1);

use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Contracts\MessagePublisher;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Psr\Clock\ClockInterface;

function messagePublisherInstallSchema(): void
{
    Schema::create('sys_outbox_messages', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('name');
        $table->unsignedSmallInteger('contract_version');
        $table->unsignedSmallInteger('envelope_version');
        $table->string('source');
        $table->string('subject')->nullable();
        $table->string('correlation_id')->nullable();
        $table->string('causation_id')->nullable();
        $table->string('pii_class');
        $table->text('scope');
        $table->text('payload');
        $table->text('metadata');
        $table->dateTimeTz('occurred_at');
        $table->dateTimeTz('available_at');
        $table->string('status');
        $table->string('lease_owner')->nullable();
        $table->dateTimeTz('lease_until')->nullable();
        $table->unsignedSmallInteger('attempts')->default(0);
        $table->string('failure_class')->nullable();
        $table->text('last_error')->nullable();
        $table->dateTimeTz('published_at')->nullable();
        $table->dateTimeTz('dead_at')->nullable();
        $table->timestampsTz();
    });
}

function messagePublisherBindDurable(DurableMessagePublisher $publisher): void
{
    app()->instance(DurableMessagePublisher::class, $publisher);
    app()->instance(MessagePublisher::class, $publisher);
}

beforeEach(function (): void {
    messagePublisherInstallSchema();
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
    $now = app(ClockInterface::class)->now();
    DB::table('sys_outbox_messages')->insert([
        'id' => $envelope->messageId,
        'name' => $envelope->name,
        'contract_version' => $envelope->contractVersion,
        'envelope_version' => $envelope->envelopeVersion,
        'source' => $envelope->source,
        'subject' => $envelope->subject,
        'correlation_id' => $envelope->correlationId,
        'causation_id' => $envelope->causationId,
        'pii_class' => $envelope->piiClass->value,
        'scope' => json_encode($envelope->scope, JSON_THROW_ON_ERROR),
        'payload' => $envelope->payload->encode(),
        'metadata' => json_encode($envelope->metadata, JSON_THROW_ON_ERROR),
        'occurred_at' => $envelope->occurredAt,
        'available_at' => $now,
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

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

    $now = app(ClockInterface::class)->now();
    DB::table('sys_outbox_messages')->insert([
        'id' => '01932f5a-0000-7000-8000-000000000011',
        'name' => 'membership.expired',
        'contract_version' => 1,
        'envelope_version' => MessageEnvelope::ENVELOPE_VERSION,
        'source' => 'axiomasoft/corex',
        'subject' => null,
        'correlation_id' => null,
        'causation_id' => null,
        'pii_class' => 'none',
        'scope' => json_encode(['accountId' => 'a1'], JSON_THROW_ON_ERROR),
        'payload' => '{}',
        'metadata' => '{}',
        'occurred_at' => $now,
        'available_at' => $now,
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect(fn () => $this->artisan('corex:messaging:relay', ['--once' => true]))
        ->toThrow(BindingResolutionException::class);

    expect(DB::table('sys_outbox_messages')->value('status'))->toBe('pending');
});

it('refuses a redis-publish publisher before markPublished', function (): void {
    $this->app->scoped(MessagePublisher::class, static fn (): MessagePublisher => new class implements MessagePublisher
    {
        public function publish(MessageEnvelope $message): void {}
    });

    $now = app(ClockInterface::class)->now();
    DB::table('sys_outbox_messages')->insert([
        'id' => '01932f5a-0000-7000-8000-000000000012',
        'name' => 'membership.expired',
        'contract_version' => 1,
        'envelope_version' => MessageEnvelope::ENVELOPE_VERSION,
        'source' => 'axiomasoft/corex',
        'subject' => null,
        'correlation_id' => null,
        'causation_id' => null,
        'pii_class' => 'none',
        'scope' => json_encode(['accountId' => 'a1'], JSON_THROW_ON_ERROR),
        'payload' => '{}',
        'metadata' => '{}',
        'occurred_at' => $now,
        'available_at' => $now,
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect(fn () => $this->artisan('corex:messaging:relay', ['--once' => true]))
        ->toThrow(BindingResolutionException::class);

    expect(DB::table('sys_outbox_messages')->value('status'))->toBe('pending');
});
