<?php

declare(strict_types=1);

namespace CoreX\SearchCustomFields\Tests\Unit;

use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\SearchCustomFields\Invalidation\Contracts\FieldSearchSubscriber;
use CoreX\SearchCustomFields\Laravel\SearchCustomFieldsOptions;
use CoreX\SearchCustomFields\SearchCustomFieldsServiceProvider;
use Orchestra\Testbench\TestCase;

final class SearchCustomFieldsServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SearchCustomFieldsServiceProvider::class];
    }

    public function test_default_boot_leaves_the_subscriber_and_projection_unregistered(): void
    {
        self::assertFalse($this->app['config']->get('corex-search-custom-fields.enabled'));
        $options = $this->app->make(SearchCustomFieldsOptions::class);
        self::assertFalse($options->enabled);
        self::assertSame($options, $this->app->make(SearchCustomFieldsOptions::class));
        self::assertFalse($this->app->bound(FieldSearchSubscriber::class));
        self::assertSame([], iterator_to_array($this->app->tagged(SearchProjectionContributor::class)));
    }
}
