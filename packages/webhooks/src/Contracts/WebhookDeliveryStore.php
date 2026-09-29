<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Data\DataScope;
use CoreX\Webhooks\Data\WebhookDelivery;

interface WebhookDeliveryStore
{
    public function find(DataScope $scope, string $id): ?WebhookDelivery;

    /** @return list<WebhookDelivery> */
    public function forEvent(DataScope $scope, string $eventId): array;
}
