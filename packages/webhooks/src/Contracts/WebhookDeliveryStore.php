<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Webhooks\Data\WebhookDelivery;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WebhookDeliveryStore
{
    public function find(DataScope $scope, string $id): ?WebhookDelivery;

    /** @return list<WebhookDelivery> */
    public function forEvent(DataScope $scope, string $eventId): array;
}
