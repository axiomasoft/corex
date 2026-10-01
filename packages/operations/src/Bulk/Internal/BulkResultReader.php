<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use DateTimeImmutable;

/** @internal Typed bulk result projection. It does not read transfer tables itself. */
final readonly class BulkResultReader
{
    /**
     * @param  callable(DataScope, BulkOperation):bool  $fieldAccess
     * @param  callable(DataScope, BulkOperation):array<string, mixed>  $manifest
     */
    public function __construct(
        private mixed $fieldAccess,
        private mixed $manifest,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>, code: ?string}
     */
    public function read(DataScope $scope, BulkOperation $operation, DateTimeImmutable $now): array
    {
        if ($operation->expiresAt !== null && $operation->expiresAt <= $now) {
            return ['status' => 410, 'body' => ['code' => OperationsErrorCode::BulkResultExpired->value], 'code' => OperationsErrorCode::BulkResultExpired->value];
        }

        if ($operation->status !== BulkOperationStatus::Completed || JsonDocuments::optionalMap($operation->resultRef) === null) {
            return ['status' => 409, 'body' => ['code' => OperationsErrorCode::BulkNotReady->value], 'code' => OperationsErrorCode::BulkNotReady->value];
        }

        if (! ($this->fieldAccess)($scope, $operation)) {
            return ['status' => 404, 'body' => ['code' => OperationsErrorCode::NotFound->value], 'code' => OperationsErrorCode::NotFound->value];
        }

        return [
            'status' => 200,
            'body' => ($this->manifest)($scope, $operation),
            'code' => null,
        ];
    }
}
