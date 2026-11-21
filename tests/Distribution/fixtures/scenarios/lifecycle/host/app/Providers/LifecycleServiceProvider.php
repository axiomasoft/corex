<?php

declare(strict_types=1);

namespace App\Providers;

use App\Lifecycle\Deployment;
use App\Lifecycle\ExternalResources;
use App\Lifecycle\HostModule;
use App\Lifecycle\Inventory;
use App\Lifecycle\ReferenceOrigins;
use App\Lifecycle\SavedViews;
use App\Lifecycle\Views;
use App\Scenario\ScenarioAuthorization;
use App\Scenario\SearchScenarioCredentials;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Foundation\Contracts\DeploymentOwners;
use CoreX\Foundation\Contracts\LifecycleInventory;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ReferenceOwners;
use CoreX\Panels\Contracts\SavedViewInventory;
use CoreX\Search\Index\Contracts\IndexNamespaceExclusion;
use CoreX\SearchMeilisearch\Contracts\MeilisearchCredentialProvider;
use Illuminate\Support\ServiceProvider;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Packman\Panels\SavedViews\Contracts\ViewAccessPolicy;
use Packman\Panels\SavedViews\Contracts\ViewSchemaProvider;

final class LifecycleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->extend(ModuleDiscovery::class, static fn (ModuleDiscovery $installed) => new HostModule($installed));
    }

    public function register(): void
    {
        $this->app['config']->set('corex-search.driver', 'meilisearch');
        $this->app['config']->set('corex-search.cluster', getenv('COREX_SCENARIO_MEILI_NAMESPACE'));
        $this->app['config']->set('corex-search-meilisearch.host', getenv('MEILISEARCH_HOST'));
        $this->app->scoped(LifecycleInventory::class, Inventory::class);
        $this->app->scoped(DeploymentOwners::class, Deployment::class);
        $this->app->scoped(MeilisearchCredentialProvider::class, SearchScenarioCredentials::class);
        $this->app->scoped(ViewAccessPolicy::class, Views::class);
        $this->app->scoped(ViewSchemaProvider::class, Views::class);
        $this->app->scoped(SavedViewInventory::class, SavedViews::class);
        $this->app->scoped(ReferenceOwners::class, ReferenceOrigins::class);
        $this->app->scoped(FieldUsePolicy::class, ScenarioAuthorization::class);
        $this->app->scoped(ExternalResources::class);
        $this->app->scoped(IndexNamespaceExclusion::class, static fn ($app) => $app->make(ExternalResources::class));
        $this->app->tag(ExternalResources::class, ModuleReferences::class);
    }
}
