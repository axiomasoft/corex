<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\DomainTrust\Data\ChallengeConsumption;
use CoreX\Tenancy\DomainTrust\Data\DomainChallengeBinding;
use CoreX\Tenancy\DomainTrust\Data\DomainChallengeDraft;
use CoreX\Tenancy\DomainTrust\Data\IssuedDomainChallenge;
use CoreX\Tenancy\DomainTrust\Data\ObservedDomainProof;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DomainChallengeStore
{
    public function issue(DomainChallengeDraft $draft): IssuedDomainChallenge;

    public function consumeMatching(
        DomainChallengeBinding $binding,
        ObservedDomainProof $proof,
    ): ChallengeConsumption;
}
