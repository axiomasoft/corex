<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Data;

use CoreX\Data\Data\DataScope;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class WebhookProviderReference
{
    public function __construct(
        public DataScope $scope,
        public string $deliveryId,
        public string $endpoint,
        public string $effectKey,
    ) {}
}
