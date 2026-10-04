<?php

declare(strict_types=1);

namespace CoreX\Access;

use CoreX\Access\Errors\Internal\PackageProblemDefinitions;
use CoreX\Access\Internal\Support\AccessConfigSection;
use CoreX\Access\Laravel\AccessBindingsRegistrar;
use CoreX\Access\Laravel\AccessIntegrationRegistrar;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CoreAuthServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [AccessBindingsRegistrar::class, AccessIntegrationRegistrar::class];

    #[Override]
    public function register(): void
    {
        ProblemDefinitions::register($this->app, __DIR__.'/../composer.json', PackageProblemDefinitions::class);

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

        // Publish-only, mirroring axiomasoft/corex-tenancy's own tenant-DB migrations
        // (D33/A19/A62 precedent): a golden-template build assembles these
        // deliberately, never an ad hoc migrate on a live tenant connection.
        $this->publishes([
            __DIR__.'/../database/migrations/tenant' => database_path('migrations/tenant'),
        ], 'corex-access-migrations-tenant');

        // Cloud profile only (D102) — boxed installs never publish this tag,
        // wsp_workspaces/users belong to axiomasoft/corex-tenancy, absent there (AC-28).
        $this->publishes([
            __DIR__.'/../database/migrations/tenant-cloud' => database_path('migrations/tenant-cloud'),
        ], 'corex-access-migrations-tenant-cloud');
    }
}
