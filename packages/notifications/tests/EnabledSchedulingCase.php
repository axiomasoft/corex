<?php

declare(strict_types=1);

namespace CoreX\Notifications\Tests;

use Override;

abstract class EnabledSchedulingCase extends DisabledSchedulingCase
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        $app['config']->set('corex-notifications.digest.enabled', true);

        return parent::getPackageProviders($app);
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('corex-notifications.digest.enabled', true);
    }
}
