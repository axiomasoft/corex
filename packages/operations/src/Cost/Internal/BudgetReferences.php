<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Internal;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Operations\Bulk\Laravel\BulkReferences;
use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\EstimatedCost;
use CoreX\Operations\Cost\Exceptions\BudgetStoreUnavailable;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class BudgetReferences
{
    private const string OWNER = 'axiomasoft/corex-operations';

    public function __construct(
        private DataScopeFactory $scopes,
        private ReferenceAdmission $admission,
        private ProviderEffectLedger $effects,
        private ScopeAdmission $tickets,
        private ClockInterface $clock,
    ) {}

    public function admit(OperationInvocation $invocation, SqlOperationBudget $budget, BudgetScope $budgetScope, EstimatedCost $cost, string $id): AdmissionTicket
    {
        $scope = $this->scopes->forCurrentPrincipal($invocation->context->tenant());
        $effect = new ProviderEffectRef(accountId: $scope->accountId, accountIncarnation: $scope->accountIncarnation, workspaceId: $scope->workspaceId, owner: self::OWNER, providerNamespace: 'budget-reservation', effectKey: $id);
        $digest = hash('sha256', json_encode([$id, $budgetScope, $cost], JSON_THROW_ON_ERROR));
        $previous = $this->effects->inspect($effect);

        if ($previous !== null) {
            if (get_object_vars($previous->ticket->scope) !== get_object_vars($scope) || ! hash_equals($previous->digest, $digest)
                || in_array($previous->state, ['conflicting', 'isolated'], true)) {
                throw new BudgetStoreUnavailable;
            }

            if ($budget->provesHold(scope: $budgetScope, cost: $cost, id: $id)) {
                return $previous->ticket;
            }

            if ($previous->observation?->kind === ProviderObservationKind::Confirmed) {
                throw new BudgetStoreUnavailable;
            }
            $this->record(ticket: $previous->ticket, kind: ProviderObservationKind::Absent, id: $id);
        }

        $targets = [new ModuleRef($invocation->operation->module)];

        if ($invocation->bulk !== null) {
            array_push($targets, ...BulkReferences::targets($invocation->bulk));
        }

        $attempt = ($previous?->ticket->key->attempt ?? 0) + 1;

        return $this->admission->reserve($scope, new ProviderAttempt(
            effect: $effect, ownerRef: $id, claimToken: $id.':'.$attempt, attempt: $attempt, deadline: $this->clock->now()->modify('+30 seconds'), digest: $digest,
        ), new ModuleRef(self::OWNER), ...$targets);
    }

    public function record(AdmissionTicket $ticket, ProviderObservationKind $kind, string $id): void
    {
        $this->effects->observe(ticket: $ticket, observation: new ProviderObservation(kind: $kind, reference: $id, observer: self::OWNER));
        $this->tickets->settle(ticket: $ticket, evidence: new AttemptEvidence(outcome: 'reconciled', reference: $id));
    }
}
