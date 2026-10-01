<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Tenancy\Impersonation\Internal\Models\ImpersonationGrant;
use CoreX\Tenancy\Laravel\Http\Middleware\AccountStatusGate;
use CoreX\Tenancy\Laravel\Http\Middleware\AllowImpersonatedWrites;
use CoreX\Tenancy\Laravel\Http\Middleware\DenyImpersonatedWrites;
use CoreX\Tenancy\Laravel\Http\Middleware\EnforceSessionLifetime;
use CoreX\Tenancy\Laravel\Http\Middleware\ImpersonationSessionGuard;
use CoreX\Tenancy\Laravel\Http\Middleware\RequireActiveMembership;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveTenantFromHost;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveWorkspace;
use CoreX\Tenancy\Laravel\Resolvers\HostTenantResolver;
use CoreX\Tenancy\Laravel\Support\StatusGatePolicy;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Workspace\Contracts\WorkspaceResolver;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Override;
use Stancl\Tenancy\Middleware\ScopeSessions;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class HttpRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->bind(
            ImpersonationGrant::class,
            static fn (Application $app): ImpersonationGrant => new ImpersonationGrant(
                options: $app->make(TenancyOptions::class),
            ),
        );
        $app->bind(
            ImpersonationSessionGuard::class,
            static fn (Application $app): ImpersonationSessionGuard => new ImpersonationSessionGuard(
                impersonation: $app->make(ImpersonationService::class),
                options: $app->make(TenancyOptions::class),
            ),
        );
        $app->bind(
            DenyImpersonatedWrites::class,
            static fn (Application $app): DenyImpersonatedWrites => new DenyImpersonatedWrites(
                impersonation: $app->make(ImpersonationService::class),
                statusGate: $app->make(StatusGatePolicy::class),
            ),
        );
        $app->bind(
            AccountStatusGate::class,
            static fn (Application $app): AccountStatusGate => new AccountStatusGate(
                statusGate: $app->make(StatusGatePolicy::class),
            ),
        );
        $app->bind(
            EnforceSessionLifetime::class,
            static fn (Application $app): EnforceSessionLifetime => new EnforceSessionLifetime(
                options: $app->make(TenancyOptions::class),
            ),
        );
        $app->bind(
            HostTenantResolver::class,
            static fn (Application $app): HostTenantResolver => new HostTenantResolver(
                app: $app,
                options: $app->make(TenancyOptions::class),
            ),
        );
        $app->bind(
            RequireActiveMembership::class,
            static fn (Application $app): RequireActiveMembership => new RequireActiveMembership(
                options: $app->make(TenancyOptions::class),
            ),
        );
        $app->bind(
            ResolveWorkspace::class,
            static fn (Application $app): ResolveWorkspace => new ResolveWorkspace(
                manager: $app->make(TenancyManager::class),
                resolver: $app->make(WorkspaceResolver::class),
                options: $app->make(TenancyOptions::class),
            ),
        );
    }

    #[Override]
    public function boot(Application $app): void
    {
        $options = $app->make(TenancyOptions::class);
        $router = $app->make(Router::class);

        // Alias only. A route without it keeps the account-wide admin context.
        // Never added to the tenant group: that would force every tenant route
        // through a workspace.
        $router->aliasMiddleware('tenancy.workspace', ResolveWorkspace::class);

        if ($options->hostIdentificationEnabled) {
            // Account is identified before auth: the user row lives in the tenant DB.
            $app->afterResolving(HttpKernel::class, static function (HttpKernel $kernel): void {
                if (method_exists($kernel, 'addToMiddlewarePriorityBefore')) {
                    $kernel->addToMiddlewarePriorityBefore(
                        before: AuthenticatesRequests::class,
                        middleware: ResolveTenantFromHost::class,
                    );
                }
            });

            $app['config']->set([
                'tenancy.identification.resolvers.'.HostTenantResolver::class => [
                    'cache' => $options->hostIdentificationCache,
                    'cache_ttl' => $options->hostIdentificationCacheTtl,
                    'cache_store' => $options->hostIdentificationCacheStore,
                ],
                'tenancy.identification.middleware' => [
                    ...$app['config']->get('tenancy.identification.middleware', []),
                    ResolveTenantFromHost::class,
                ],
            ]);

            $router->aliasMiddleware('tenancy.host', ResolveTenantFromHost::class);
            $router->aliasMiddleware('tenancy.status', AccountStatusGate::class);

            // stancl pre-registers tenant as an empty route-context group.
            // Merge into it; do not replace the existing list.
            $router->middlewareGroup('tenant', [
                ...($router->getMiddlewareGroups()['tenant'] ?? []),
                ResolveTenantFromHost::class,
                AccountStatusGate::class,
                ScopeSessions::class,
            ]);
        }

        if (! $options->oidcEnabled) {
            return;
        }

        // loadRoutesFrom skips a cached route file; a naked group does not.
        if (! ($app instanceof CachesRoutes && $app->routesAreCached())) {
            require dirname(__DIR__, 2).'/routes/oidc.php';
        }

        $router->aliasMiddleware('tenancy.oidc.session_lifetime', EnforceSessionLifetime::class);
        $router->middlewareGroup('tenant', [
            ...($router->getMiddlewareGroups()['tenant'] ?? []),
            EnforceSessionLifetime::class,
        ]);

        if (! $options->impersonation->enabled) {
            return;
        }

        $router->aliasMiddleware('tenancy.impersonation.allow', AllowImpersonatedWrites::class);
        $router->middlewareGroup('tenant', [
            ...($router->getMiddlewareGroups()['tenant'] ?? []),
            ImpersonationSessionGuard::class,
            DenyImpersonatedWrites::class,
        ]);
    }
}
