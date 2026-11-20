<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Webhooks\Contracts\WebhookEgressPolicy;
use CoreX\Webhooks\Data\WebhookDestination;

final readonly class FixtureWebhookEgress implements WebhookEgressPolicy
{
    public function __construct(private CurrentWorkScope $current) {}

    public function allows(DataScope $scope, WebhookDestination $destination): bool
    {
        $this->current->assert($scope);

        return $scope->accountId === Scenario::BOXED && $scope->workspaceId === Scenario::W1
            && $destination->host === FixtureWebhookTransport::HOST;
    }
}
