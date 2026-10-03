<?php

declare(strict_types=1);

namespace CoreX\Panels\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Panels\Contracts\PanelCatalogue;
use CoreX\Panels\Contracts\PanelProvider;
use CoreX\Panels\Internal\EntityViewSchemaProvider;
use CoreX\Panels\Internal\ModulePanelCatalogue;
use CoreX\Panels\Internal\PanelsOptions;
use CoreX\Panels\SavedViews\Contracts\ViewSchemaProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class PanelsBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(PanelsOptions::class, static fn (Application $app): PanelsOptions => PanelsOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-panels', []),
        ));
        $app->singleton(PanelCatalogue::class, static fn (Application $app): ModulePanelCatalogue => new ModulePanelCatalogue(
            providers: self::panelProviders($app),
            entities: $app->make(EntityRegistry::class),
            modules: $app->make(ModuleRegistry::class),
        ));
        $app->singleton(ViewSchemaProvider::class, static fn (Application $app): EntityViewSchemaProvider => new EntityViewSchemaProvider(
            entities: $app->make(EntityRegistry::class),
        ));
    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(PanelsOptions::class);
    }

    /** @return iterable<PanelProvider> */
    private static function panelProviders(Application $app): iterable
    {
        /** @var iterable<PanelProvider> $providers */
        $providers = $app->tagged($app->make(PanelsOptions::class)->providerTag);

        return $providers;
    }
}
