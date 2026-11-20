<?php

declare(strict_types=1);

namespace CoreX\Messaging\Contracts;

use CoreX\Messaging\Data\ConsumerId;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** The host's authenticated broker subscription and restricted quarantine journal. */
#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface BrokerDeliverySource
{
    /** Read immutable original bytes for this logical subscriber and deployment. Null means already held. */
    public function read(ConsumerId $consumer, string $restrictedRef): ?string;

    /** Retain the original bytes by reference and refuse automatic replay until an operator releases the hold. */
    public function quarantine(ConsumerId $consumer, string $restrictedRef, string $rawDigest, string $reason): void;
}
