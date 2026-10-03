<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Laravel\RegistersNothingOnBoot;
use CoreX\Tenancy\Authentication\Internal\Http\Controllers\LogoutController;
use CoreX\Tenancy\Authentication\Internal\Http\Controllers\OidcLoginController;
use CoreX\Tenancy\Authentication\Internal\Oidc\IdTokenVerifier;
use CoreX\Tenancy\Authentication\Internal\Oidc\JwksKeySet;
use CoreX\Tenancy\Authentication\Internal\Oidc\OidcClient;
use CoreX\Tenancy\Authentication\Internal\Oidc\OidcDiscovery;
use CoreX\Tenancy\Identity\Contracts\IdentitySyncer;
use CoreX\Tenancy\Impersonation\Internal\DatabaseImpersonationService;
use CoreX\Tenancy\Laravel\Support\ImpersonationPolicy;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Override;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AuthenticationRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(
            OidcDiscovery::class,
            static fn (Application $app): OidcDiscovery => new OidcDiscovery(
                http: $app->make(HttpFactory::class),
                cache: $app->make(CacheFactory::class),
                issuer: (string) $app->make(TenancyOptions::class)->oidcIssuer,
                cacheStore: $app->make(TenancyOptions::class)->oidcDiscoveryCacheStore,
                cacheTtl: $app->make(TenancyOptions::class)->oidcDiscoveryCacheTtl,
            ),
        );

        $app->singleton(
            JwksKeySet::class,
            static fn (Application $app): JwksKeySet => new JwksKeySet(
                http: $app->make(HttpFactory::class),
                cache: $app->make(CacheFactory::class),
                cacheStore: $app->make(TenancyOptions::class)->oidcJwksCacheStore,
                cacheTtl: $app->make(TenancyOptions::class)->oidcJwksCacheTtl,
                refetchCooldownSeconds: $app->make(TenancyOptions::class)->oidcJwksRefetchCooldownSeconds,
            ),
        );

        $app->singleton(
            IdTokenVerifier::class,
            static fn (Application $app): IdTokenVerifier => new IdTokenVerifier(
                jwks: $app->make(JwksKeySet::class),
                issuer: (string) $app->make(TenancyOptions::class)->oidcIssuer,
                clientId: (string) $app->make(TenancyOptions::class)->oidcClientId,
                allowedAlgs: $app->make(TenancyOptions::class)->oidcAllowedAlgs,
                leewaySeconds: $app->make(TenancyOptions::class)->oidcLeewaySeconds,
            ),
        );

        $app->singleton(
            OidcClient::class,
            static fn (Application $app): OidcClient => new OidcClient(
                http: $app->make(HttpFactory::class),
                discovery: $app->make(OidcDiscovery::class),
                clientId: (string) $app->make(TenancyOptions::class)->oidcClientId,
                clientSecret: $app->make(TenancyOptions::class)->oidcClientSecret?->reveal(),
                redirectUri: (string) $app->make(TenancyOptions::class)->oidcRedirectUri,
            ),
        );

        $app->singleton(
            LogoutController::class,
            static fn (Application $app): LogoutController => new LogoutController(
                auth: $app->make(AuthFactory::class),
                connections: $app->make(ConnectionResolverInterface::class),
                clock: $app->make(ClockInterface::class),
                options: $app->make(TenancyOptions::class),
            ),
        );
        $app->scoped(
            OidcLoginController::class,
            static fn (Application $app): OidcLoginController => new OidcLoginController(
                discovery: $app->make(OidcDiscovery::class),
                client: $app->make(OidcClient::class),
                verifier: $app->make(IdTokenVerifier::class),
                syncer: $app->make(IdentitySyncer::class),
                impersonation: $app->make(ImpersonationService::class),
                impersonationPolicy: $app->make(ImpersonationPolicy::class),
                options: $app->make(TenancyOptions::class),
                auth: $app->make(AuthFactory::class),
                connections: $app->make(ConnectionResolverInterface::class),
                clock: $app->make(ClockInterface::class),
            ),
        );

        // P2.11 (D139/D10) — rebind the boxed NullImpersonationService
        // to the cloud implementation whenever the OIDC lane itself is
        // active; DatabaseImpersonationService::start() is only ever
        // reached when `oidc.impersonation.enabled` ALSO gates it open
        // (OidcLoginController::impersonationClaim()) — binding it here
        // unconditionally within this block just means the container
        // has something to resolve for OidcLoginController's
        // constructor regardless of that inner sub-flag.
        $app->scoped(
            ImpersonationService::class,
            static fn (Application $app): DatabaseImpersonationService => new DatabaseImpersonationService(
                impersonationPolicy: $app->make(ImpersonationPolicy::class),
                options: $app->make(TenancyOptions::class),
            ),
        );
    }
}
