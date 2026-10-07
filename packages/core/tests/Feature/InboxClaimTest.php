<?php

declare(strict_types=1);

use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Exceptions\InboxRefused;
use CoreX\Messaging\Laravel\InboxRetention;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Psr\Clock\ClockInterface;

function inboxClaimInstallSchema(): void
{
    Schema::create('sys_inbox_messages', function (Blueprint $table): void {
        $table->string('consumer');
        $table->string('message_id');
        $table->string('name');
        $table->dateTimeTz('processed_at');
        $table->primary(['consumer', 'message_id']);
    });
}

beforeEach(function (): void {
    inboxClaimInstallSchema();
});

it('forgets only explicit message ids for the selected consumer', function (): void {
    $inbox = resolve(Inbox::class);
    DB::transaction(function () use ($inbox): void {
        foreach (['webhooks', 'billing'] as $consumer) {
            foreach (['old', 'retained'] as $messageId) {
                $inbox->claim(consumer: $consumer, messageId: $messageId, name: 'example');
            }
        }
    });
    $retention = new InboxRetention(connection: DB::connection(), table: 'sys_inbox_messages');

    expect($retention->forget(consumer: 'webhooks', messageIds: []))->toBe(0)
        ->and($retention->forget(consumer: 'webhooks', messageIds: ['old']))->toBe(1)
        ->and($retention->forget(consumer: 'webhooks', messageIds: ['old']))->toBe(0)
        ->and(DB::table('sys_inbox_messages')->where('consumer', 'billing')->count())->toBe(2)
        ->and(DB::table('sys_inbox_messages')->where('consumer', 'webhooks')->value('message_id'))->toBe('retained');
});

it('refuses claim outside a transaction with MSG-006', function (): void {
    $inbox = resolve(Inbox::class);

    expect(fn (): bool => $inbox->claim(
        consumer: 'search.projection.v1',
        messageId: '11111111-1111-7111-8111-111111111111',
        name: 'membership.expired',
    ))->toThrow(InboxRefused::class, 'MSG-006');
});

it('returns true on first claim and false on duplicate consumer and message id', function (): void {
    $inbox = resolve(Inbox::class);
    $messageId = '22222222-2222-7222-8222-222222222222';

    $first = null;
    $second = null;

    DB::transaction(function () use ($inbox, $messageId, &$first, &$second): void {
        $first = $inbox->claim(
            consumer: 'search.projection.v1',
            messageId: $messageId,
            name: 'membership.expired',
        );
        $second = $inbox->claim(
            consumer: 'search.projection.v1',
            messageId: $messageId,
            name: 'membership.expired',
        );
    });

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse();
});

it('treats consumers independently for the same message id', function (): void {
    $inbox = resolve(Inbox::class);
    $messageId = '33333333-3333-7333-8333-333333333333';

    $firstConsumer = null;
    $secondConsumer = null;

    DB::transaction(function () use ($inbox, $messageId, &$firstConsumer, &$secondConsumer): void {
        $firstConsumer = $inbox->claim(
            consumer: 'search.projection.v1',
            messageId: $messageId,
            name: 'membership.expired',
        );
        $secondConsumer = $inbox->claim(
            consumer: 'billing.projection.v1',
            messageId: $messageId,
            name: 'membership.expired',
        );
    });

    expect($firstConsumer)->toBeTrue()
        ->and($secondConsumer)->toBeTrue();
});

it('stores processed_at from FrozenClock', function (): void {
    $frozen = new DateTimeImmutable('2026-06-15T12:00:00+00:00');
    app()->instance(ClockInterface::class, new FrozenClock($frozen));
    app()->forgetScopedInstances();

    $inbox = resolve(Inbox::class);
    $messageId = '44444444-4444-7444-8444-444444444444';

    DB::transaction(function () use ($inbox, $messageId): void {
        $inbox->claim(
            consumer: 'search.projection.v1',
            messageId: $messageId,
            name: 'membership.expired',
        );
    });

    $row = DB::table('sys_inbox_messages')
        ->where('consumer', 'search.projection.v1')
        ->where('message_id', $messageId)
        ->first();

    expect($row)->not->toBeNull()
        ->and(new DateTimeImmutable((string) $row->processed_at)->getTimestamp())->toBe($frozen->getTimestamp());
});
