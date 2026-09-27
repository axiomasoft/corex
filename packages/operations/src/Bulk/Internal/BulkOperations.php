<?php

declare(strict_types=1);

namespace CoreX\Api\Internal\Bulk;

use CoreX\Api\Contracts\BulkOperationStore;
use CoreX\Api\Data\BulkOperation;
use CoreX\Api\Data\BulkOperationDefinition;
use CoreX\Api\Enums\BulkOperationStatus;
use CoreX\Exceptions\ScopeContextMismatch;
use CoreX\Support\Data\DataScope;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Psr\Clock\ClockInterface as PsrClock;

/** @internal HTTP projection of the durable bulk contract. Possession of an id is not authority. */
final readonly class BulkOperations
{
    public function __construct(
        private BulkOperationStore $store,
        private TransferBulkExecutor $executor,
        private BulkResultReader $results,
        private BulkFailureReader $failures,
        private PsrClock $clock,
    ) {}

    public function submit(DataScope $scope, string $clientId, ?string $idempotencyKey, BulkOperationDefinition $definition): JsonResponse
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $this->problem(400, 'idempotency_required');
        }

        if ($definition->clientId !== $clientId || $definition->actorId !== $scope->actorId) {
            return $this->problem(404, 'not_found');
        }

        $operation = $this->executor->submit($definition);

        return $this->accepted($operation);
    }

    public function show(DataScope $scope, string $clientId, string $id): JsonResponse
    {
        $operation = $this->visible($scope, $clientId, $id);

        if ($operation === null) {
            return $this->problem(404, 'not_found');
        }

        $response = response()->json([
            'operation_id' => $operation->definition->id,
            'status' => $operation->status->value,
            'progress' => $operation->progress,
            'has_failures' => ($operation->progress['invalid'] ?? 0) !== 0,
        ]);

        if (! $operation->status->isTerminal()) {
            $response->headers->set('Retry-After', '2');
        }

        return $response;
    }

    public function cancel(DataScope $scope, string $clientId, string $id): JsonResponse
    {
        if ($this->visible($scope, $clientId, $id) === null) {
            return $this->problem(404, 'not_found');
        }

        $operation = $this->store->cancel($scope, $id);
        $status = $operation->status === BulkOperationStatus::Cancelling ? 202 : 200;

        return response()->json([
            'operation_id' => $operation->definition->id,
            'status' => $operation->status->value,
        ], $status);
    }

    public function result(DataScope $scope, string $clientId, string $id): JsonResponse
    {
        $operation = $this->visible($scope, $clientId, $id);

        if ($operation === null) {
            return $this->problem(404, 'not_found');
        }

        $read = $this->results->read($scope, $operation, $this->clock->now());

        return response()->json($read['body'], $read['status']);
    }

    public function failurePage(DataScope $scope, string $clientId, string $id, ?string $afterOrdinal, int $limit): JsonResponse
    {
        $operation = $this->visible($scope, $clientId, $id);

        if ($operation === null) {
            return $this->problem(404, 'not_found');
        }

        try {
            $page = $this->failures->read($scope, $operation, $afterOrdinal, $limit);
        } catch (InvalidArgumentException) {
            return $this->problem(422, 'limit_exceeded');
        }

        return response()->json($page);
    }

    private function visible(DataScope $scope, string $clientId, string $id): ?BulkOperation
    {
        try {
            $operation = $this->store->find($scope, $id);
        } catch (ScopeContextMismatch) {
            return null;
        }

        if ($operation === null || ! hash_equals($operation->definition->clientId, $clientId)) {
            return null;
        }

        return $operation;
    }

    private function accepted(BulkOperation $operation): JsonResponse
    {
        $location = '/api/v1/bulk-operations/'.$operation->definition->id;

        return response()->json([
            'operation_id' => $operation->definition->id,
            'status_url' => $location,
            'status' => $operation->status->value,
        ], 202)->header('Location', $location);
    }

    private function problem(int $status, string $code): JsonResponse
    {
        return response()->json(['code' => $code], $status);
    }
}
