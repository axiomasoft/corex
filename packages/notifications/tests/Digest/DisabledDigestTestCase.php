<?php

declare(strict_types=1);

namespace CoreX\Notifications\Tests\Digest;

use CoreX\Notifications\NotificationsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

abstract class DisabledDigestTestCase extends Orchestra
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [NotificationsServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('corex-notifications.digest.enabled', false);
    }
}
