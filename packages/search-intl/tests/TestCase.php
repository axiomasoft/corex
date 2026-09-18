<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Tests;

use CoreX\Intl\IntlServiceProvider;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchIntl\Contracts\SavedViewLabelReader;
use CoreX\SearchIntl\Internal\LocalizedFieldProjector;
use CoreX\SearchIntl\Internal\SqlSavedViewLabelReader;
use CoreX\SearchIntl\SearchIntlServiceProvider;
use CoreX\Testing\CoreXTestCase;
use Override;

abstract class TestCase extends CoreXTestCase
{
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
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/intl/database/migrations');
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('corex-intl-search.enabled', true);
        $this->app->scoped(LocalizedFieldProjector::class);
        $this->app->scoped(SavedViewLabelReader::class, SqlSavedViewLabelReader::class);
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('corex-intl-search.enabled', true);
        $app['config']->set('corex-intl.locale.supported_tags', ['en', 'ru', 'en-US', 'ru-RU']);
        $app['config']->set('corex-intl.locale.app_tag', 'en');
    }
}
