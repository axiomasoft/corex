<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Laravel;

use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\CustomFields\Schemas\Contracts\DeclaredFields;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldProjectionCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemaDependencies;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemas;
use CoreX\CustomFields\Schemas\Contracts\SchemaHeads;
use CoreX\CustomFields\Schemas\Internal\DatabaseFieldCatalogue;
use CoreX\CustomFields\Schemas\Internal\DatabaseFieldProjectionCatalogue;
use CoreX\CustomFields\Schemas\Internal\DatabaseFieldSchemas;
use CoreX\CustomFields\Schemas\Internal\DatabaseSchemaHeads;
use CoreX\CustomFields\Schemas\Internal\DeclaredSchema;
use CoreX\CustomFields\Schemas\Internal\DirectDdlRoute;
use CoreX\CustomFields\Schemas\Internal\SchemaJournal;
use CoreX\CustomFields\Schemas\Internal\SchemaReferences;
use CoreX\CustomFields\Schemas\Internal\SchemaSteps;
use CoreX\CustomFields\Schemas\Internal\SchemaWorker;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\Data\Contracts\DataScopeCodec;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Psr\Clock\ClockInterface;

#[Internal]
final class FieldSchemaRegistrar implements PackageRegistrar
{
    public function register(Application $app): void
    {
        $app->scoped(DeclaredSchema::class, static fn (Application $app): DeclaredSchema => new DeclaredSchema($app->make(FieldTypes::class),
            static fn (): iterable => self::tagged($app, DeclaredFields::class),
            $app->bound(EntityRegistry::class) ? $app->make(EntityRegistry::class) : null, $app->bound(ModuleRegistry::class) ? $app->make(ModuleRegistry::class) : null));
        $app->scoped(DirectDdlRoute::class, static fn (Application $app): DirectDdlRoute => new DirectDdlRoute($app->make(CurrentWorkScope::class),
            $app->bound(AccountConnectionResolver::class) ? $app->make(AccountConnectionResolver::class) : null, $app->make(Repository::class)->get('corex-custom-fields.ddl', true) === true));
        $app->scoped(SchemaReferences::class, static fn (Application $app): SchemaReferences => new SchemaReferences(static fn (): iterable => self::tagged($app, FieldSchemaDependencies::class)));
        $app->scoped(DatabaseSchemaHeads::class);
        $app->alias(DatabaseSchemaHeads::class, SchemaHeads::class);
        $app->scoped(FieldSchemas::class, DatabaseFieldSchemas::class);
        $app->scoped(FieldCatalogue::class, DatabaseFieldCatalogue::class);
        $app->scoped(FieldProjectionCatalogue::class, DatabaseFieldProjectionCatalogue::class);
        $app->scoped(SchemaWorker::class, static fn (Application $app): SchemaWorker => new SchemaWorker($app->make(DatabaseManager::class),
            $app->make(TenantContextResolver::class), $app->make(DataScopeFactory::class), $app->make(WorkScopeRunner::class), $app->make(StoredWorkRunner::class),
            $app->make(CurrentWorkScope::class), $app->make(DataScopeCodec::class), $app->make(SchemaJournal::class), $app->make(ClockInterface::class),
            static fn (): SchemaSteps => $app->make(SchemaSteps::class)));
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $type
     * @return iterable<T>
     */
    private static function tagged(Application $app, string $type): iterable
    {
        foreach ($app->tagged($type) as $contribution) {
            if (! $contribution instanceof $type) {
                throw new InvalidArgumentException('Invalid field schema contribution.');
            }
            yield $contribution;
        }
    }

    public function boot(Application $app): void
    {
        if ($app->runningInConsole()) {
            $app->make(Dispatcher::class)->listen(ArtisanStarting::class, static fn (ArtisanStarting $event) => $event->artisan->resolveCommands([RunFieldSchemaWorker::class]));
        }
    }
}
