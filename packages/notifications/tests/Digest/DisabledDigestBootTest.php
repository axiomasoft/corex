<?php

declare(strict_types=1);

use CoreX\Notifications\Tests\Digest\DisabledDigestTestCase;
use CoreX\Scheduling\Contracts\WakeupScheduler;

uses(DisabledDigestTestCase::class);

it('does not register the wakeup consumer when digest support is disabled', function (): void {
    expect(config('corex-notifications.digest.enabled'))->toBeFalse()
        ->and(app()->bound(WakeupScheduler::class))->toBeFalse();
});
