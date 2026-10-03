<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Webhooks\Data\WebhookDestination;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WebhookEgressPolicy
{
    /** Called on subscription create/update and before every attempt, including replays. */
    public function allows(DataScope $scope, WebhookDestination $destination): bool;
}
