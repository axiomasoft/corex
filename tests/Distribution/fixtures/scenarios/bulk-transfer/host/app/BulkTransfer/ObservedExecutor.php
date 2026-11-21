<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use App\Scenario\Scenario;
use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Bulk\Data\BulkContext;
use CoreX\Operations\Bulk\Data\BulkStep;
use CoreX\Operations\Bulk\Data\BulkStepResult;
use CoreX\Operations\Errors\Exceptions\ApiError;

final readonly class ObservedExecutor implements BulkExecutor
{
    public function __construct(private BulkExecutor $owner) {}

    public function step(BulkContext $context, BulkStep $step): BulkStepResult
    {
        $mode = getenv('COREX_BULK_MODE');
        $witness = ['mode' => $mode, 'operation' => $context->operationRef, 'token' => $step->token, 'sequence' => $step->checkpoint?->sequence ?? 0,
            'rowLimit' => $step->rowLimit, 'seconds' => $step->timeLimitSeconds];
        Journal::record('bulk-dispatched', $witness);

        try {
            $result = $this->owner->step($context, $step);
        } catch (ApiError $error) {
            Journal::record('owner-refused', [...$witness, 'code' => $error->errorCode, 'status' => $error->status]);

            throw $error;
        }
        $witness['result'] = $result;
        Journal::record('owner-return', $witness);

        $barrier = match ($mode) {
            'import-owner-ack' => 'import-owner-committed',
            'export-ack' => 'export-published',
            'export-stale' => 'export-stale-ack',
            default => null,
        };

        if ($barrier !== null) {
            if ($mode === 'export-stale') {
                Scenario::barrier($barrier, $witness);
            } else {
                BarrierWitness::beforeKill($barrier, $context->kind, $witness);
            }
        }

        return $result;
    }
}
