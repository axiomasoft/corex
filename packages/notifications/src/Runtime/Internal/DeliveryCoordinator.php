<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Notifications\Delivery\Contracts\DeliveryExecution;
use CoreX\Notifications\Delivery\Contracts\NotificationChannelDriver;
use CoreX\Notifications\Delivery\Contracts\RecipientResolver;
use CoreX\Notifications\Delivery\Data\DeliveryClaim;
use CoreX\Notifications\Delivery\Data\DeliveryInput;
use CoreX\Notifications\Delivery\Data\DeliveryOutcome;
use CoreX\Notifications\Delivery\Enums\DeliveryOutcomeKind;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use Illuminate\Container\Container;
use Packman\Attributes\Internal;
use Throwable;

#[Internal]
final readonly class DeliveryCoordinator implements DeliveryExecution
{
    public function __construct(
        private DeliveryStore $deliveries,
        private ChannelDrivers $drivers,
        private RecipientResolver $recipients,
        private NotificationPolicy $policy,
        private NotificationVisibility $visibility,
        private StoredWorkRunner $work,
        private Container $container,
    ) {}

    public function deliver(string $deliveryId): void
    {
        $claim = $this->deliveries->claim($deliveryId);

        if ($claim === null) {
            return;
        }

        try {
            $this->work->runStored(scope: $claim->scope, proof: $claim->proof, work: static function (TenantContext $tenant) use ($claim): void {
                Container::getInstance()->make(self::class)->send($claim, $tenant);
            });
        } catch (ScopeContextMismatch|ScopeStale $error) {
            $this->deliveries->quarantine($claim);

            throw $error;
        }
    }

    public function reconcile(string $deliveryId): void
    {
        $effect = $this->deliveries->effect($deliveryId);

        if ($effect !== null) {
            $this->container->make(ProviderEffectRecovery::class)->recover($effect);
        }
    }

    private function send(DeliveryClaim $claim, TenantContext $tenant): void
    {
        $context = new NotificationContext(tenant: $tenant, actorKind: $claim->scope->principalKind, actorId: $claim->scope->principalId);
        $rows = $this->deliveries->subjectRows($claim);

        if ($rows === []) {
            $this->deliveries->skip(claim: $claim, reason: 'notification-unavailable');

            return;
        }

        $recipient = null;
        foreach ($rows as $row) {
            $subject = $this->visibility->subject($row);

            if (! $this->visibility->allows(context: $context, subject: $subject) || ! $this->policy->allows(context: $context, action: NotificationAction::Notify, subject: $subject)) {
                $this->deliveries->skip(claim: $claim, reason: 'policy-denied');

                return;
            }
            $recipient = $subject->recipient;
        }

        $driver = $this->drivers->find($claim->channel);

        if ($driver === null || $driver->descriptor()->version !== $claim->driverVersion) {
            $this->deliveries->skip(claim: $claim, reason: 'provider-unconfigured');

            return;
        }
        $target = $this->recipients->resolve(context: $context, recipient: $recipient, channel: $claim->channel);

        if ($target === null || $target->channel !== $claim->channel || $target->reference === ''
            || ($claim->targetReference !== null && $target->reference !== $claim->targetReference)) {
            $this->deliveries->skip(claim: $claim, reason: 'recipient-unavailable');

            return;
        }

        $input = new DeliveryInput(
            deliveryKey: $claim->deliveryKey, attemptEpoch: $claim->epoch, target: $target, message: $claim->message, context: $context,
            messageSha256: $claim->messageHash, templateVersion: $claim->templateVersion, policyVersion: $claim->policyVersion, driverVersion: $claim->driverVersion,
        );

        if ($driver instanceof InAppChannelDriver) {
            $this->deliveries->complete(claim: $claim, outcome: $driver->send($input));

            return;
        }

        $this->sendExternal(claim: $claim, driver: $driver, input: $input);
    }

    private function sendExternal(DeliveryClaim $claim, NotificationChannelDriver $driver, DeliveryInput $input): void
    {
        $descriptor = $driver->descriptor();
        $namespace = 'notification.'.hash('sha256', json_encode([$descriptor->key, $descriptor->version, $descriptor->dedupScope], JSON_THROW_ON_ERROR));

        if (($claim->providerNamespace !== null && $claim->providerNamespace !== $namespace)
            || ! $this->deliveries->freezeTarget(claim: $claim, namespace: $namespace, target: $input->target->reference)) {
            return;
        }

        $effect = DeliveryStore::effectRef(scope: $claim->scope, namespace: $namespace, key: $claim->deliveryKey);
        $ledger = $this->container->make(ProviderEffectLedger::class);
        $admission = $this->container->make(ScopeAdmission::class);
        $prior = $ledger->inspect($effect);

        if ($prior !== null && $prior->state !== 'reconciled'
            && ! $this->container->make(ProviderEffectRecovery::class)->recover($effect)) {
            return;
        }
        $ticket = ReferenceWrites::reserveEffect(scope: $claim->scope, attempt: new ProviderAttempt(
            effect: $effect,
            ownerRef: $claim->id,
            claimToken: $claim->token,
            attempt: $claim->attempts,
            deadline: $claim->deadline,
            digest: DeliveryStore::providerDigest(messageHash: $claim->messageHash, namespace: $namespace, target: $input->target->reference),
        ), producer: 'axiomasoft/corex-notifications', ledger: $ledger);

        if (! $this->deliveries->startSending(claim: $claim, ticketId: $ticket->id)) {
            $ledger->observe($ticket, new ProviderObservation(kind: ProviderObservationKind::Absent, reference: 'owner-send-not-started', observer: DeliveryIntents::Owner));
            $this->container->make(ProviderEffectRecovery::class)->recover($effect);

            return;
        }

        try {
            $outcome = $driver->send($input);
        } catch (Throwable $exception) {
            $outcome = new DeliveryOutcome(kind: DeliveryOutcomeKind::Unknown, providerRef: null, retryAfter: null, reason: 'worker-exception');
            $this->record(claim: $claim, outcome: $outcome, ledger: $ledger, admission: $admission, ticket: $ticket);

            throw $exception;
        }

        $this->record(claim: $claim, outcome: $outcome, ledger: $ledger, admission: $admission, ticket: $ticket);
    }

    private function record(DeliveryClaim $claim, DeliveryOutcome $outcome, ProviderEffectLedger $ledger, ScopeAdmission $admission, AdmissionTicket $ticket): void
    {
        if ($outcome->kind === DeliveryOutcomeKind::Completed && $outcome->providerRef === null) {
            $outcome = new DeliveryOutcome(kind: $outcome->kind, providerRef: $claim->deliveryKey, retryAfter: null, reason: $outcome->reason);
        }

        if ($outcome->kind === DeliveryOutcomeKind::Retryable && ! $outcome->absenceProven) {
            $outcome = new DeliveryOutcome(kind: DeliveryOutcomeKind::Unknown, providerRef: null, retryAfter: null, reason: 'unproven-resend');
        }
        $ledger->observe(ticket: $ticket, observation: self::observation(outcome: $outcome, effectKey: $claim->deliveryKey));

        if ($this->deliveries->complete(claim: $claim, outcome: $outcome, state: 'sending')) {
            $admission->settle(ticket: $ticket, evidence: new AttemptEvidence(
                outcome: $outcome->kind === DeliveryOutcomeKind::Unknown ? 'unknown' : 'reconciled',
                reference: $claim->id,
            ));
        }
    }

    public static function observation(DeliveryOutcome $outcome, string $effectKey): ProviderObservation
    {
        $kind = match ($outcome->kind) {
            DeliveryOutcomeKind::Accepted => ProviderObservationKind::Accepted,
            DeliveryOutcomeKind::Completed => ProviderObservationKind::Confirmed,
            DeliveryOutcomeKind::Retryable => $outcome->absenceProven ? ProviderObservationKind::Absent : ProviderObservationKind::Unknown,
            DeliveryOutcomeKind::Permanent => ProviderObservationKind::Permanent,
            DeliveryOutcomeKind::Unknown => ProviderObservationKind::Unknown,
        };

        return new ProviderObservation(kind: $kind, reference: $outcome->providerRef ?? match ($kind) {
            ProviderObservationKind::Confirmed => $effectKey,
            ProviderObservationKind::Absent => 'retry-after-'.($outcome->retryAfter ?? 60),
            default => $outcome->reason,
        }, observer: DeliveryIntents::Owner);
    }
}
