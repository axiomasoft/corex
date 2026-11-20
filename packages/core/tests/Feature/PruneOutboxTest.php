<?php

declare(strict_types=1);

use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Data\ConsumerId;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

require_once __DIR__.'/../Support/Messaging.php';

beforeEach(function (): void {
    messagingWorkScope();
    messagingInstallTable('inbox');
    messagingInstallTable('outbox');
    outboxAppendBindRegistry(outboxAppendDeclaredContract());
    app()->instance(ClockInterface::class, new FrozenClock(new DateTimeImmutable('2026-06-15T12:00:00+00:00')));
});

it('prunes published rows older than the published window and keeps newer ones', function (): void {
    $now = resolve(ClockInterface::class)->now();
    outboxRelayInsertRow([
        'id' => 'pub-old',
        'status' => 'published',
        'published_at' => $now->sub(new DateInterval('P20D')),
    ]);
    outboxRelayInsertRow([
        'id' => 'pub-new',
        'status' => 'published',
        'published_at' => $now->sub(new DateInterval('P5D')),
    ]);

    Artisan::call('corex:messaging:prune', ['--published-older-than' => '14 days']);

    expect(DB::table('sys_outbox_messages')->pluck('message_id')->all())
        ->toBe(['pub-new']);
});

it('prunes dead rows by the dead window not the published window', function (): void {
    $now = resolve(ClockInterface::class)->now();
    outboxRelayInsertRow([
        'id' => 'dead-old',
        'status' => 'dead',
        'dead_at' => $now->sub(new DateInterval('P100D')),
    ]);
    outboxRelayInsertRow([
        'id' => 'dead-recent',
        'status' => 'dead',
        'dead_at' => $now->sub(new DateInterval('P30D')),
    ]);

    Artisan::call('corex:messaging:prune', [
        '--published-older-than' => '1 day',
        '--dead-older-than' => '90 days',
    ]);

    expect(DB::table('sys_outbox_messages')->pluck('message_id')->all())
        ->toBe(['dead-recent']);
});

it('never prunes pending or publishing rows', function (): void {
    $now = resolve(ClockInterface::class)->now();
    outboxRelayInsertRow([
        'id' => 'pending-1',
        'status' => 'pending',
        'occurred_at' => $now->sub(new DateInterval('P365D')),
    ]);
    outboxRelayInsertRow([
        'id' => 'publishing-1',
        'status' => 'publishing',
        'occurred_at' => $now->sub(new DateInterval('P365D')),
    ]);

    Artisan::call('corex:messaging:prune', [
        '--published-older-than' => '0 seconds',
        '--dead-older-than' => '0 seconds',
    ]);

    expect(DB::table('sys_outbox_messages')->count())->toBe(2);
});

it('does not touch sys_inbox_messages', function (): void {
    $now = resolve(ClockInterface::class)->now();
    outboxRelayInsertRow([
        'id' => 'pub-stale',
        'status' => 'published',
        'published_at' => $now->sub(new DateInterval('P30D')),
    ]);

    DB::transaction(fn () => resolve(Inbox::class)->claim(DB::connection(), new ConsumerId('worker-a', 1), messagingSampleEnvelope(['messageId' => 'inbox-1'])));

    Artisan::call('corex:messaging:prune', ['--published-older-than' => '1 day']);

    expect(DB::table('sys_inbox_messages')->count())->toBe(1);
});

it('uses FrozenClock for retention thresholds', function (): void {
    $anchor = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    app()->instance(ClockInterface::class, new FrozenClock($anchor));

    outboxRelayInsertRow([
        'id' => 'on-boundary',
        'status' => 'published',
        'published_at' => $anchor->sub(new DateInterval('P14D')),
    ]);
    outboxRelayInsertRow([
        'id' => 'past-boundary',
        'status' => 'published',
        'published_at' => $anchor->sub(new DateInterval('P14DT1S')),
    ]);

    Artisan::call('corex:messaging:prune', ['--published-older-than' => '14 days']);

    expect(DB::table('sys_outbox_messages')->pluck('message_id')->all())
        ->toBe(['on-boundary']);
});
