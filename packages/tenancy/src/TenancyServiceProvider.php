<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

use CoreX\Support\PackageRegistrar;
use CoreX\Tenancy\Laravel\AuthenticationRegistrar;
use CoreX\Tenancy\Laravel\ConsoleRegistrar;
use CoreX\Tenancy\Laravel\DatabaseRegistrar;
use CoreX\Tenancy\Laravel\DomainTrustRegistrar;
use CoreX\Tenancy\Laravel\EventsRegistrar;
use CoreX\Tenancy\Laravel\HttpRegistrar;
use CoreX\Tenancy\Laravel\IdentityRegistrar;
use CoreX\Tenancy\Laravel\ProvisioningRegistrar;
use CoreX\Tenancy\Laravel\RuntimeRegistrar;
use CoreX\Tenancy\Laravel\WakeupRegistrar;
use CoreX\Tenancy\Laravel\WorkspaceRegistrar;
use CoreX\Wakeup\Contracts\DueWakeupSweeper;
use CoreX\Wakeup\Contracts\WakeupScheduleHint;
use Illuminate\Support\ServiceProvider;
use Override;

final class TenancyServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy.php', 'tenancy');
        $this->mergeConfigFrom(__DIR__.'/../config/corex-tenancy.php', 'corex-tenancy');

        foreach ($this->registrars() as $registrar) {
            $registrar->register($this->app);
        }
    }

    public function boot(): void
    {
        // root_* migrations are PG-only DDL (jsonb/CHECK/UNIQUE NULLS NOT
        // DISTINCT) targeting the CENTRAL connection, never a tenant/account
        // database (D14). Publish-only, not loadMigrationsFrom (mirrors
        // corex/core CoreServiceProvider D33/A19/A62): an unconditional load
        // would run root_ DDL against whatever connection a consumer's own
        // migrator defaults to. A consumer publishes then runs
        // `migrate --database=<central> --path=database/migrations/root`.
        $this->publishes([
            __DIR__.'/../database/migrations/root' => database_path('migrations/root'),
        ], 'tenancy-migrations');

        // Tenant-DB DDL (`users`, D14/D16) — same publish-only rationale as
        // root above; a consumer's golden-template build assembles these
        // (P2.12), never an ad hoc migrate on a live tenant connection.
        $this->publishes([
            __DIR__.'/../database/migrations/tenant' => database_path('migrations/tenant'),
        ], 'tenancy-migrations-tenant');

        $this->publishes([
            __DIR__.'/../config/tenancy.php' => config_path('tenancy.php'),
        ], 'tenancy-config');

        foreach ($this->registrars() as $registrar) {
            $registrar->boot($this->app);
        }
    }

    /** @return list<PackageRegistrar> */
    private function registrars(): array
    {
        $tenancy = $this->app['config']->get('tenancy', []);
        $tenancy = is_array($tenancy) ? $tenancy : [];
        $corexTenancy = $this->app['config']->get('corex-tenancy', []);
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
        $oidcEnabled = (bool) ($tenancy['oidc']['enabled'] ?? false);

        if ($oidcEnabled) {
            $registrars[] = new AuthenticationRegistrar;
        }

        $wakeupDriver = is_string($corexTenancy['wakeup']['driver'] ?? null)
            ? $corexTenancy['wakeup']['driver']
            : 'local_sweep';

        if (
            $wakeupDriver === 'root_registry'
            && interface_exists(WakeupScheduleHint::class)
            && interface_exists(DueWakeupSweeper::class)
        ) {
            $registrars[] = new WakeupRegistrar;
        }

        return $registrars;
    }
}
