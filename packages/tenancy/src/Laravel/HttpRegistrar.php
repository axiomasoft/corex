<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Impersonation\Internal\Models\ImpersonationGrant;
use CoreX\Tenancy\Laravel\Http\Middleware\AccountStatusGate;
use CoreX\Tenancy\Laravel\Http\Middleware\AllowImpersonatedWrites;
use CoreX\Tenancy\Laravel\Http\Middleware\DenyImpersonatedWrites;
use CoreX\Tenancy\Laravel\Http\Middleware\EnforceSessionLifetime;
use CoreX\Tenancy\Laravel\Http\Middleware\ImpersonationSessionGuard;
use CoreX\Tenancy\Laravel\Http\Middleware\RequireActiveMembership;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveTenantFromHost;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveWorkspace;
use CoreX\Tenancy\Laravel\Http\Middleware\ScopeSessionToTenant;
use CoreX\Tenancy\Laravel\Support\HostResolverCacheInvalidator;
use CoreX\Tenancy\Laravel\Support\StatusGatePolicy;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Runtime\Internal\HostAccountResolver;
use CoreX\Tenancy\Workspace\Contracts\WorkspaceResolver;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Foundation\Http\Kernel as FoundationKernel;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Override;
use Packman\Foundation\Contracts\PackageRegistrar;
use RuntimeException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class HttpRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->bind(
            ImpersonationGrant::class,
            static fn (Application $app): ImpersonationGrant => (new ImpersonationGrant)
                ->setConnection($app->make(TenancyOptions::class)->centralConnection),
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
                contexts: $app->make(TenantContextResolver::class),
                accounts: $app->make(AccountRead::class),
                auth: $app->make(AuthFactory::class),
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
            ),
        );
        $app->bind(
            EnforceSessionLifetime::class,
            static fn (Application $app): EnforceSessionLifetime => new EnforceSessionLifetime(
                options: $app->make(TenancyOptions::class),
            ),
        );
        $app->bind(
            HostAccountResolver::class,
            static fn (Application $app): HostAccountResolver => new HostAccountResolver(
                connections: $app->make(ConnectionResolverInterface::class),
                cache: $app->make(CacheFactory::class),
                options: $app->make(TenancyOptions::class),
            ),
        );
        $app->bind(
            HostResolverCacheInvalidator::class,
            static fn (Application $app): HostResolverCacheInvalidator => new HostResolverCacheInvalidator(
                connections: $app->make(ConnectionResolverInterface::class),
                cache: $app->make(CacheFactory::class),
                options: $app->make(TenancyOptions::class),
            ),
        );
        $app->bind(
            ResolveTenantFromHost::class,
            static fn (Application $app): ResolveTenantFromHost => new ResolveTenantFromHost(
                manager: $app->make(TenancyManager::class),
                hosts: $app->make(HostAccountResolver::class),
                accounts: $app->make(AccountRead::class),
                container: $app,
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
            ),
        );
        $app->bind(
            ScopeSessionToTenant::class,
            static fn (Application $app): ScopeSessionToTenant => new ScopeSessionToTenant(
                manager: $app->make(TenancyManager::class),
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
            $app->afterResolving(HttpKernel::class, self::prioritiseHostMiddleware(...));

            $router->aliasMiddleware('tenancy.host', ResolveTenantFromHost::class);
            $router->aliasMiddleware('tenancy.status', AccountStatusGate::class);

            // Merge into an existing `tenant` group; do not replace the list. Priority (below)
            // sorts the three entries around StartSession.
            $router->middlewareGroup('tenant', [
                ...self::tenantMiddleware($router),
                ResolveTenantFromHost::class,
                ScopeSessionToTenant::class,
                AccountStatusGate::class,
            ]);
        }

        if (! $options->oidcEnabled) {
            return;
        }

        // loadRoutesFrom skips a cached route file; a naked group does not.
        if (! ($app instanceof CachesRoutes && self::routesAreCached($app))) {
            require dirname(__DIR__, 2).'/routes/oidc.php';
        }

        $router->aliasMiddleware('tenancy.oidc.session_lifetime', EnforceSessionLifetime::class);
        $router->middlewareGroup('tenant', [
            ...self::tenantMiddleware($router),
            EnforceSessionLifetime::class,
        ]);

        if (! $options->impersonation->enabled) {
            return;
        }

        $router->aliasMiddleware('tenancy.impersonation.allow', AllowImpersonatedWrites::class);
        $router->middlewareGroup('tenant', [
            ...self::tenantMiddleware($router),
            ImpersonationSessionGuard::class,
            DenyImpersonatedWrites::class,
        ]);
    }

    private static function routesAreCached(CachesRoutes $app): bool
    {
        return $app->routesAreCached();
    }

    /** @return array<array-key, mixed> */
    private static function tenantMiddleware(Router $router): array
    {
        $middleware = $router->getMiddlewareGroups()['tenant'] ?? [];

        if (! is_array($middleware)) {
            throw new RuntimeException('The tenant middleware group must be an array.');
        }

        return $middleware;
    }

    /**
     * The account is identified before StartSession (so the session handler is built for the
     * account and saved inside it) and before any user resolution; the session is bound to the
     * account right after StartSession and before errors/auth middleware; the status gate follows.
     */
    private static function prioritiseHostMiddleware(HttpKernel $kernel): void
    {
        if (! $kernel instanceof FoundationKernel) {
            return;
        }

        $kernel->addToMiddlewarePriorityBefore(StartSession::class, ResolveTenantFromHost::class);
        $kernel->addToMiddlewarePriorityBefore(
            [ShareErrorsFromSession::class, AuthenticatesRequests::class, AuthenticatesSessions::class],
            ScopeSessionToTenant::class,
        );
        $kernel->addToMiddlewarePriorityAfter(ScopeSessionToTenant::class, AccountStatusGate::class);
    }
}
