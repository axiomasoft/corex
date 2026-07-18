<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

use CoreX\Tenancy\Commands\PendingClearCommand;
use CoreX\Tenancy\Commands\PendingCreateCommand;
use CoreX\Tenancy\Jobs\CreateDatabaseFromTemplate;
use CoreX\Tenancy\Provisioning\PendingPool;
use CoreX\Tenancy\Provisioning\StanclTenantDatabaseProvisioner;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Override;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;
use Stancl\Tenancy\Events\TenantCreated;

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
        ]);

        $this->app->bind(UniqueIdentifierGenerator::class, config('tenancy.id_generator'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                PendingCreateCommand::class,
                PendingClearCommand::class,
            ]);
        }

        Event::listen(
            TenantCreated::class,
            JobPipeline::make([CreateDatabaseFromTemplate::class])
                ->send(static fn (TenantCreated $event): mixed => $event->tenant)
                ->shouldBeQueued(false)
                ->toListener(),
        );
    }
}
