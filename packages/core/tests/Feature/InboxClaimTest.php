<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/Messaging.php';

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Data\ConsumerId;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Exceptions\InboxRefused;
use CoreX\Messaging\Exceptions\InvalidMessage;
use CoreX\Messaging\Laravel\InboxRetention;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

beforeEach(function (): void {
    messagingWorkScope();
    messagingInstallTable('inbox');
    outboxAppendBindRegistry(outboxAppendDeclaredContract());
});

it('forgets only explicit message ids for the selected consumer', function (): void {
    $inbox = resolve(Inbox::class);
    DB::transaction(function () use ($inbox): void {
        foreach (['webhooks', 'billing'] as $consumer) {
            foreach (['old', 'retained'] as $messageId) {
                $inbox->claim(connection: DB::connection(), consumer: new ConsumerId($consumer, 1), message: messagingSampleEnvelope(['messageId' => $messageId]));
            }
        }
    });
    $retention = new InboxRetention(connection: DB::connection(), current: resolve(CurrentWorkScope::class), table: 'sys_inbox_messages');

    expect($retention->forget(scope: messagingWorkScope(), consumer: new ConsumerId('webhooks', 1), source: 'axiomasoft/corex', messageIds: []))->toBe(0)
        ->and($retention->forget(scope: messagingWorkScope(), consumer: new ConsumerId('webhooks', 1), source: 'axiomasoft/corex', messageIds: ['old']))->toBe(1)
        ->and($retention->forget(scope: messagingWorkScope(), consumer: new ConsumerId('webhooks', 1), source: 'axiomasoft/corex', messageIds: ['old']))->toBe(0)
        ->and(DB::table('sys_inbox_messages')->where('consumer', 'billing')->count())->toBe(2)
        ->and(DB::table('sys_inbox_messages')->where('consumer', 'webhooks')->value('message_id'))->toBe('retained');
});

it('refuses claim outside a transaction with MSG-006', function (): void {
    $inbox = resolve(Inbox::class);

    expect(fn (): bool => $inbox->claim(
        connection: DB::connection(), consumer: new ConsumerId('search.projection.v1', 1), message: messagingSampleEnvelope(['messageId' => '11111111-1111-7111-8111-111111111111']),
    ))->toThrow(InboxRefused::class, 'MSG-006');
});

it('returns true on first claim and false on duplicate consumer and message id', function (): void {
    $inbox = resolve(Inbox::class);
    $messageId = '22222222-2222-7222-8222-222222222222';

    $first = null;
    $second = null;

    DB::transaction(function () use ($inbox, $messageId, &$first, &$second): void {
        $first = $inbox->claim(
            connection: DB::connection(), consumer: new ConsumerId('search.projection.v1', 1), message: messagingSampleEnvelope(['messageId' => $messageId]),
        );
        $second = $inbox->claim(
            connection: DB::connection(), consumer: new ConsumerId('search.projection.v1', 1), message: messagingSampleEnvelope(['messageId' => $messageId]),
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
            connection: DB::connection(), consumer: new ConsumerId('search.projection.v1', 1), message: messagingSampleEnvelope(['messageId' => $messageId]),
        );
        $secondConsumer = $inbox->claim(
            connection: DB::connection(), consumer: new ConsumerId('billing.projection.v1', 1), message: messagingSampleEnvelope(['messageId' => $messageId]),
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
            connection: DB::connection(), consumer: new ConsumerId('search.projection.v1', 1), message: messagingSampleEnvelope(['messageId' => $messageId]),
        );
    });

    $row = DB::table('sys_inbox_messages')
        ->where('consumer', 'search.projection.v1')
        ->where('message_id', $messageId)
        ->first();

    expect($row)->not->toBeNull()
        ->and(new DateTimeImmutable((string) $row->processed_at)->getTimestamp())->toBe($frozen->getTimestamp());
});

it('refuses a derived fact that does not name its cause', function (): void {
    $contract = outboxAppendDeclaredContract();
    outboxAppendBindRegistry(new MessageContract(
        name: $contract->name,
        version: $contract->version,
        class: $contract->class,
        owner: $contract->owner,
        maxPiiClass: $contract->maxPiiClass,
        channel: $contract->channel,
        derived: true,
    ));

    expect(fn (): bool => DB::transaction(fn (): bool => resolve(Inbox::class)->claim(
        connection: DB::connection(), consumer: new ConsumerId('search.projection', 1), message: messagingSampleEnvelope(['messageId' => 'uncaused', 'causationId' => null]),
    )))->toThrow(InvalidMessage::class)
        ->and(DB::table('sys_inbox_messages')->count())->toBe(0);
});

it('refuses a replayed message id that carries a different fact', function (): void {
    $claim = static fn (string $correlationId): bool => DB::transaction(fn (): bool => resolve(Inbox::class)->claim(
        connection: DB::connection(), consumer: new ConsumerId('search.projection', 1), message: messagingSampleEnvelope(['messageId' => 'replayed', 'correlationId' => $correlationId]),
    ));

    expect($claim('corr-1'))->toBeTrue()
        ->and($claim('corr-1'))->toBeFalse()
        ->and(fn (): bool => $claim('corr-2'))->toThrow(InboxRefused::class, 'Conflicting fact');
});
