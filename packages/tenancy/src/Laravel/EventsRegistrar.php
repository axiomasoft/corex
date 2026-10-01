<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Tenancy\Impersonation\Internal\ImpersonatedModelWriteGuard;
use CoreX\Tenancy\Laravel\Listeners\RevertTenancyOnJobException;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Override;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Listeners\BootstrapTenancy;
use Stancl\Tenancy\Listeners\RevertToCentralContext;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class EventsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void {}

    #[Override]
    public function boot(Application $app): void
    {
        $options = $app->make(TenancyOptions::class);
        $events = $app->make(Dispatcher::class);

        $events->listen(
            TenantCreated::class,
            JobPipeline::make($options->provisioningPipeline)
                ->send(static fn (TenantCreated $event): mixed => $event->tenant)
                ->shouldBeQueued(false)
                ->toListener(),
        );

        // stancl ships these two listeners but wires them only in the
        // tenancy:install stub, not in its package provider. Without them
        // Tenancy::initialize()/end() never run a bootstrapper.
        $events->listen(TenancyInitialized::class, BootstrapTenancy::class);
        $events->listen(TenancyEnded::class, RevertToCentralContext::class);

        // QueueTenancyBootstrapper reverts on JobProcessed/JobFailed only,
        // never on the retry path JobExceptionOccurred.
        $events->listen(JobExceptionOccurred::class, RevertTenancyOnJobException::class);

        // Wildcard listeners stay inside the OIDC block: impersonation can be
        // configured while OIDC is off, and that profile registers nothing.
        if ($options->oidcEnabled && $options->impersonation->enabled) {
            // Resolve per event. A captured instance would keep the first
            // request's scoped impersonation service in a long-lived worker.
            $events->listen('eloquent.saving: *', [ImpersonatedModelWriteGuard::class, 'handle']);
            $events->listen('eloquent.deleting: *', [ImpersonatedModelWriteGuard::class, 'handle']);
            $events->listen('eloquent.restoring: *', [ImpersonatedModelWriteGuard::class, 'handle']);
        }
    }
}
