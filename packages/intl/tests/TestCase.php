<?php

declare(strict_types=1);

namespace CoreX\Intl\Tests;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\Intl\IntlServiceProvider;
use CoreX\Intl\Tests\Support\IntlTestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
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

        $this->app->scoped(DataScopeAuthorizer::class, static fn (): DataScopeAuthorizer => new CallableDataScopeAuthorizer(static fn (): bool => true));
        $this->app->forgetScopedInstances();
        $this->app->instance(TenantContextResolver::class, new class implements TenantContextResolver
        {
            public function current(): TenantContext
            {
                return new TenantContext(new AccountRef('acct', 'acct', 'active', [], []));
            }
        });
        CallerWorkScopeFixture::bind($this->app);
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
