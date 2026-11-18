<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Webhooks\Data\WebhookDestination;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WebhookEgressPolicy
{
    /** Called on subscription create/update and before every attempt, including replays. */
    public function allows(DataScope $scope, WebhookDestination $destination): bool;
}
