<?php

declare(strict_types=1);

namespace CoreX\Intl\Tests;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Internal\AllowAllDataScopeAuthorizer;
use CoreX\Intl\IntlServiceProvider;
use CoreX\Intl\Tests\Support\IntlTestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Override;
use Packman\Schema\Testing\MigratesSchemaSets;

abstract class TestCase extends IntlTestCase
{
    use MigratesSchemaSets;

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
    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex-intl:tenant'], DB::getDefaultConnection());
    }
}
