<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Webhooks\Data\WebhookSubscription;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WebhookSubscriptionStore
{
    public function create(
        DataScope $scope,
        WebhookSubscription $subscription,
        bool $sensitiveAbility,
        bool $policyAllows,
    ): WebhookSubscription;

    /**
     * @param  list<string>  $keyRefs
     */
    public function update(
        DataScope $scope,
        string $id,
        int $expectedRevision,
        string $endpointUrl,
        array $keyRefs,
        bool $sensitiveAbility,
        bool $policyAllows,
    ): WebhookSubscription;

    public function find(DataScope $scope, string $id): ?WebhookSubscription;

    public function disable(DataScope $scope, string $id, int $expectedRevision): WebhookSubscription;
}
