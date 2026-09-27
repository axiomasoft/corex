<?php

declare(strict_types=1);

namespace CoreX\Api\Internal\Cost;

use Closure;
use CoreX\Api\ApiError;
use CoreX\Api\Contracts\BudgetReservation;
use CoreX\Api\Contracts\OperationBudget;
use CoreX\Api\Contracts\OperationCostEstimator;
use CoreX\Api\Data\ActualCost;
use CoreX\Api\Data\BudgetScope;
use CoreX\Api\Data\CostInput;
use CoreX\Api\Data\EstimatedCost;
use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Data\OperationResult;
use CoreX\Api\Enums\OperationMode;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/** @internal Admission and settlement inside the shared operation invocation seam. */
final readonly class BudgetAdmission
{
    /** @var Closure(OperationInvocation): CostInput */
    private Closure $costInput;

    /** @var Closure(OperationInvocation): ?string */
    private Closure $clientId;

    /**
     * @param  null|Closure(OperationInvocation): CostInput  $costInput
     * @param  null|Closure(OperationInvocation): ?string  $clientId
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

        new BudgetScope($installationId, '__validation__', null, null);
    }

    /** @param Closure(Closure(): void): OperationResult $effect */
    public function invoke(OperationInvocation $invocation, Closure $effect): OperationResult
    {
        $estimate = $this->estimator->estimate($invocation->operation, ($this->costInput)($invocation));
        $reservation = $this->reserve($invocation, $estimate);
        $actual = new ActualCost($estimate->units);
        $handlerStarted = false;

        try {
            $result = $effect(static function () use (&$handlerStarted): void {
                $handlerStarted = true;
            });
        } catch (Throwable $error) {
            if ($handlerStarted) {
                $this->settleWithoutChangingOutcome($reservation, $actual);
            } else {
                $this->rejectWithoutChangingOutcome($reservation);
            }

            throw $error;
        }

        $this->settleWithoutChangingOutcome($reservation, $actual);

        return $this->withMetadata($result, $reservation, $estimate, $actual);
    }

    public function replay(OperationInvocation $invocation, OperationResult $result): OperationResult
    {
        $estimate = new EstimatedCost(
            units: 1,
            recipeHash: hash('sha256', 'corex-operation-replay/1'),
        );
        $reservation = $this->reserve($invocation, $estimate);
        $actual = new ActualCost($estimate->units);
        $this->settleWithoutChangingOutcome($reservation, $actual);

        return $this->withMetadata($result, $reservation, $estimate, $actual);
    }

    private function withMetadata(
        OperationResult $result,
        BudgetReservation $reservation,
        EstimatedCost $estimate,
        ActualCost $actual,
    ): OperationResult {
        return new OperationResult(
            data: $result->data,
            replayed: $result->replayed,
            metadata: [...$result->metadata, 'cost' => $this->metadata($reservation, $estimate, $actual)],
        );
    }

    private function reserve(OperationInvocation $invocation, EstimatedCost $estimate): BudgetReservation
    {
        $tenant = $invocation->context->tenant();

        try {
            return $this->budget->reserve(new BudgetScope(
                installationId: $this->installationId,
                accountId: $tenant->account->id,
                clientId: ($this->clientId)($invocation),
                actorId: $invocation->context->principal()->id(),
            ), $estimate);
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

/** @internal Keeps budget storage failure distinct from an enforced 429. */
final class BudgetStoreUnavailable extends RuntimeException {}

/** @internal Identifies a reserve denial without conflating handler-originated 429s. */
final class BudgetRateLimited extends TooManyRequestsHttpException {}
