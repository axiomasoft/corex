<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Data\DataScope;
use CoreX\Operations\Errors\ApiError;
use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Contracts\WebhookSigner;
use CoreX\Webhooks\Data\WebhookDelivery;
use CoreX\Webhooks\Data\WebhookHttpResult;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Throwable;

/** @internal Performs one network delivery after the durable claim transaction commits. */
final readonly class WebhookDeliveryWorker
{
    public function __construct(
        private SqlWebhookDeliveryStore $store,
        private WebhookSigner $signer,
        private WebhookUrlPolicy $urlPolicy,
        private WebhookHttpTransport $transport,
        private WebhookTopicCatalogue $topics,
        private ClockInterface $clock,
    ) {}

    public function workOnce(DataScope $scope, string $worker): ?WebhookDelivery
    {
        $delivery = $this->store->claim($scope, $worker);

        if ($delivery === null) {
            return null;
        }

        $keyRefs = $this->store->signingKeyRefs($scope, $delivery);

        if ($keyRefs === null) {
            return $this->store->find($scope, $delivery->id) ?? $delivery;
        }

        try {
            $topic = $this->topics->get($delivery->topic, $delivery->topicVersion);

            if ($topic->major !== $delivery->major) {
                throw new ApiError('unknown_message_contract', 422, 'Webhook topic major changed.');
            }
        } catch (ApiError) {
            return $this->finish($scope, $delivery, new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: 'topic_unavailable',
                durationMs: 0,
            ));
        }

        try {
            $signed = $this->signer->sign(
                deliveryId: $delivery->id,
                timestamp: $this->clock->now()->getTimestamp(),
                rawBody: $delivery->body,
                secretRefs: $keyRefs,
            );
        } catch (Throwable) {
            return $this->finish($scope, $delivery, new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: 'signing_unavailable',
                durationMs: 0,
            ));
        }

        try {
            $request = $this->urlPolicy->validate(
                url: $delivery->endpointSnapshot,
                body: $delivery->body,
                signedHeaders: $signed,
            );
        } catch (InvalidArgumentException) {
            return $this->finish($scope, $delivery, new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: 'webhook_url_forbidden',
                durationMs: 0,
            ));
        } catch (RuntimeException) {
            return $this->finish($scope, $delivery, new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: 'dns_unavailable',
                durationMs: 0,
            ));
        }

        try {
            $result = $this->transport->send($request);
        } catch (Throwable) {
            $result = new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: 'network_failure',
                durationMs: 0,
            );
        }

        return $this->finish($scope, $delivery, $result);
    }

    private function finish(
        DataScope $scope,
        WebhookDelivery $delivery,
        WebhookHttpResult $result,
    ): WebhookDelivery {
        $this->store->finish($delivery, $result);

        return $this->store->find($scope, $delivery->id) ?? $delivery;
    }
}
