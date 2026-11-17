<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Laravel;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Internal\Schema\ModuleFieldCatalogue;
use CoreX\CustomFields\Internal\Schema\StaticFieldCatalogue;
use Illuminate\Contracts\Foundation\Application;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

/** @internal Composition of the FieldCatalogue binding. */
final class FieldCatalogueFactory
{
    public function make(Application $app): FieldCatalogue
    {
        $composition = $app->make(AttributeComposition::class);
        $modulesBound = $app->bound(EntityRegistry::class) && $app->bound(ModuleRegistry::class);
        $catalogue = new StaticFieldCatalogue(
            definitions: $composition->definitions,
            drivers: $composition->drivers,
        );

        if ($modulesBound && in_array('module', $composition->effectiveSources(modulesBound: true), true)) {
            return $catalogue->appending(new ModuleFieldCatalogue(
                entities: $app->make(EntityRegistry::class),
                modules: $app->make(ModuleRegistry::class),
                contexts: $app->make(TenantContextRestorer::class),
                drivers: $composition->drivers,
            ));
        }

        return $catalogue;
    }
}
