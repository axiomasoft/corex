<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\DomainTrust\Internal\Models\Domain;
use CoreX\Tenancy\Impersonation\Internal\ImpersonatedModelWriteGuard;
use CoreX\Tenancy\Laravel\Support\HostResolverCacheInvalidator;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Runtime\Internal\QueueTenantContext;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Override;

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

        // Resolved from the container per event: QueueTenantContext is a singleton frame stack.
        // JobAttempted (dispatched from the `finally` of Worker::process and SyncQueue::executeJob)
        // is the only point that ends a job's context.
        $events->listen(JobProcessing::class, [QueueTenantContext::class, 'processing']);
        $events->listen(JobAttempted::class, [QueueTenantContext::class, 'finished']);

        // Host → account entries are forgotten before a model's rows go away, and after any save.
        // The invalidator itself is a no-op while host identification or its cache is off.
        $events->listen('eloquent.saved: '.Account::class, [HostResolverCacheInvalidator::class, 'accountChanged']);
        $events->listen('eloquent.deleting: '.Account::class, [HostResolverCacheInvalidator::class, 'accountChanged']);
        $events->listen('eloquent.saved: '.Domain::class, [HostResolverCacheInvalidator::class, 'domainChanged']);
        $events->listen('eloquent.deleting: '.Domain::class, [HostResolverCacheInvalidator::class, 'domainChanged']);

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
