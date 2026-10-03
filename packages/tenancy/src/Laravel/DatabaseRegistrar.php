<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Laravel\RegistersNothingOnBoot;
use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Internal\Database\SchemaCatalogueSerializer;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityLock;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DatabaseRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(SchemaCatalogueSerializer::class);
        $app->singleton(TemplateIntegrityLock::class);
        $app->singleton(
            \CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier::class,
            static fn (Application $app): \CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier => new \CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier(
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
                serializer: $app->make(SchemaCatalogueSerializer::class),
                lock: $app->make(TemplateIntegrityLock::class),
                options: $app->make(TenancyOptions::class),
                connections: $app->make(ConnectionResolverInterface::class),
                connectionFactory: $app->make(ConnectionFactory::class),
            ),
        );
        $app->singleton(
            TemplateIntegrityVerifier::class,
            static fn (Application $app): \CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier => $app->make(
                \CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier::class,
            ),
        );
    }
}
