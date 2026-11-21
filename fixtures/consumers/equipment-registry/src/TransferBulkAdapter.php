<?php

declare(strict_types=1);

namespace Equipment\Registry;

use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\CancellationSignal;
use CoreX\ImportExport\Contracts\ExportPublications;
use CoreX\ImportExport\Contracts\ImportExportFactory;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\StepToken;
use CoreX\ImportExport\Data\TransferLimits;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Exceptions\ImportUnavailable;
use CoreX\ImportExport\Exceptions\SessionUnavailable;
use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Bulk\Contracts\BulkOwner;
use CoreX\Operations\Bulk\Data\BulkCheckpoint;
use CoreX\Operations\Bulk\Data\BulkContext;
use CoreX\Operations\Bulk\Data\BulkFailurePage;
use CoreX\Operations\Bulk\Data\BulkOwnerRef;
use CoreX\Operations\Bulk\Data\BulkStep;
use CoreX\Operations\Bulk\Data\BulkStepResult;
use CoreX\Operations\Bulk\Enums\BulkStepStatus;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use Ramsey\Uuid\Uuid;
use UnexpectedValueException;

final readonly class TransferBulkAdapter implements BulkExecutor, BulkOwner
{
    private const string OWNER = 'axiomasoft/corex-import-export';

    public function __construct(
        private ImportExportFactory $sessions,
        private TransferResultReader $results,
        private ExportPublications $exports,
        private TransferBulkInputs $inputs,
    ) {}

    public function admit(BulkContext $context): void
    {
        $fingerprint = match ($context->kind) {
            'import' => $this->inputs->import($context)['fingerprint'],
            'export' => $this->inputs->export($context)['fingerprint'],
            default => throw new ApiError(OperationsErrorCode::CapabilityUnavailable, 503, 'Bulk capability is unavailable.'),
        };
        $this->assertFingerprint($context, $fingerprint);
    }

    public function step(BulkContext $context, BulkStep $step): BulkStepResult
    {
        return match ($context->kind) {
            'import' => $this->import($context, $step),
            'export' => $this->export($context, $step),
            default => new BulkStepResult(BulkStepStatus::Failed, null, [], errorCode: 'capability_unavailable'),
        };
    }

    public function result(BulkContext $context, BulkOwnerRef $ref): ?JsonDocument
    {
        $kind = match ($context->kind) {
            'import' => 'import-result',
            'export' => 'export-result',
            default => '',
        };

        if (! $this->matches($context, $ref, $kind)) {
            return null;
        }

        try {
            return JsonDocuments::capture($this->results->result($context->scope, new TransferRef($ref->id)));
        } catch (ImportUnavailable|ExportUnavailable|SessionUnavailable) {
            return null;
        }
    }

    public function failures(BulkContext $context, BulkOwnerRef $ref, ?string $after, int $limit): ?BulkFailurePage
    {
        if ($context->kind !== 'import' || ! $this->matches($context, $ref, 'import-failures')) {
            return null;
        }

        try {
            $page = $this->results->failures($context->scope, new TransferRef($ref->id), $after, $limit);

            return new BulkFailurePage($page['records'], $page['next']);
        } catch (ImportUnavailable|SessionUnavailable) {
            return null;
        }
    }

    private function import(BulkContext $context, BulkStep $step): BulkStepResult
    {
        $input = $this->inputs->import($context);
        $this->assertFingerprint($context, $input['fingerprint']);
        $ref = $this->transfer($context);
        $session = $this->sessions->openImport(
            $context->scope, $ref, $input['source'], $input['mapper'], $input['sink'], $input['mapping'], $input['conflicts'],
            dryRun: $input['dryRun'], cancellation: $this->cancellation($step),
            limits: new TransferLimits(batchRows: $step->rowLimit, maxRuntimeSeconds: $step->timeLimitSeconds),
        );
        $token = $session->token;
        $sequence = '0';
        $epoch = $token->epoch;

        if ($step->checkpoint !== null) {
            $state = JsonDocuments::map($step->checkpoint->ownerState);

            if (($state['transfer'] ?? null) !== $ref->id || ! is_string($state['sequence'] ?? null) || ! is_string($state['epoch'] ?? null)) {
                throw new UnexpectedValueException('Bulk checkpoint does not address the equipment transfer.');
            }

            $sequence = $state['sequence'];
            $epoch = $state['epoch'];
        }

        $receipt = $session->runner->step($context->scope, $session->transfer, new StepToken(
            $sequence, $epoch, $token->sourceHash, $token->mappingHash, $token->schemaHash, $token->restoreEpoch, $step->token,
        ));
        $status = match ($receipt->state) {
            'completed' => BulkStepStatus::Completed,
            'cancelled' => BulkStepStatus::Cancelled,
            'failed' => BulkStepStatus::Failed,
            'unknown', 'pending' => BulkStepStatus::Accepted,
            default => BulkStepStatus::Progress,
        };
        $checkpoint = $receipt->checkpointSequence === $sequence ? $step->checkpoint : new BulkCheckpoint(
            ($step->checkpoint?->sequence ?? 0) + 1,
            JsonDocuments::capture(['transfer' => $ref->id, 'sequence' => $receipt->checkpointSequence, 'epoch' => $epoch]),
        );

        return new BulkStepResult(
            $status, $checkpoint, array_filter($receipt->stats, is_int(...)),
            $status === BulkStepStatus::Completed ? new BulkOwnerRef(self::OWNER, 'import-result', $ref->id) : null,
            ! empty($receipt->stats['invalid']) || ! empty($receipt->stats['conflict']) ? new BulkOwnerRef(self::OWNER, 'import-failures', $ref->id) : null,
            $receipt->state === 'unknown' ? 'bulk_reconciliation_required' : $receipt->errorCode,
        );
    }

    private function export(BulkContext $context, BulkStep $step): BulkStepResult
    {
        $input = $this->inputs->export($context);
        $this->assertFingerprint($context, $input['fingerprint']);
        $ref = $this->transfer($context);
        $draft = $input['manifest'];
        $manifest = new ExportManifest(
            self::manifestId($context->operationRef), $draft->snapshotMode, $draft->schemaHash, $draft->restoreEpoch, $draft->roster, $draft->partsHash,
        );

        if ($step->cancelRequested) {
            try {
                $this->results->result($context->scope, $ref);

                return new BulkStepResult(BulkStepStatus::Completed, null, [], new BulkOwnerRef(self::OWNER, 'export-result', $ref->id));
            } catch (ExportUnavailable|ImportUnavailable|SessionUnavailable) {
                return new BulkStepResult(BulkStepStatus::Cancelled, null, []);
            }
        }

        $session = $this->sessions->openExport(
            $context->scope, $ref, $input['source'], $manifest, $input['cursor'],
            cancellation: $this->cancellation($step),
            limits: new TransferLimits(batchRows: $step->rowLimit, maxRuntimeSeconds: $step->timeLimitSeconds),
        );
        $sealed = $session->runner->seal($context->scope, $ref->id, $manifest, $session->cursor);
        $this->exports->publish($context->scope, $ref, $sealed);

        return new BulkStepResult(BulkStepStatus::Completed, null, [], new BulkOwnerRef(self::OWNER, 'export-result', $ref->id));
    }

    private function matches(BulkContext $context, BulkOwnerRef $ref, string $kind): bool
    {
        return $ref->owner === self::OWNER && $ref->kind === $kind && $ref->id === $this->transfer($context)->id;
    }

    private function assertFingerprint(BulkContext $context, string $actual): void
    {
        $expected = JsonDocuments::map($context->extensionStamp)['transfer_fingerprint'] ?? null;

        if (! is_string($expected) || preg_match('/\A[a-f0-9]{64}\z/D', $actual) !== 1 || ! hash_equals($expected, $actual)) {
            throw new ApiError(OperationsErrorCode::IdempotencyContractMismatch, 409, 'The pinned transfer contract has changed.');
        }
    }

    private function transfer(BulkContext $context): TransferRef
    {
        return new TransferRef(Uuid::uuid5(Uuid::NAMESPACE_URL, 'corex:equipment:transfer:'.$context->operationRef)->toString());
    }

    public static function manifestId(string $operationRef): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'corex:equipment:manifest:'.$operationRef)->toString();
    }

    private function cancellation(BulkStep $step): CancellationSignal
    {
        return new class($step->cancelRequested) implements CancellationSignal
        {
            public function __construct(private readonly bool $cancelled) {}

            public function isCancelled(): bool
            {
                return $this->cancelled;
            }
        };
    }
}
