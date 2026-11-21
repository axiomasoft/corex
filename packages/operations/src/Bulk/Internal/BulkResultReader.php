<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Contracts\BulkOwner;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use DateTimeImmutable;
use Packman\Attributes\Internal;

#[Internal]
final readonly class BulkResultReader
{
    /** @param Closure(): ?BulkOwner $owner */
    public function __construct(private Closure $owner) {}

    /** @return array{status: int, body: array<array-key, mixed>} */
    public function read(DataScope $scope, BulkOperation $operation, DateTimeImmutable $now): array
    {
        if ($operation->expiresAt !== null && $operation->expiresAt <= $now) {
            return ['status' => 410, 'body' => ['code' => OperationsErrorCode::BulkResultExpired->value]];
        }

        if ($operation->status !== BulkOperationStatus::Completed || $operation->ownerResultRef === null) {
            return ['status' => 409, 'body' => ['code' => OperationsErrorCode::BulkNotReady->value]];
        }

        $document = ($this->owner)()?->result($operation->definition->context($scope), $operation->ownerResultRef);

        return $document === null
            ? ['status' => 404, 'body' => ['code' => OperationsErrorCode::NotFound->value]]
            : ['status' => 200, 'body' => JsonDocuments::map($document)];
    }
}
