<?php

declare(strict_types=1);

use CoreX\Notifications\Internal\Digest\DigestTick;
use CoreX\Notifications\Internal\Digest\DigestWakeupAdapter;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use CoreX\Wakeup\Data\WakeupDelivery;
use CoreX\Wakeup\Data\WakeupRequest;
use CoreX\Wakeup\Data\WakeupWindow;

it('maps a typed digest wakeup request without a workflow dependency', function (): void {
    $seen = null;
    $context = new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'digest-test', 'active', [], []));
    $window = new WakeupWindow(new DateTimeImmutable('2026-09-16T09:00:00Z'), new DateTimeImmutable('2026-09-16T10:00:00Z'), 'hourly');
    $request = new WakeupRequest($context, 'notification.digest.v1', 'digest:2026-09-16T09', new DateTimeImmutable('2026-09-16T10:00:00Z'), $window, []);
    $adapter = new DigestWakeupAdapter(function (DigestTick $tick) use (&$seen): void {
        $seen = $tick;
    });

    $adapter->handle(new WakeupDelivery($request, 1, 1));

    expect($seen)->toBeInstanceOf(DigestTick::class)
        ->and($seen->requestKey)->toBe('digest:2026-09-16T09')
        ->and($seen->window->period)->toBe('hourly');
});
