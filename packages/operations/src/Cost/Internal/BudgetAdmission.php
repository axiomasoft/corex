<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Internal;

use Closure;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Cost\Contracts\BudgetExecution;
use CoreX\Operations\Cost\Contracts\BudgetReservation;
use CoreX\Operations\Cost\Contracts\DurableOperationBudget;
use CoreX\Operations\Cost\Contracts\OperationBudget;
use CoreX\Operations\Cost\Contracts\OperationCostEstimator;
use CoreX\Operations\Cost\Data\ActualCost;
use CoreX\Operations\Cost\Data\BudgetAttempt;
use CoreX\Operations\Cost\Data\BudgetInvocationResult;
use CoreX\Operations\Cost\Data\BudgetReceipt;
use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\CostInput;
use CoreX\Operations\Cost\Data\EstimatedCost;
use CoreX\Operations\Cost\Exceptions\BudgetRateLimited;
use CoreX\Operations\Cost\Exceptions\BudgetStoreUnavailable;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Data\OperationResult;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Packman\Attributes\Internal;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** @internal Admission and settlement inside the shared operation invocation seam. */
#[Internal]
final readonly class BudgetAdmission implements BudgetExecution
{
    /** @var Closure(OperationInvocation):CostInput */
    private Closure $costInput;

    /** @var Closure(OperationInvocation):?string */
    private Closure $clientId;

    /**
     * @param  null|Closure(OperationInvocation):CostInput  $costInput
     * @param  null|Closure(OperationInvocation):?string  $clientId
     */
    public function __construct(
        private OperationBudget $budget,
        private OperationCostEstimator $estimator,
        private string $installationId,
        private ExceptionHandler $exceptions,
        ?Closure $costInput = null,
        ?Closure $clientId = null,
        private ?BudgetReferences $references = null,
    ) {
        $this->costInput = $costInput ?? static fn (OperationInvocation $invocation): CostInput => new CostInput(
            pageRows: 0,
            selectedFields: count($invocation->operation->readFields ?? []),
            relationNodes: 0,
            attributeFilters: 0,
            highlight: false,
            exportRows: 0,
            sideEffects: in_array($invocation->operation->mode, [OperationMode::Command, OperationMode::BulkCommand], true) ? 1 : 0,
        );
        $this->clientId = $clientId ?? static fn (OperationInvocation $invocation): ?string => $invocation->clientId;

        new BudgetScope(installationId: $installationId, accountId: '__validation__', clientId: null, actorId: null);
    }

    /** @param Closure(Closure():void):OperationResult $effect */
    public function invoke(OperationInvocation $invocation, Closure $effect): OperationResult
    {
        return $this->run(invocation: $invocation, effect: $effect, attempt: null)->result;
    }

    /** @param Closure(Closure():void):OperationResult $effect */
    public function invokeTransactional(OperationInvocation $invocation, Closure $effect, BudgetAttempt $attempt): BudgetInvocationResult
    {
        return $this->run(invocation: $invocation, effect: $effect, attempt: $attempt);
    }

    /** @param Closure(Closure():void):OperationResult $effect */
    private function run(OperationInvocation $invocation, Closure $effect, ?BudgetAttempt $attempt): BudgetInvocationResult
    {
        $estimate = $this->estimator->estimate(operation: $invocation->operation, input: ($this->costInput)($invocation));
        $pending = $this->budget instanceof DurableOperationBudget ? $attempt : null;
        $reservation = $this->reserve(invocation: $invocation, estimate: $estimate, reservationId: $pending?->reservationId);
        $actual = new ActualCost($estimate->units);
        $handlerStarted = false;

        try {
            $result = $effect(static function () use (&$handlerStarted): void {
                $handlerStarted = true;
            });
        } catch (Throwable $error) {
            if (! $handlerStarted) {
                $this->rejectWithoutChangingOutcome($reservation);
            } elseif ($pending === null) {
                $this->settleWithoutChangingOutcome(reservation: $reservation, actual: $actual);
            }

            throw $error;
        }

        if ($pending === null) {
            $this->settleWithoutChangingOutcome(reservation: $reservation, actual: $actual);
        }

        return new BudgetInvocationResult(
            result: $this->withMetadata(result: $result, reservation: $reservation, estimate: $estimate, actual: $actual),
            receipt: $pending === null ? null : new BudgetReceipt(reservationId: $pending->reservationId, actualUnits: $actual->units),
        );
    }

    public function recover(BudgetReceipt $receipt): void
    {
        if (! $this->budget instanceof DurableOperationBudget) {
            return;
        }

        try {
            $this->budget->recover($receipt);
        } catch (Throwable $error) {
            $this->exceptions->report($error);
        }
    }

    private function withMetadata(
        OperationResult $result,
        BudgetReservation $reservation,
        EstimatedCost $estimate,
        ActualCost $actual,
    ): OperationResult {
        return new OperationResult(
            data: JsonDocuments::capture(JsonDocuments::array($result->data)),
            replayed: $result->replayed,
            metadata: JsonDocuments::capture([...JsonDocuments::map($result->metadata), 'cost' => $this->metadata(reservation: $reservation, estimate: $estimate, actual: $actual)]),
            accepted: $result->accepted,
            privateWitness: $result->privateWitness,
        );
    }

    private function reserve(OperationInvocation $invocation, EstimatedCost $estimate, ?string $reservationId = null): BudgetReservation
    {
        $tenant = $invocation->context->tenant();

        try {
            $scope = new BudgetScope(
                installationId: $this->installationId,
                accountId: $tenant->account->id,
                clientId: ($this->clientId)($invocation),
                actorId: $invocation->context->principal()->principalId(),
            );

            if ($this->references !== null && $this->budget instanceof SqlOperationBudget) {
                return $this->reserveAdmitted(invocation: $invocation, scope: $scope, estimate: $estimate, id: $reservationId ?? bin2hex(random_bytes(32)), budget: $this->budget);
            }

            return $reservationId !== null && $this->budget instanceof DurableOperationBudget
                ? $this->budget->reserveAttempt($scope, $estimate, $reservationId)
                : $this->budget->reserve(scope: $scope, cost: $estimate);
        } catch (HttpExceptionInterface $error) {
            if ($error->getStatusCode() === 429) {
                $retryAfter = $error->getHeaders()['Retry-After'] ?? null;

                throw new BudgetRateLimited(
                    is_int($retryAfter) || is_string($retryAfter) ? $retryAfter : null,
                    previous: $error,
                );
            }

            throw $error;
        } catch (ApiError $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new BudgetStoreUnavailable(previous: $error);
        }
    }

    private function reserveAdmitted(OperationInvocation $invocation, BudgetScope $scope, EstimatedCost $estimate, string $id, SqlOperationBudget $budget): BudgetReservation
    {
        $references = $this->references ?? throw new BudgetStoreUnavailable;
        $ticket = $references->admit($invocation, $budget, $scope, $estimate, $id);

        try {
            $hold = $budget->reserveAttempt(scope: $scope, cost: $estimate, reservationId: $id);
        } catch (HttpExceptionInterface $refused) {
            if ($refused->getStatusCode() === 429) {
                $references->record($ticket, ProviderObservationKind::Absent, $id);
            }

            throw $refused;
        }

        $references->record($ticket, ProviderObservationKind::Confirmed, $id);

        return $hold;
    }

    private function settleWithoutChangingOutcome(BudgetReservation $reservation, ActualCost $actual): void
    {
        try {
            $reservation->settle($actual);
        } catch (Throwable) {
            // A settlement observation failure cannot rewrite the business outcome.
            // The held reservation expires without a refund, preserving the estimate.
        }
    }

    private function rejectWithoutChangingOutcome(BudgetReservation $reservation): void
    {
        try {
            $reservation->reject();
        } catch (Throwable) {
            // Failure to record a safe pre-handler refund cannot authorize the handler.
        }
    }

    /** @return array<string, mixed> */
    private function metadata(
        BudgetReservation $reservation,
        EstimatedCost $estimate,
        ActualCost $actual,
    ): array {
        $metadata = [
            'requested' => $estimate->units,
            'actual' => $actual->units,
            'mode' => 'enforce',
        ];

        if ($reservation instanceof SqlBudgetReservation) {
            $status = $reservation->throttleStatus;
            $metadata['throttle'] = [
                'capacity' => $status->capacity,
                'available' => $status->available,
                'restore_rate' => $status->restoreRate,
                'observed_at' => $status->observedAt->format(DATE_ATOM),
            ];
        }

        return $metadata;
    }
}
