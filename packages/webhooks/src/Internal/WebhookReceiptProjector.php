<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Context\Contracts\ProviderReceiptProjector;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Webhooks\Contracts\WebhookEgressPolicy;
use CoreX\Webhooks\Contracts\WebhookProviderLookup;
use CoreX\Webhooks\Data\WebhookDestination;
use CoreX\Webhooks\Data\WebhookProviderReference;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class WebhookReceiptProjector implements ProviderReceiptProjector
{
    public function __construct(
        private SqlWebhookDeliveryStore $store,
        private Container $container,
        private WebhookEgressPolicy $egress,
    ) {}

    public function owner(): string
    {
        return WebhookWork::Owner;
    }

    public function lookup(ProviderEffectStatus $status): ?ProviderObservation
    {
        $delivery = $this->store->providerDelivery($status);

        if ($delivery === null) {
            return $this->store->claimPrecededSend($status)
                ? new ProviderObservation(kind: ProviderObservationKind::Absent, reference: 'owner-send-not-started', observer: $this->owner())
                : null;
        }

        if (! $this->container->bound(WebhookProviderLookup::class)) {
            return null;
        }

        try {
            $destination = WebhookDestination::fromUrl($delivery->endpointSnapshot);
        } catch (InvalidArgumentException) {
            return null;
        }

        if (! $this->egress->allows(scope: $status->ticket->scope, destination: $destination)) {
            return null;
        }

        return $this->container->make(WebhookProviderLookup::class)->lookup(new WebhookProviderReference(
            scope: $status->ticket->scope, deliveryId: $delivery->id, endpoint: $delivery->endpointSnapshot, effectKey: $delivery->effectKey,
        ));
    }

    public function project(ProviderEffectStatus $status): ?string
    {
        return $this->store->project($status);
    }
}
