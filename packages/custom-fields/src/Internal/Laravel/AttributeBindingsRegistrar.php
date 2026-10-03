<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Laravel;

use CoreX\CustomFields\Contracts\AttributeFilter;
use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Contracts\SchemaOperations;
use CoreX\CustomFields\Internal\Query\SchemaAttributeFilter;
use CoreX\CustomFields\Internal\UnavailableAttributes;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** @internal Scoped attribute services of the package. */
final class AttributeBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(UnavailableAttributes::class);
        $app->scoped(AttributeFilter::class, SchemaAttributeFilter::class);
        $app->scoped(AttributeStore::class, static fn (Application $app): AttributeStore => (new AttributeStoreFactory)->make($app));
        $app->scoped(SchemaOperations::class, static fn (Application $app): SchemaOperations => (new SchemaOperationsFactory)->make($app));
        $app->scoped(FieldCatalogue::class, static fn (Application $app): FieldCatalogue => (new FieldCatalogueFactory)->make($app));
    }

    #[Override]
    public function boot(Application $app): void {}
}
