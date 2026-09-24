<?php

declare(strict_types=1);

namespace CoreX\Attributes;

use CoreX\Attributes\Contracts\AttributeFilter;
use CoreX\Attributes\Contracts\AttributeStore;
use CoreX\Attributes\Contracts\FieldCatalogue;
use CoreX\Attributes\Contracts\SchemaOperations;
use CoreX\Attributes\Data\AttributeTables;
use CoreX\Attributes\Internal\Engine\EavAttributeStore;
use CoreX\Attributes\Internal\Engine\JsonbAttributeStore;
use CoreX\Attributes\Internal\Engine\QueuedSchemaOperations;
use CoreX\Attributes\Internal\Engine\StructuralAttributeFilter;
use CoreX\Attributes\Internal\Laravel\AttributeComposition;
use CoreX\Attributes\Internal\Registry\RegistryFieldCatalogue;
use CoreX\Attributes\Internal\Registry\StaticFieldCatalogue;
use CoreX\Attributes\Internal\UnavailableAttributes;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class AttributesServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-attributes.php', 'corex-attributes');
        $this->app->singleton(AttributeTables::class, function (Application $app): AttributeTables {
            /** @var array<string, mixed> $tables */
            $tables = (array) $app['config']->get('corex-attributes.tables', []);

            return new AttributeTables(
                documents: (string) ($tables['documents'] ?? 'sys_attribute_documents'),
                writeTokens: (string) ($tables['write_tokens'] ?? 'sys_attribute_write_tokens'),
                eavValues: (string) ($tables['eav_values'] ?? 'sys_attribute_eav_values'),
                eavRecords: (string) ($tables['eav_records'] ?? 'sys_attribute_eav_records'),
                schemaOperations: (string) ($tables['schema_operations'] ?? 'sys_attribute_schema_operations'),
            );
        });
        $this->app->singleton(AttributeComposition::class, function (Application $app): AttributeComposition {
            /** @var array<string, mixed> $config */
            $config = (array) $app['config']->get('corex-attributes', []);

            return AttributeComposition::fromArray($config);
        });
        $this->app->scoped(UnavailableAttributes::class);

        $this->app->scoped(AttributeFilter::class, StructuralAttributeFilter::class);

        $this->app->scoped(AttributeStore::class, function (Application $app): AttributeStore {
            $composition = $app->make(AttributeComposition::class);
            $catalogue = $app->make(FieldCatalogue::class);
            $tables = $app->make(AttributeTables::class);

            if ($composition->storageDriver === 'eav' && ($composition->drivers['eav'] ?? false) && $app->bound('db')) {
                return new EavAttributeStore(connection: $app['db']->connection(), tables: $tables, catalogue: $catalogue, authorizer: $app->make(DataScopeAuthorizer::class));
            }

            if (($composition->drivers['jsonb'] ?? false) && $app->bound('db')) {
                return new JsonbAttributeStore(connection: $app['db']->connection(), tables: $tables, catalogue: $catalogue, authorizer: $app->make(DataScopeAuthorizer::class));
            }

            return $app->make(UnavailableAttributes::class);
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
                return $catalogue->appending(new RegistryFieldCatalogue(
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
