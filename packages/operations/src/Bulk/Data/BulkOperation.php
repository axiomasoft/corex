<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class BulkOperation
{
    /**
     * @param  array<string, mixed>|null  $transferRef
     * @param  array<string, mixed>  $checkpoint
     * @param  array<string, mixed>  $progress
     * @param  array<string, mixed>|null  $resultRef
     * @param  array<string, mixed>|null  $error
     */
    public function __construct(
        public BulkOperationDefinition $definition,
        public ?array $transferRef,
        public BulkOperationStatus $status,
        public ?BulkOperationStatus $terminalStatus,
        public int $revision,
        public ?string $leaseOwner,
        public ?DateTimeImmutable $leaseUntil,
        public ?DateTimeImmutable $cancelRequestedAt,
        public array $checkpoint,
        public array $progress,
        public ?array $resultRef,
        public ?array $error,
        public int $attempt,
        public DateTimeImmutable $nextAttemptAt,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $finishedAt,
        public ?DateTimeImmutable $expiresAt,
    ) {
        if ($revision < 0 || $attempt < 0) {
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
