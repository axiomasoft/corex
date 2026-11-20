<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\DomainTrust\Internal\Models\Domain;
use CoreX\Tenancy\Impersonation\Internal\ImpersonatedModelWriteGuard;
use CoreX\Tenancy\Laravel\Support\HostResolverCacheInvalidator;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;

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

        // Fence host mappings after committed saves/deletes, including cascade deletion.
        // The invalidator itself is a no-op while host identification or its cache is off.
        $events->listen('eloquent.saved: '.Account::class, [HostResolverCacheInvalidator::class, 'accountChanged']);
        $events->listen('eloquent.deleted: '.Account::class, [HostResolverCacheInvalidator::class, 'accountChanged']);
        $events->listen('eloquent.saved: '.Domain::class, [HostResolverCacheInvalidator::class, 'domainChanged']);
        $events->listen('eloquent.deleted: '.Domain::class, [HostResolverCacheInvalidator::class, 'domainChanged']);

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
