<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Webhooks\Contracts\WebhookEgressPolicy;
use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Contracts\WebhookSigner;
use CoreX\Webhooks\Data\WebhookDelivery;
use CoreX\Webhooks\Data\WebhookDestination;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Throwable;

/** @internal Performs one network delivery after the durable claim transaction commits. */
#[Internal]
final readonly class WebhookDeliveryWorker
{
    public function __construct(
        private SqlWebhookDeliveryStore $store,
        private WebhookSigner $signer,
        private WebhookUrlPolicy $urlPolicy,
        private WebhookHttpTransport $transport,
        private WebhookTopicCatalogue $topics,
        private ClockInterface $clock,
        private WebhookEgressPolicy $egress,
        private DataScopeCodec $scopeCodec,
    ) {}

    public function workOnce(DataScope $scope, string $worker): ?WebhookDelivery
    {
        $delivery = $this->store->claim(scope: $scope, worker: $worker);

        if ($delivery === null) {
            return null;
        }

        $keyRefs = $this->store->signingKeyRefs(scope: $scope, delivery: $delivery);

        if ($keyRefs === null) {
            return $this->store->find(scope: $scope, id: $delivery->id) ?? $delivery;
        }

        try {
            $topic = $this->topics->get(name: $delivery->topic, version: $delivery->topicVersion);

            if ($topic->major !== $delivery->major) {
                throw new ApiError(errorCode: OperationsErrorCode::UnknownMessageContract, status: 422, message: 'Webhook topic major changed.');
            }
        } catch (ApiError) {
            return $this->finish(scope: $scope, delivery: $delivery, result: new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: WebhookFailureReason::TopicUnavailable,
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
            return $this->finish(scope: $scope, delivery: $delivery, result: new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: WebhookFailureReason::SigningUnavailable,
                durationMs: 0,
            ));
        }

        try {
            $destination = WebhookDestination::fromUrl($delivery->endpointSnapshot);
        } catch (InvalidArgumentException) {
            return $this->finish(scope: $scope, delivery: $delivery, result: new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: WebhookFailureReason::UrlForbidden,
                durationMs: 0,
            ));
        }

        $deliveryScope = $this->scopeCodec->decode($delivery->scope);

        if (! $this->egress->allows(scope: $deliveryScope, destination: $destination)) {
            return $this->finish(scope: $scope, delivery: $delivery, result: new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: WebhookFailureReason::EgressDenied,
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
            return $this->finish(scope: $scope, delivery: $delivery, result: new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: WebhookFailureReason::UrlForbidden,
                durationMs: 0,
            ));
        } catch (RuntimeException) {
            return $this->finish(scope: $scope, delivery: $delivery, result: new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: WebhookFailureReason::DnsUnavailable,
                durationMs: 0,
            ));
        }

        try {
            $result = $this->transport->send($request);
        } catch (Throwable) {
            $result = new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: WebhookFailureReason::NetworkFailure,
                durationMs: 0,
            );
        }

        return $this->finish(scope: $scope, delivery: $delivery, result: $result);
    }

    private function finish(
        DataScope $scope,
        WebhookDelivery $delivery,
        WebhookHttpResult $result,
    ): WebhookDelivery {
        $this->store->finish(delivery: $delivery, result: $result);

        return $this->store->find(scope: $scope, id: $delivery->id) ?? $delivery;
    }
}
