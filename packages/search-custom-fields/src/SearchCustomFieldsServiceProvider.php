<?php

declare(strict_types=1);

namespace CoreX\SearchCustomFields;

use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\SearchCustomFields\Invalidation\Contracts\FieldSearchSubscriber;
use CoreX\SearchCustomFields\Invalidation\Internal\CommittedFieldSearchSubscriber;
use CoreX\SearchCustomFields\Laravel\SearchCustomFieldsOptions;
use CoreX\SearchCustomFields\Projection\Internal\FieldProjectionContributor;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class SearchCustomFieldsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $file = __DIR__.'/../config/corex-search-custom-fields.php';
        $this->mergeConfigFrom($file, 'corex-search-custom-fields');
        $this->app->singleton(SearchCustomFieldsOptions::class, static fn (Application $app): SearchCustomFieldsOptions => SearchCustomFieldsOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-search-custom-fields', [])));
        $options = $this->app->make(SearchCustomFieldsOptions::class);

        if ($options->enabled) {
            $this->app->scoped(FieldSearchSubscriber::class, CommittedFieldSearchSubscriber::class);
            $this->app->scoped(FieldProjectionContributor::class);
            $this->app->tag(FieldProjectionContributor::class, SearchProjectionContributor::class);
        }

        $this->publishes([$file => $this->app->configPath('corex-search-custom-fields.php')], 'corex-search-custom-fields-config');
    }
}
