<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Internal;

use Closure;
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
        return $this->run($invocation, $effect, null)->result;
    }

    /** @param Closure(Closure():void):OperationResult $effect */
    public function invokeTransactional(OperationInvocation $invocation, Closure $effect, BudgetAttempt $attempt): BudgetInvocationResult
    {
        return $this->run($invocation, $effect, $attempt);
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
            $this->withMetadata(result: $result, reservation: $reservation, estimate: $estimate, actual: $actual),
            $pending === null ? null : new BudgetReceipt($pending->reservationId, $actual->units),
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

    public function replay(OperationInvocation $invocation, OperationResult $result): OperationResult
    {
        $estimate = new EstimatedCost(
            units: 1,
            recipeHash: hash('sha256', 'corex-operation-replay/1'),
        );
        $reservation = $this->reserve(invocation: $invocation, estimate: $estimate);
        $actual = new ActualCost($estimate->units);
        $this->settleWithoutChangingOutcome(reservation: $reservation, actual: $actual);

        return $this->withMetadata(result: $result, reservation: $reservation, estimate: $estimate, actual: $actual);
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

            return $reservationId !== null && $this->budget instanceof DurableOperationBudget
                ? $this->budget->reserveAttempt($scope, $estimate, $reservationId)
                : $this->budget->reserve($scope, $estimate);
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
