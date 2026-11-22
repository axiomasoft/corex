<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use Closure;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface as PsrClock;

/** @internal HTTP projection of the durable bulk contract. Possession of an id is not authority. */
#[Internal]
final readonly class BulkOperations
{
    /** @param null|Closure(DataScope, BulkOperationDefinition): bool $allowed */
    public function __construct(
        private BulkOperationStore $store,
        private BulkResultReader $results,
        private BulkFailureReader $failures,
        private PsrClock $clock,
        private ?Closure $allowed = null,
    ) {}

    public function show(DataScope $scope, string $clientId, string $id): JsonResponse
    {
        $operation = $this->visible(scope: $scope, clientId: $clientId, id: $id);

        if ($operation === null) {
            return $this->problem(status: 404, code: 'not_found');
        }

        $response = response()->json([
            'operation_id' => $operation->definition->id,
            'status' => $operation->status->value,
            'progress' => JsonDocuments::map($operation->progress),
            'has_failures' => $operation->failurePageRef !== null || (JsonDocuments::map($operation->progress)['invalid'] ?? 0) !== 0,
        ]);

        if (! $operation->status->isTerminal()) {
            $response->headers->set('Retry-After', '2');
        }

        return $response;
    }

    public function cancel(DataScope $scope, string $clientId, string $id): JsonResponse
    {
        if ($this->visible(scope: $scope, clientId: $clientId, id: $id) === null) {
            return $this->problem(status: 404, code: 'not_found');
        }

        $operation = $this->store->cancel(scope: $scope, id: $id);
        $status = $operation->status === BulkOperationStatus::Cancelling ? 202 : 200;

        return response()->json([
            'operation_id' => $operation->definition->id,
            'status' => $operation->status->value,
        ], $status);
    }

    public function result(DataScope $scope, string $clientId, string $id): JsonResponse
    {
        $operation = $this->visible(scope: $scope, clientId: $clientId, id: $id);

        if ($operation === null) {
            return $this->problem(status: 404, code: 'not_found');
        }

        $read = $this->results->read(scope: $scope, operation: $operation, now: $this->clock->now());

        return response()->json($read['body'], $read['status'])->header('Cache-Control', 'private, no-store');
    }

    public function failurePage(DataScope $scope, string $clientId, string $id, ?string $afterOrdinal, int $limit): JsonResponse
    {
        $operation = $this->visible(scope: $scope, clientId: $clientId, id: $id);

        if ($operation === null) {
            return $this->problem(status: 404, code: 'not_found');
        }

        if ($operation->expiresAt !== null && $operation->expiresAt <= $this->clock->now()) {
            return $this->problem(status: 410, code: 'operations.bulk_result_expired');
        }

        try {
            $page = $this->failures->read(scope: $scope, operation: $operation, afterOrdinal: $afterOrdinal, limit: $limit);
        } catch (InvalidArgumentException) {
            return $this->problem(status: 422, code: 'limit_exceeded');
        }

        return $page === null
            ? $this->problem(status: 404, code: 'not_found')
            : response()->json($page)->header('Cache-Control', 'private, no-store');
    }

    private function visible(DataScope $scope, string $clientId, string $id): ?BulkOperation
    {
        try {
            $operation = $this->store->find(scope: $scope, id: $id);
        } catch (ScopeContextMismatch|ScopeStale) {
            return null;
        }

        if ($operation === null || ! hash_equals($operation->definition->clientId, $clientId)
            || ($this->allowed !== null && ! ($this->allowed)($scope, $operation->definition))) {
            return null;
        }

        return $operation;
    }

    private function problem(int $status, string $code): JsonResponse
    {
        return response()->json(['code' => $code], $status);
    }
}
