<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Data\Data\DataScope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ProviderEffectLedger
{
    public function reserve(DataScope $scope, ProviderAttempt $attempt): AdmissionTicket;

    public function inspect(ProviderEffectRef $effect): ?ProviderEffectStatus;

    public function observe(AdmissionTicket $ticket, ProviderObservation $observation): void;
}
