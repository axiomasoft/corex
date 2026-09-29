<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\RegistersNothingOnBoot;
use CoreX\Tenancy\Contracts\DomainChallengeStore;
use CoreX\Tenancy\Contracts\DomainOwnershipProofVerifier;
use CoreX\Tenancy\Contracts\DomainTrustAuthorizer;
use CoreX\Tenancy\Contracts\DomainTrustPolicy;
use CoreX\Tenancy\DomainTrust\DomainTrustCoordinator;
use CoreX\Tenancy\DomainTrust\DomainTrustDisabled;
use CoreX\Tenancy\DomainTrust\DomainTrustStateWriter;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** @internal Package composition type. */
final class DomainTrustRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(
            DomainTrustStateWriter::class,
            static fn (Application $app): DomainTrustStateWriter => new DomainTrustStateWriter(
                centralConnection: $app->make(TenancyOptions::class)->centralConnection,
            ),
        );
        $app->scoped(
            DomainTrustCoordinator::class,
            static function (Application $app): DomainTrustCoordinator {
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
                    challengeTtlSeconds: $options->domainTrustChallengeTtlSeconds,
                );
            },
        );
    }
}
