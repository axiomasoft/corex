<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/SchedulingDigestFixtures.php';

use CoreX\Notifications\Internal\DigestSchedulerCheck;
use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Notifications\Tests\DisabledSchedulingCase;
use CoreX\Scheduling\Contracts\DueWakeupSource;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use Illuminate\Support\Facades\DB;

uses(DisabledSchedulingCase::class);

it('freezes a disabled digest without resolving a scheduler', function (): void {
    $resolved = false;
    app()->bind(WakeupScheduler::class, function () use (&$resolved): WakeupScheduler {
        $resolved = true;

        return new SchedulingSpy;
    });

    $digest = schedulingFreeze('disabled-build');

    expect($digest)->not->toBeNull()
        ->and($resolved)->toBeFalse()
        ->and(DB::table('ntf_digest_runs')->count())->toBe(1)
        ->and(iterator_to_array(app()->tagged(WakeupHandlerDefinition::TAG)))->toBe([])
        ->and(iterator_to_array(app()->tagged(DueWakeupSource::TAG)))->toBe([]);
});

it('reports a digest scheduler violation when the binding is absent', function (): void {
    app()['config']->set('corex-notifications.digest.enabled', true);
    app()->forgetInstance(NotificationsOptions::class);

    expect(resolve(DigestSchedulerCheck::class)->violations(false))->toBe([
        'corex-notifications.digest.enabled requires a bound CoreX\\Scheduling\\Contracts\\WakeupScheduler',
    ]);
});
