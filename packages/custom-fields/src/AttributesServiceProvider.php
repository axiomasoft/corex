<?php

declare(strict_types=1);

namespace CoreX\CustomFields;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\CustomFields\Contracts\AttributeFilter;
use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Contracts\SchemaOperations;
use CoreX\CustomFields\Data\AttributeStoragePolicy;
use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Internal\Integration\AttributeMessageTranslator;
use CoreX\CustomFields\Internal\Laravel\AttributeComposition;
use CoreX\CustomFields\Internal\Laravel\AttributeCompositionRegistrar;
use CoreX\CustomFields\Internal\Laravel\AttributeMessagingRegistrar;
use CoreX\CustomFields\Internal\Query\SchemaAttributeFilter;
use CoreX\CustomFields\Internal\Schema\ModuleFieldCatalogue;
use CoreX\CustomFields\Internal\Schema\QueuedSchemaOperations;
use CoreX\CustomFields\Internal\Schema\StaticFieldCatalogue;
use CoreX\CustomFields\Internal\Storage\ConfiguredAttributeStoreResolver;
use CoreX\CustomFields\Internal\Storage\ResolvingAttributeStore;
use CoreX\CustomFields\Internal\UnavailableAttributes;
use CoreX\CustomFields\Internal\Values\EavAttributeStore;
use CoreX\CustomFields\Internal\Values\JsonbAttributeStore;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Psr\Clock\ClockInterface;

final class AttributesServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-custom-fields.php', 'corex-custom-fields');
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
