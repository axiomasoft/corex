<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

use CoreX\Tenancy\Errors\Internal\PackageProblemDefinitions;
use CoreX\Tenancy\Internal\TenancySchemaSets;
use CoreX\Tenancy\Laravel\RegistrarFactory;
use CoreX\Tenancy\Laravel\Support\TenancyConfigSection;
use CoreX\Tenancy\Laravel\TenancyPortBindings;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Contracts\ConfigReportSectionInterface as ConfigReportSection;
use Packman\Errors\Laravel\ProblemDefinitions;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TenancyServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([TenancySchemaSets::class], SchemaSetProvider::class);

        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

        TenancyPortBindings::register($this->app);

        $this->app->tag([TenancyConfigSection::class], ConfigReportSection::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-tenancy.php', 'corex-tenancy');

        foreach (RegistrarFactory::forApplication($this->app) as $registrar) {
            $registrar->register($this->app);
        }
    }

    public function boot(): void
    {

        $this->publishes([
            __DIR__.'/../config/corex-tenancy.php' => config_path('corex-tenancy.php'),
        ], 'corex-tenancy-config');

        foreach (RegistrarFactory::forApplication($this->app) as $registrar) {
            $registrar->boot($this->app);
        }
    }
}
