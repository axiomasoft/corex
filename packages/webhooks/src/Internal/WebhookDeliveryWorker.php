<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use Closure;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Webhooks\Contracts\WebhookEgressPolicy;
use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Contracts\WebhookSigner;
use CoreX\Webhooks\Data\WebhookDelivery;
use CoreX\Webhooks\Data\WebhookDestination;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use Illuminate\Container\Container;
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
        /** @var ?Closure(): self */
        private ?Closure $resolveWorker = null,
    ) {}

    public function workOnce(DataScope $scope, string $worker): ?WebhookDelivery
    {
        $delivery = $this->store->claim(scope: $scope, worker: $worker);

        if ($delivery === null) {
            return null;
        }

        try {
            $original = $this->scopeCodec->decode($delivery->scope);

            if (WebhookDeliveryScope::logical($scope) !== WebhookDeliveryScope::logical($original)
                || WebhookDeliveryScope::fence($scope) !== WebhookDeliveryScope::fence($original)) {
                throw new ScopeContextMismatch('Stored webhook fence differs from the claimed delivery lane.');
            }

            Container::getInstance()->make(StoredWorkRunner::class)->runStored(
                $original,
                WebhookWork::proof($original, $delivery),
                function (TenantContext $tenant) use ($original, $delivery): void {
                    $worker = $this->resolveWorker === null ? $this : ($this->resolveWorker)();
                    $worker->send($original, $delivery);
                },
            );
        } catch (InvalidArgumentException|ScopeContextMismatch) {
            $this->store->quarantine($scope, $delivery);
        }

        return $this->store->find(scope: $scope, id: $delivery->id) ?? $delivery;
    }

    public function reconcile(DataScope $scope, string $deliveryId): bool
    {
        $delivery = $this->store->find($scope, $deliveryId);

        return $delivery !== null && Container::getInstance()->make(ProviderEffectRecovery::class)->recover(WebhookWork::effect($scope, $delivery));
    }

    private function send(DataScope $scope, WebhookDelivery $delivery): ?WebhookDelivery
    {
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
            $destination = WebhookDestination::fromUrl($delivery->endpointSnapshot);
        } catch (InvalidArgumentException) {
            return $this->finish(scope: $scope, delivery: $delivery, result: new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: WebhookFailureReason::UrlForbidden,
                durationMs: 0,
            ));
        }

        if (! $this->egress->allows(scope: $scope, destination: $destination)) {
            return $this->finish(scope: $scope, delivery: $delivery, result: new WebhookHttpResult(
                status: null,
                retryAfter: null,
                errorCode: WebhookFailureReason::EgressDenied,
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
            $request = $this->urlPolicy->validate(
                url: $delivery->endpointSnapshot,
                body: $delivery->body,
                signedHeaders: $signed,
                effectKey: $delivery->effectKey,
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

        if ($delivery->claimToken === null || $delivery->leaseUntil === null) {
            return null;
        }

        $container = Container::getInstance();
        $ledger = $container->make(ProviderEffectLedger::class);
        $admission = $container->make(ScopeAdmission::class);
        $effect = WebhookWork::effect($scope, $delivery);
        $prior = $ledger->inspect($effect);

        if ($prior !== null && $prior->state !== 'reconciled'
            && ! $container->make(ProviderEffectRecovery::class)->recover($effect)) {
            return null;
        }
        $ticket = ReferenceWrites::reserveEffect($scope, new ProviderAttempt(
            $effect, $delivery->id, $delivery->claimToken, $delivery->attempt,
            $delivery->leaseUntil, WebhookWork::deliveryDigest($delivery),
        ), 'axiomasoft/corex-webhooks', $ledger);

        if (! $this->store->startSending($scope, $delivery, $ticket->id)) {
            $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Absent, 'owner-send-not-started', WebhookWork::Owner));
            $container->make(ProviderEffectRecovery::class)->recover($effect);

            return null;
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

        $kind = $this->store->observation($result);
        $ledger->observe($ticket, new ProviderObservation($kind, $result->providerRef ?? match ($kind) {
            ProviderObservationKind::Confirmed => $delivery->effectKey,
            default => $result->errorCode->value ?? 'http-'.($result->status ?? 0),
        }, WebhookWork::Owner));

        if ($this->store->finish($scope, $delivery, $result, sent: true)) {
            $admission->settle($ticket, new AttemptEvidence($kind === ProviderObservationKind::Unknown ? 'unknown' : 'reconciled', $delivery->id));
        }

        return null;
    }

    private function finish(
        DataScope $scope,
        WebhookDelivery $delivery,
        WebhookHttpResult $result,
    ): WebhookDelivery {
        $this->store->finish(scope: $scope, delivery: $delivery, result: $result);

        return $this->store->find(scope: $scope, id: $delivery->id) ?? $delivery;
    }
}
