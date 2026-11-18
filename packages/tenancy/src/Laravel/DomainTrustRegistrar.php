<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Tenancy\Contracts\DomainChallengeStore;
use CoreX\Tenancy\Contracts\DomainOwnershipProofVerifier;
use CoreX\Tenancy\Contracts\DomainTrustAuthorizer;
use CoreX\Tenancy\Contracts\DomainTrustPolicy;
use CoreX\Tenancy\DomainTrust\Exceptions\DomainTrustDisabled;
use CoreX\Tenancy\DomainTrust\Internal\DomainTrustCoordinator;
use CoreX\Tenancy\DomainTrust\Internal\DomainTrustStateWriter;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Concerns\RegistersNothingOnBoot;
use Packman\Foundation\Contracts\PackageRegistrar;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DomainTrustRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(
            DomainTrustStateWriter::class,
            static fn (Application $app): DomainTrustStateWriter => new DomainTrustStateWriter(
                connections: $app->make(ConnectionResolverInterface::class),
                clock: $app->make(ClockInterface::class),
                events: $app->make(Dispatcher::class),
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
            ),
        );
        $app->scoped(DomainTrustCoordinator::class, static fn (Application $app): DomainTrustCoordinator => self::coordinator($app));
    }

    private static function coordinator(Application $app): DomainTrustCoordinator
    {
        $options = $app->make(TenancyOptions::class);

        if (! $options->domainTrustEnabled) {
            throw new DomainTrustDisabled('Custom-domain verification is disabled.');
        }

        return new DomainTrustCoordinator(
            policy: $app->make(DomainTrustPolicy::class),
            challengeStore: $app->make(DomainChallengeStore::class),
            proofVerifier: $app->make(DomainOwnershipProofVerifier::class),
            authorizer: $app->make(DomainTrustAuthorizer::class),
            stateWriter: $app->make(DomainTrustStateWriter::class),
            clock: $app->make(ClockInterface::class),
            challengeTtlSeconds: $options->domainTrustChallengeTtlSeconds,
        );
    }
}
