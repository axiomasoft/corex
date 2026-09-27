<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\WebhookDelivery;
use CoreX\Support\Data\DataScope;

interface WebhookDeliveryStore
{
    public function find(DataScope $scope, string $id): ?WebhookDelivery;

    /** @return list<WebhookDelivery> */
    public function forEvent(DataScope $scope, string $eventId): array;
}
