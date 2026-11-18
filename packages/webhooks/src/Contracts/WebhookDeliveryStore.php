<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Webhooks\Data\WebhookDelivery;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WebhookDeliveryStore
{
    public function find(DataScope $scope, string $id): ?WebhookDelivery;

    /** @return list<WebhookDelivery> */
    public function forEvent(DataScope $scope, string $eventId): array;
}
