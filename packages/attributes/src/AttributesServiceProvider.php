<?php

declare(strict_types=1);

namespace CoreX\Attributes;

use CoreX\Attributes\Contracts\AttributeFilter;
use CoreX\Attributes\Contracts\AttributeStore;
use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Contracts\SchemaOperations;
use CoreX\Attributes\Data\AttributeStoragePolicy;
use CoreX\Attributes\Data\AttributeTables;
use CoreX\Attributes\Internal\Integration\AttributeMessageTranslator;
use CoreX\Attributes\Internal\Laravel\AttributeComposition;
use CoreX\Attributes\Internal\Laravel\AttributeCompositionRegistrar;
use CoreX\Attributes\Internal\Laravel\AttributeMessagingRegistrar;
use CoreX\Attributes\Internal\Query\SchemaAttributeFilter;
use CoreX\Attributes\Internal\Schema\ModuleFieldCatalogue;
use CoreX\Attributes\Internal\Schema\QueuedSchemaOperations;
use CoreX\Attributes\Internal\Schema\StaticFieldCatalogue;
use CoreX\Attributes\Internal\Storage\ConfiguredAttributeStoreResolver;
use CoreX\Attributes\Internal\Storage\ResolvingAttributeStore;
use CoreX\Attributes\Internal\UnavailableAttributes;
use CoreX\Attributes\Internal\Values\EavAttributeStore;
use CoreX\Attributes\Internal\Values\JsonbAttributeStore;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Psr\Clock\ClockInterface;

final class AttributesServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-attributes.php', 'corex-attributes');
        (new AttributeMessagingRegistrar)->register($this->app);
        (new AttributeCompositionRegistrar)->register($this->app);
        $this->app->scoped(UnavailableAttributes::class);

        $this->app->scoped(AttributeFilter::class, SchemaAttributeFilter::class);

        $this->app->scoped(AttributeStore::class, function (Application $app): AttributeStore {
            $composition = $app->make(AttributeComposition::class);
            $catalogue = $app->make(FieldCatalogue::class);
            $tables = $app->make(AttributeTables::class);

            if (! $app->bound('db')) {
                return $app->make(UnavailableAttributes::class);
            }

            $connection = $app['db']->connection();
            $authorizer = $app->make(DataScopeAuthorizer::class);
            $outbox = $app->make(Outbox::class);
            $messageTranslator = $app->make(AttributeMessageTranslator::class);
            $clock = $app->make(ClockInterface::class);
            $jsonb = ($composition->drivers['jsonb'] ?? false)
                ? new JsonbAttributeStore(
                    connection: $connection,
                    tables: $tables,
                    catalogue: $catalogue,
                    authorizer: $authorizer,
                    outbox: $outbox,
                    messageTranslator: $messageTranslator,
                    clock: $clock,
                )
                : null;
            $eav = ($composition->drivers['eav'] ?? false)
                ? new EavAttributeStore(
                    connection: $connection,
                    tables: $tables,
                    catalogue: $catalogue,
                    authorizer: $authorizer,
                    outbox: $outbox,
                    messageTranslator: $messageTranslator,
                    clock: $clock,
                )
                : null;

            if ($jsonb === null && $eav === null) {
                return $app->make(UnavailableAttributes::class);
            }

            return new ResolvingAttributeStore(new ConfiguredAttributeStoreResolver(
                policy: new AttributeStoragePolicy(default: $composition->storageDriver, entities: $composition->storageByEntity),
                jsonb: $jsonb,
                eav: $eav,
            ));
        });

        $this->app->scoped(SchemaOperations::class, function (Application $app): SchemaOperations {
            if (! $app->bound(EntityRegistry::class) || ! $app->bound(ModuleRegistry::class)) {
                return $app->make(UnavailableAttributes::class);
            }

            $composition = $app->make(AttributeComposition::class);
            $catalogue = $app->make(FieldCatalogue::class);

            if (($composition->drivers['ddl'] ?? false) && $app->bound('db')) {
                return new QueuedSchemaOperations(
                    connection: $app['db']->connection(),
                    tables: $app->make(AttributeTables::class),
                    catalogue: $catalogue,
                    authorizer: $app->make(DataScopeAuthorizer::class),
                );
            }

            return $app->make(UnavailableAttributes::class);
        });

        $this->app->scoped(FieldCatalogue::class, function (Application $app): FieldCatalogue {
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
        });
    }
}
