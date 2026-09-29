<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\RegistersNothingOnBoot;
use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Database\SchemaCatalogueSerializer;
use CoreX\Tenancy\Provisioning\Database\TemplateIntegrityLock;
use CoreX\Tenancy\Provisioning\Database\TemplateIntegrityVerifier as DatabaseTemplateIntegrityVerifier;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** @internal Package composition type. */
final class DatabaseRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(SchemaCatalogueSerializer::class);
        $app->singleton(TemplateIntegrityLock::class);
        $app->singleton(
            DatabaseTemplateIntegrityVerifier::class,
            static fn (Application $app): DatabaseTemplateIntegrityVerifier => new DatabaseTemplateIntegrityVerifier(
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
                serializer: $app->make(SchemaCatalogueSerializer::class),
                lock: $app->make(TemplateIntegrityLock::class),
            ),
        );
        $app->singleton(
            TemplateIntegrityVerifier::class,
            static fn (Application $app): DatabaseTemplateIntegrityVerifier => $app->make(
                DatabaseTemplateIntegrityVerifier::class,
            ),
        );
    }
}
