<?php

declare(strict_types=1);

namespace CoreX\Access;

use CoreX\Access\Errors\Internal\PackageProblemDefinitions;
use CoreX\Access\Internal\AccessSchemaSets;
use CoreX\Access\Internal\Support\AccessConfigSection;
use CoreX\Access\Laravel\AccessBindingsRegistrar;
use CoreX\Access\Laravel\AccessIntegrationRegistrar;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Errors\Laravel\ProblemDefinitions;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccessServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [AccessBindingsRegistrar::class, AccessIntegrationRegistrar::class];

    #[Override]
    public function register(): void
    {
        $this->app->tag([AccessSchemaSets::class], SchemaSetProvider::class);

        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

        $this->app->tag([AccessConfigSection::class], ConfigReportSection::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-access.php', 'corex-access');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }
    }

    public function boot(): void
    {
        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }

        $this->registerPublishing();
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/corex-access.php' => config_path('corex-access.php'),
        ], 'corex-access-config');

    }
}
