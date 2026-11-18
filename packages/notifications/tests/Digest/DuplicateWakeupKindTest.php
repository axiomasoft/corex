<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/SchedulingDigestFixtures.php';
require_once __DIR__.'/../../../scheduler/tests/Support/PackageBridge.php';

use CoreX\Notifications\Digest\Internal\DigestWakeupAdapter;
use CoreX\Notifications\Tests\EnabledSchedulingCase;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use Packman\Config\Exceptions\InvalidConfiguration;

uses(EnabledSchedulingCase::class);

it('schedules an enabled digest exactly once', function (): void {
    $spy = new SchedulingSpy;
    app()->instance(WakeupScheduler::class, $spy);

    $digest = schedulingFreeze('enabled-scheduled');

    expect($digest)->not->toBeNull()
        ->and($spy->calls)->toBe(1);
});

it('rejects a duplicate wakeup handler kind when the host config repeats the tagged kind', function (): void {
    app()['config']->set('corex-scheduler.handlers', [
        'notification.digest.v1' => [
            'handler' => DigestWakeupAdapter::class,
            'version' => 'notification-digest/1',
            'accept_legacy_unstamped' => false,
        ],
    ]);
    app()->forgetInstance(wakeup_bridge_options());

    expect(fn () => wakeup_bridge_register())
        ->toThrow(InvalidConfiguration::class, 'corex-scheduler.handlers.notification.digest.v1: duplicate wakeup handler kind');
});
