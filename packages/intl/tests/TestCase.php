<?php

declare(strict_types=1);

namespace CoreX\Intl\Tests;

use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Intl\IntlServiceProvider;
use CoreX\Support\Data\AllowAllDataScopeAuthorizer;
use CoreX\Testing\CoreXTestCase;
use Illuminate\Foundation\Application;
use Override;

abstract class TestCase extends CoreXTestCase
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
