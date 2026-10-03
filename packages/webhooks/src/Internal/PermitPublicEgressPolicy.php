<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Data\Data\DataScope;
use CoreX\Webhooks\Contracts\WebhookEgressPolicy;
use CoreX\Webhooks\Data\WebhookDestination;
use Override;

/** @internal Host restrictions are opt-in; the separate URL policy always enforces public IPs. */
final readonly class PermitPublicEgressPolicy implements WebhookEgressPolicy
{
    #[Override]
    public function allows(DataScope $scope, WebhookDestination $destination): bool
    {
        return true;
    }
}
