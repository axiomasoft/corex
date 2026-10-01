<?php

declare(strict_types=1);

namespace CoreX\Intl\Tests;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Internal\AllowAllDataScopeAuthorizer;
use CoreX\Intl\IntlServiceProvider;
use CoreX\Intl\Tests\Support\IntlTestCase;
use Illuminate\Foundation\Application;
use Override;

abstract class TestCase extends IntlTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(DataScopeAuthorizer::class, new AllowAllDataScopeAuthorizer);
        $this->app->forgetScopedInstances();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            IntlServiceProvider::class,
        ];
    }

    #[Override]
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
