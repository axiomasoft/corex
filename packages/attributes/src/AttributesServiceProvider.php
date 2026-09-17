<?php

declare(strict_types=1);

namespace CoreX\Attributes;

use CoreX\Attributes\Contracts\AttributeFilter;
use CoreX\Attributes\Contracts\AttributeStore;
use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Contracts\SchemaOperations;
use CoreX\Attributes\Internal\Engine\EavAttributeStore;
use CoreX\Attributes\Internal\Engine\JsonbAttributeStore;
use CoreX\Attributes\Internal\Engine\QueuedSchemaOperations;
use CoreX\Attributes\Internal\Engine\StructuralAttributeFilter;
use CoreX\Attributes\Internal\Registry\RegistryFieldCatalogue;
use CoreX\Attributes\Internal\UnavailableAttributes;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class AttributesServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-attributes.php', 'corex-attributes');
        $this->app->scoped(UnavailableAttributes::class);

        $this->app->scoped(AttributeFilter::class, StructuralAttributeFilter::class);

        $this->app->scoped(AttributeStore::class, function (Application $app): AttributeStore {
            $catalogue = $app->make(FieldCatalogue::class);

            if ($catalogue instanceof UnavailableAttributes) {
                return $catalogue;
            }

            if ($app['config']->get('corex-attributes.storage_driver') === 'eav' && (bool) $app['config']->get('corex-attributes.drivers.eav', false) && $app->bound('db')) {
                return new EavAttributeStore(connection: $app['db']->connection(), catalogue: $catalogue);
            }

            if ((bool) $app['config']->get('corex-attributes.drivers.jsonb', false) && $app->bound('db')) {
                return new JsonbAttributeStore(connection: $app['db']->connection(), catalogue: $catalogue);
            }

            return $app->make(UnavailableAttributes::class);
        });

        $this->app->scoped(SchemaOperations::class, function (Application $app): SchemaOperations {
            $catalogue = $app->make(FieldCatalogue::class);

            if ($catalogue instanceof UnavailableAttributes) {
                return $catalogue;
            }

            if ((bool) $app['config']->get('corex-attributes.drivers.ddl', false) && $app->bound('db')) {
                return new QueuedSchemaOperations(
                    connection: $app['db']->connection(),
                    catalogue: $catalogue,
                );
            }

            return $app->make(UnavailableAttributes::class);
        });

        $this->app->scoped(FieldCatalogue::class, function (Application $app): FieldCatalogue {
            if ($app->bound(EntityRegistry::class) && $app->bound(ModuleRegistry::class)) {
                return new RegistryFieldCatalogue(
                    entities: $app->make(EntityRegistry::class),
                    modules: $app->make(ModuleRegistry::class),
                    drivers: (array) $app['config']->get('corex-attributes.drivers'),
                );
            }

            return $app->make(UnavailableAttributes::class);
        });
    }
}
