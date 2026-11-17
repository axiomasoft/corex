<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Laravel;

use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Contracts\SchemaOperations;
use CoreX\CustomFields\Data\AttributeTables;
use CoreX\CustomFields\Internal\Schema\QueuedSchemaOperations;
use CoreX\CustomFields\Internal\UnavailableAttributes;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

/** @internal Composition of the SchemaOperations binding. */
final class SchemaOperationsFactory
{
    public function make(Application $app): SchemaOperations
    {
        if (! $app->bound(EntityRegistry::class) || ! $app->bound(ModuleRegistry::class)) {
            return $app->make(UnavailableAttributes::class);
        }

        $composition = $app->make(AttributeComposition::class);
        $catalogue = $app->make(FieldCatalogue::class);

        if (($composition->drivers['ddl'] ?? false) && $app->bound('db')) {
            return new QueuedSchemaOperations(
                connection: $app->make(DatabaseManager::class)->connection(),
                tables: $app->make(AttributeTables::class),
                catalogue: $catalogue,
                authorizer: $app->make(DataScopeAuthorizer::class),
            );
        }

        return $app->make(UnavailableAttributes::class);
    }
}
