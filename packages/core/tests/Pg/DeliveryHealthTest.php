<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/Messaging.php';

use CoreX\Messaging\Internal\SqlDeliveryHealth;
use Illuminate\Support\Facades\Artisan;

function deliveryHealthInsertRow(array $overrides = []): void
{
    outboxRelayInsertRow($overrides);
}

beforeEach(function (): void {
    outboxRelayMigrate();
});

it('returns null oldest age and zero counters on an empty table', function (): void {
    outboxRelayClock(new DateTimeImmutable('2026-06-15T12:00:00+00:00'));

    $health = resolve(SqlDeliveryHealth::class)->read();

    expect($health->oldestPendingSeconds)->toBeNull()
        ->and($health->oldestPendingSecondsByContract)->toBe([])
        ->and($health->pendingCount)->toBe(0)
        ->and($health->publishingCount)->toBe(0)
        ->and($health->deadCount)->toBe(0)
        ->and($health->maxAttempts)->toBe(0);
})->group('pg');

it('reports per-contract ages and the maximum as oldest pending', function (): void {
    $now = new DateTimeImmutable('2026-06-15T12:00:00+00:00');
    outboxRelayClock($now);

    deliveryHealthInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000031',
        'occurred_at' => $now->sub(new DateInterval('PT2H')),
    ]);
    deliveryHealthInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000032',
        'name' => 'billing.invoice',
        'contract_version' => 2,
        'occurred_at' => $now->sub(new DateInterval('PT30M')),
    ]);

    $health = resolve(SqlDeliveryHealth::class)->read();

    expect($health->oldestPendingSeconds)->toBe(7200)
        ->and($health->oldestPendingSecondsByContract)->toBe([
            'billing.invoice.v2' => 1800,
            'membership.expired.v1' => 7200,
        ])
        ->and($health->pendingCount)->toBe(2);
})->group('pg');

it('excludes pending rows with future available_at from age', function (): void {
    $now = new DateTimeImmutable('2026-06-15T12:00:00+00:00');
    outboxRelayClock($now);

    deliveryHealthInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000041',
        'occurred_at' => $now->sub(new DateInterval('PT1H')),
        'available_at' => $now->add(new DateInterval('PT1H')),
    ]);

    $health = resolve(SqlDeliveryHealth::class)->read();

    expect($health->oldestPendingSeconds)->toBeNull()
        ->and($health->pendingCount)->toBe(1);
})->group('pg');

it('counts published rows only in status totals not in pending age', function (): void {
    $now = new DateTimeImmutable('2026-06-15T12:00:00+00:00');
    outboxRelayClock($now);

    deliveryHealthInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000051',
        'status' => 'published',
        'published_at' => $now,
        'occurred_at' => $now->sub(new DateInterval('P1D')),
    ]);

    $health = resolve(SqlDeliveryHealth::class)->read();

    expect($health->oldestPendingSeconds)->toBeNull()
        ->and($health->pendingCount)->toBe(0)
        ->and($health->publishingCount)->toBe(0)
        ->and($health->deadCount)->toBe(0);
})->group('pg');

it('computes maxAttempts only from pending and publishing rows', function (): void {
    $now = new DateTimeImmutable('2026-06-15T12:00:00+00:00');
    outboxRelayClock($now);

    deliveryHealthInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000061',
        'status' => 'pending',
        'attempts' => 3,
    ]);
    deliveryHealthInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000062',
        'status' => 'publishing',
        'attempts' => 5,
    ]);
    deliveryHealthInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000063',
        'status' => 'dead',
        'attempts' => 99,
        'dead_at' => $now,
    ]);

    $health = resolve(SqlDeliveryHealth::class)->read();

    expect($health->maxAttempts)->toBe(5)
        ->and($health->deadCount)->toBe(1);
})->group('pg');

it('fails the health probe when oldest pending exceeds max-age', function (): void {
    $now = new DateTimeImmutable('2026-06-15T12:00:00+00:00');
    outboxRelayClock($now);

    deliveryHealthInsertRow([
        'id' => '01932f5a-0000-7000-8000-000000000071',
        'occurred_at' => $now->sub(new DateInterval('PT5M')),
    ]);

    expect(Artisan::call('corex:messaging:health', ['--max-age' => '0']))->toBe(1)
        ->and(Artisan::call('corex:messaging:health', ['--max-age' => '600']))->toBe(0);
})->group('pg');

it('returns success for max-age probe on an empty table', function (): void {
    outboxRelayClock(new DateTimeImmutable('2026-06-15T12:00:00+00:00'));

    expect(Artisan::call('corex:messaging:health', ['--max-age' => '0']))->toBe(0);
})->group('pg');
