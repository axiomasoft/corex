<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;

/** Binds the explicit budget and tenant schema identities. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ApiSchemaBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(ApiSchemaConnections::class, static fn (Application $app): ApiSchemaConnections => ApiSchemaConnections::fromSchema(
            schema: $app->make(Repository::class)->get('corex-operations.schema'),
            database: $app->make(DatabaseManager::class),
        ));
    }

    #[Override]
    public function boot(Application $app): void {}
}
