<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

use CoreX\Tenancy\Bootstrappers\WorkspaceContextBootstrapper;
use CoreX\Tenancy\Commands\IdentitiesReconcileCommand;
use CoreX\Tenancy\Commands\PendingClearCommand;
use CoreX\Tenancy\Commands\PendingCreateCommand;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\Identity\DatabaseIdentitySyncer;
use CoreX\Tenancy\Identity\IdentitySyncer;
use CoreX\Tenancy\Jobs\CreateDatabaseFromTemplate;
use CoreX\Tenancy\Listeners\RevertTenancyOnJobException;
use CoreX\Tenancy\Provisioning\PendingPool;
use CoreX\Tenancy\Provisioning\StanclTenantDatabaseProvisioner;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Override;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Listeners\BootstrapTenancy;
use Stancl\Tenancy\Listeners\RevertToCentralContext;
use Stancl\Tenancy\Tenancy as Stancl;

final class TenancyServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy.php', 'tenancy');

        $this->app->singleton(
            StanclTenantDatabaseProvisioner::class,
            static fn (): StanclTenantDatabaseProvisioner => new StanclTenantDatabaseProvisioner(
                (string) config('tenancy.central_connection'),
            ),
        );

        $this->app->singleton(
            PendingPool::class,
            static fn (Application $app): PendingPool => new PendingPool(
                (string) config('tenancy.central_connection'),
                $app->make(StanclTenantDatabaseProvisioner::class),
            ),
        );

        // Rebind the boxed defaults (CoreServiceProvider::register(),
        // registered before this provider — corex/tenancy requires corex/core)
        // to the cloud implementations (D10, P2.5): a container binding, not
        // an `if(cloud)` branch. Same pattern as ModulesServiceProvider's
        // SettingDefaultsProvider rebind (P1.25).
        $this->app->singleton(
            StanclTenancyManager::class,
            static fn (Application $app): StanclTenancyManager => new StanclTenancyManager($app->make(Stancl::class)),
        );
        $this->app->singleton(TenancyManager::class, static fn (Application $app): StanclTenancyManager => $app->make(StanclTenancyManager::class));
        $this->app->singleton(TenantContextResolver::class, TenancyManagerContextResolver::class);

        $this->app->singleton(
            DatabaseIdentitySyncer::class,
            static fn (Application $app): DatabaseIdentitySyncer => new DatabaseIdentitySyncer(
                (string) config('tenancy.central_connection'),
                $app->make(TenancyManager::class),
            ),
        );
        $this->app->singleton(IdentitySyncer::class, static fn (Application $app): DatabaseIdentitySyncer => $app->make(DatabaseIdentitySyncer::class));

        // Mirror stancl's own TenancyServiceProvider::register() bootstrapper
        // singleton loop, but over OUR `bootstrapper_order` (config/tenancy.php)
        // rather than stancl's default list: ScoutPrefixBootstrapper isn't in
        // stancl's own default set (commented out there), so without this it
        // would resolve fresh on every bootstrap()/revert() call and lose its
        // stateful $originalScoutPrefix between the two. NOT re-running
        // `__constructStatic` here for bootstrappers already in stancl's own
        // default list — its own register() already called it once
        // (QueueTenancyBootstrapper registers global queue event listeners
        // there; calling it twice would double-register them).
        // WorkspaceContextBootstrapper (P2.6) is NOT part of stancl's default
        // list, so its own static hook is never reached from there — called
        // explicitly here, exactly once.
        foreach (config('tenancy.bootstrapper_order', []) as $bootstrapper) {
            if ($bootstrapper === WorkspaceContextBootstrapper::class) {
                WorkspaceContextBootstrapper::__constructStatic($this->app);
            }

            $this->app->singleton($bootstrapper);
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
            __DIR__.'/../database/migrations/root' => database_path('migrations'),
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

        // Projected here (not in register()) so it deterministically wins
        // over stancl's own TenancyServiceProvider::register() regardless of
        // provider registration order — see config/tenancy.php comment.
        config([
            'tenancy.models.tenant' => config('tenancy.tenant_model'),
            'tenancy.database.central_connection' => config('tenancy.central_connection'),
            'tenancy.database.managers.pgsql' => config('tenancy.tenant_database_manager'),
            'tenancy.bootstrappers' => config('tenancy.bootstrapper_order'),
            'tenancy.cache.prefix' => config('tenancy.cache_prefix'),
        ]);

        $this->app->bind(UniqueIdentifierGenerator::class, config('tenancy.id_generator'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                PendingCreateCommand::class,
                PendingClearCommand::class,
                IdentitiesReconcileCommand::class,
            ]);
        }

        Event::listen(
            TenantCreated::class,
            JobPipeline::make([CreateDatabaseFromTemplate::class])
                ->send(static fn (TenantCreated $event): mixed => $event->tenant)
                ->shouldBeQueued(false)
                ->toListener(),
        );

        // stancl ships these two listeners but wires them only in the
        // `tenancy:install` app-level stub (assets/TenancyServiceProvider.stub.php)
        // — not automatically by its own package provider. Without this,
        // Tenancy::initialize()/end() only flip the `initialized` flag and
        // fire events; no bootstrapper ever actually runs.
        Event::listen(TenancyInitialized::class, BootstrapTenancy::class);
        Event::listen(TenancyEnded::class, RevertToCentralContext::class);

        // F-Q1 (D76): QueueTenancyBootstrapper reverts only on
        // JobProcessed/JobFailed, never on JobExceptionOccurred (retry path)
        // — see RevertTenancyOnJobException docblock.
        Event::listen(JobExceptionOccurred::class, RevertTenancyOnJobException::class);
    }
}
