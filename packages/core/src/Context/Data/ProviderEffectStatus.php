<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ProviderEffectStatus
{
    public function __construct(
        public ProviderEffectRef $effect,
        public AdmissionTicket $ticket,
        public ?ProviderObservation $observation,
        public string $state,
        public string $ownerRef,
        public string $claimToken,
        public string $digest,
        /** The first coordinate covered by the provider reference policy, retained independently of the latest receipt. */
        public ?string $firstProviderRef = null,
    ) {}
}
