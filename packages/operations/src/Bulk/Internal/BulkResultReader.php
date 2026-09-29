<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use CoreX\Data\DataScope;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
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
            return ['status' => 410, 'body' => ['code' => 'bulk_result_expired'], 'code' => 'bulk_result_expired'];
        }

        if ($operation->status !== BulkOperationStatus::Completed || $operation->resultRef === null) {
            return ['status' => 409, 'body' => ['code' => 'bulk_not_ready'], 'code' => 'bulk_not_ready'];
        }

        if (! ($this->fieldAccess)($scope, $operation)) {
            return ['status' => 404, 'body' => ['code' => 'not_found'], 'code' => 'not_found'];
        }

        return [
            'status' => 200,
            'body' => ($this->manifest)($scope, $operation),
            'code' => null,
        ];
    }
}
