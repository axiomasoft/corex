<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Internal;

use Closure;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Cost\Contracts\BudgetExecution;
use CoreX\Operations\Cost\Contracts\BudgetReservation;
use CoreX\Operations\Cost\Contracts\OperationBudget;
use CoreX\Operations\Cost\Contracts\OperationCostEstimator;
use CoreX\Operations\Cost\Data\ActualCost;
use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\CostInput;
use CoreX\Operations\Cost\Data\EstimatedCost;
use CoreX\Operations\Cost\Exceptions\BudgetRateLimited;
use CoreX\Operations\Cost\Exceptions\BudgetStoreUnavailable;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Data\OperationResult;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** @internal Admission and settlement inside the shared operation invocation seam. */
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
        $this->clientId = $clientId ?? static fn (OperationInvocation $invocation): ?string => null;

        new BudgetScope(installationId: $installationId, accountId: '__validation__', clientId: null, actorId: null);
    }

    /** @param Closure(Closure():void):OperationResult $effect */
    public function invoke(OperationInvocation $invocation, Closure $effect): OperationResult
    {
        $estimate = $this->estimator->estimate(operation: $invocation->operation, input: ($this->costInput)($invocation));
        $reservation = $this->reserve(invocation: $invocation, estimate: $estimate);
        $actual = new ActualCost($estimate->units);
        $handlerStarted = false;

        try {
            $result = $effect(static function () use (&$handlerStarted): void {
                $handlerStarted = true;
            });
        } catch (Throwable $error) {
            if ($handlerStarted) {
                $this->settleWithoutChangingOutcome(reservation: $reservation, actual: $actual);
            } else {
                $this->rejectWithoutChangingOutcome($reservation);
            }

            throw $error;
        }

        $this->settleWithoutChangingOutcome(reservation: $reservation, actual: $actual);

        return $this->withMetadata(result: $result, reservation: $reservation, estimate: $estimate, actual: $actual);
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

    private function reserve(OperationInvocation $invocation, EstimatedCost $estimate): BudgetReservation
    {
        $tenant = $invocation->context->tenant();

        try {
            return $this->budget->reserve(scope: new BudgetScope(
                installationId: $this->installationId,
                accountId: $tenant->account->id,
                clientId: ($this->clientId)($invocation),
                actorId: $invocation->context->principal()->id(),
            ), cost: $estimate);
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
