<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Tests;

use CoreX\Intl\IntlServiceProvider;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchIntl\SearchIntlServiceProvider;
use CoreX\SearchIntl\Tests\Support\SearchIntlTestCase;
use Illuminate\Support\Facades\DB;
use Override;
use Packman\Schema\Testing\MigratesSchemaSets;

abstract class TestCase extends SearchIntlTestCase
{
    use MigratesSchemaSets;

    /**
     * @return list<class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            IntlServiceProvider::class,
            SearchServiceProvider::class,
            SearchIntlServiceProvider::class,
        ];
    }

    #[Override]
    protected function defineDatabaseMigrations(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex-intl:tenant'], DB::getDefaultConnection());
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Testbench applies its environment after the first provider registration.
        $this->app->register(SearchIntlServiceProvider::class, force: true);
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('corex-search-intl.enabled', true);
        $app['config']->set('corex-intl.locale.supported_tags', ['en', 'ru', 'en-US', 'ru-RU', 'zh-Hant-TW']);
        $app['config']->set('corex-intl.locale.app_tag', 'en');
        $app['config']->set('corex-intl.locale.explicit_fallback_tags', ['en']);
    }
}
