<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Tenancy\Errors\Internal\PackageProblemDefinitions;
use CoreX\Tenancy\Internal\TenancySchemaSets;
use CoreX\Tenancy\Laravel\AuthenticationRegistrar;
use CoreX\Tenancy\Laravel\ConsoleRegistrar;
use CoreX\Tenancy\Laravel\DatabaseRegistrar;
use CoreX\Tenancy\Laravel\DomainTrustRegistrar;
use CoreX\Tenancy\Laravel\EventsRegistrar;
use CoreX\Tenancy\Laravel\HttpRegistrar;
use CoreX\Tenancy\Laravel\IdentityRegistrar;
use CoreX\Tenancy\Laravel\ProvisioningRegistrar;
use CoreX\Tenancy\Laravel\RuntimeRegistrar;
use CoreX\Tenancy\Laravel\Support\TenancyConfigSection;
use CoreX\Tenancy\Laravel\TenancyPortBindings;
use CoreX\Tenancy\Laravel\WakeupRegistrar;
use CoreX\Tenancy\Laravel\WorkspaceRegistrar;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Override;

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

        foreach ($this->registrars() as $registrar) {
            $registrar->register($this->app);
        }
    }

    public function boot(): void
    {

        $this->publishes([
            __DIR__.'/../config/corex-tenancy.php' => config_path('corex-tenancy.php'),
        ], 'corex-tenancy-config');

        foreach ($this->registrars() as $registrar) {
            $registrar->boot($this->app);
        }
    }

    /** @return list<PackageRegistrar> */
    private function registrars(): array
    {
        $corexTenancy = $this->app->make(Repository::class)->get('corex-tenancy', []);
        $corexTenancy = is_array($corexTenancy) ? $corexTenancy : [];

        $registrars = [
            new RuntimeRegistrar,
            new DatabaseRegistrar,
            new ProvisioningRegistrar,
            new IdentityRegistrar,
            new WorkspaceRegistrar,
            new DomainTrustRegistrar,
            new ConsoleRegistrar,
            new HttpRegistrar,
            new EventsRegistrar,
        ];

        // TenancyOptions stays bind()-lazy (P7.2/P8.2): make() here would
        // validate OIDC before Testbench defineEnvironment fills issuer fields.
        $oidc = is_array($corexTenancy['oidc'] ?? null) ? $corexTenancy['oidc'] : [];
        $oidcEnabled = (bool) ($oidc['enabled'] ?? false);

        if ($oidcEnabled) {
            $registrars[] = new AuthenticationRegistrar;
        }

        $wakeup = is_array($corexTenancy['wakeup'] ?? null) ? $corexTenancy['wakeup'] : [];
        $wakeupDriver = is_string($wakeup['driver'] ?? null)
            ? $wakeup['driver']
            : 'local_sweep';

        // Contracts live in CoreX\Scheduling; the sweeper is resolved lazily from whichever backend binds it.
        // A missing backend is reported by TenancyConfigSection::adapters() and fails loudly on first use.
        if ($wakeupDriver === 'root_registry') {
            $registrars[] = new WakeupRegistrar;
        }

        return $registrars;
    }
}
