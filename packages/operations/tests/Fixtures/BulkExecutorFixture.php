<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Fixtures;

use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Bulk\Contracts\BulkOwner;
use CoreX\Operations\Bulk\Data\BulkCheckpoint;
use CoreX\Operations\Bulk\Data\BulkContext;
use CoreX\Operations\Bulk\Data\BulkFailurePage;
use CoreX\Operations\Bulk\Data\BulkOwnerRef;
use CoreX\Operations\Bulk\Data\BulkStep;
use CoreX\Operations\Bulk\Data\BulkStepResult;
use CoreX\Operations\Bulk\Enums\BulkStepStatus;
use RuntimeException;

final class BulkExecutorFixture implements BulkExecutor, BulkOwner
{
    /** @var list<BulkStep> */
    public array $attempts = [];

    /** @var array<string, BulkStepResult> */
    private array $receipts = [];

    public bool $loseReply = false;

    public bool $allowRead = true;

    public int $resultReads = 0;

    public int $failureReads = 0;

    public int $effects = 0;

    public function admit(BulkContext $context): void {}

    public function step(BulkContext $context, BulkStep $step): BulkStepResult
    {
        $this->attempts[] = $step;

        if (isset($this->receipts[$step->token])) {
            return $this->receipts[$step->token];
        }

        if ($step->cancelRequested) {
            return new BulkStepResult(BulkStepStatus::Cancelled, $step->checkpoint, []);
        }

        $sequence = ($step->checkpoint?->sequence ?? 0) + 1;
        $this->effects++;
        $result = $this->receipts[$step->token] = new BulkStepResult(
            $sequence === 2 ? BulkStepStatus::Completed : BulkStepStatus::Progress,
            new BulkCheckpoint($sequence, JsonDocuments::capture(['owner_row' => $sequence])),
            ['applied' => $sequence],
            resultRef: $sequence === 2 ? new BulkOwnerRef('fixture/owner', 'result', $context->operationRef) : null,
        );

        if ($this->loseReply) {
            $this->loseReply = false;

            throw new RuntimeException('owner commit with lost acknowledgment');
        }

        return $result;
    }

    public function result(BulkContext $context, BulkOwnerRef $ref): ?JsonDocument
    {
        if (! $this->allowRead || $ref->owner !== 'fixture/owner') {
            return null;
        }
        $this->resultReads++;

        return JsonDocuments::capture(['media_type' => 'application/json', 'rows' => 1]);
    }

    public function failures(BulkContext $context, BulkOwnerRef $ref, ?string $after, int $limit): ?BulkFailurePage
    {
        if (! $this->allowRead) {
            return null;
        }
        $this->failureReads++;

        return new BulkFailurePage([['ordinal' => '11', 'code' => 'invalid_serial', 'path' => '/serial']], null);
    }
}
