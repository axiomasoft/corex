<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use CoreX\Data\Data\JsonDocument;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use DateTimeImmutable;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class BulkOperation
{
    public function __construct(
        public BulkOperationDefinition $definition,
        public BulkOperationStatus $status,
        public ?BulkOperationStatus $terminalStatus,
        public int $revision,
        public ?string $leaseOwner,
        public ?DateTimeImmutable $leaseUntil,
        public ?DateTimeImmutable $cancelRequestedAt,
        public ?BulkCheckpoint $checkpoint,
        public JsonDocument $progress,
        public ?JsonDocument $resultRef,
        public ?JsonDocument $error,
        public int $attempt,
        public DateTimeImmutable $nextAttemptAt,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $finishedAt,
        public ?DateTimeImmutable $expiresAt,
        public ?BulkOwnerRef $ownerResultRef = null,
        public ?BulkOwnerRef $failurePageRef = null,
        public int $recoveryAttempts = 0,
    ) {
        if ($revision < 0 || $attempt < 0 || $recoveryAttempts < 0) {
            throw new InvalidArgumentException('Bulk revision and attempt must be non-negative.');
        }

        if ($terminalStatus !== null && ! in_array($terminalStatus, [
            BulkOperationStatus::Cancelled,
            BulkOperationStatus::Completed,
            BulkOperationStatus::Failed,
        ], true)) {
            throw new InvalidArgumentException('Bulk terminal status must preserve a terminal operation outcome.');
        }
    }
}
