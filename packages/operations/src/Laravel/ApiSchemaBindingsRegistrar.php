<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Override;

/** Binds the explicit budget and tenant schema identities. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ApiSchemaBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(ApiSchemaRegistrar::class, static fn (Application $app): ApiSchemaRegistrar => ApiSchemaRegistrar::fromSchema(
            $app->make(Repository::class)->get('corex-operations.schema'),
            $app->make(DatabaseManager::class),
        ));
    }

    #[Override]
    public function boot(Application $app): void {}
}
