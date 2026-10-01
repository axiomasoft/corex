<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Fixtures;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkTransferSession;
use CoreX\Transfer\Contracts\CancellationSignal;
use CoreX\Transfer\Contracts\ExportRunner;
use CoreX\Transfer\Contracts\TransferResultReader;
use CoreX\Transfer\Contracts\TransferRunner;
use CoreX\Transfer\Data\Export\ExportCursor;
use CoreX\Transfer\Data\Export\ExportManifest;
use CoreX\Transfer\Data\SourceCursor;
use CoreX\Transfer\Data\StepToken;
use CoreX\Transfer\Data\TransferReceipt;
use CoreX\Transfer\Data\TransferRef;
use RuntimeException;

final class BulkTransferFactory implements \CoreX\Operations\Bulk\Contracts\BulkTransferFactory
{
    /** @var array<string, string> */
    public array $tokens = [];

    public int $sinkWrites = 0;

    public int $steps = 0;

    public bool $crashBeforeCheckpoint = false;

    public bool $cancelled = false;

    /** @var array{restore_epoch?: string, version?: string, rights?: string} */
    public array $drift = [];

    public function create(BulkOperation $operation): BulkTransferSession
    {
        $id = $operation->definition->id;
        $kind = JsonDocuments::map($operation->definition->inputRef)['kind'] ?? 'import';
        $attempt = is_string(JsonDocuments::map($operation->checkpoint)['attempt_id'] ?? null)
            ? JsonDocuments::map($operation->checkpoint)['attempt_id']
            : $id;

        if ($kind === 'export' && (JsonDocuments::map($operation->checkpoint)['sealed'] ?? true) !== true && ! isset($this->tokens[$id])) {
            $attempt .= '-retry';
        }

        $this->tokens[$id] ??= $attempt;
        $stamp = JsonDocuments::map($operation->definition->extensionStamp);
        $restore = $this->drift['restore_epoch'] ?? (string) $stamp['restore_epoch'];
        $version = $this->drift['version'] ?? (string) $stamp['version'];
        $rights = $this->drift['rights'] ?? (string) $stamp['rights'];
        $zero = hash('sha256', 'zero');
        $token = new StepToken(
            checkpointSequence: (string) (JsonDocuments::map($operation->checkpoint)['sequence'] ?? '0'),
            epoch: '1',
            sourceHash: $zero,
            mappingHash: hash('sha256', $rights),
            schemaHash: hash('sha256', $version),
            restoreEpoch: $restore,
        );
        $transfer = new TransferRef($this->tokens[$id]);
        $signal = new class($this->cancelled) implements CancellationSignal
        {
            public function __construct(private bool $cancelled) {}

            public function isCancelled(): bool
            {
                return $this->cancelled;
            }
        };
        $reader = new class implements TransferResultReader
        {
            public function failures(DataScope $scope, TransferRef $ref, ?string $afterOrdinal, int $limit): array
            {
                return ['records' => [], 'next' => null];
            }

            public function result(DataScope $scope, TransferRef $ref): array
            {
                return ['state' => 'sealed'];
            }
        };

        if ($kind === 'export') {
            $manifest = new ExportManifest(
                id: $this->tokens[$id],
                snapshotMode: 'coherent',
                schemaHash: $token->schemaHash,
                restoreEpoch: $token->restoreEpoch,
                roster: JsonDocuments::capture(['rows' => 1]),
                partsHash: $zero,
            );

            return new BulkTransferSession(
                runner: new ExportProbe($this),
                transfer: $transfer,
                token: $token,
                cancellation: $signal,
                results: $reader,
                manifest: $manifest,
                cursor: new ExportCursor('0', '0', $zero),
            );
        }

        return new BulkTransferSession(
            runner: new ImportProbe($this),
            transfer: $transfer,
            token: $token,
            cancellation: $signal,
            results: $reader,
        );
    }
}

final class ImportProbe implements TransferRunner
{
    public function __construct(private BulkTransferFactory $factory) {}

    public function step(DataScope $scope, TransferRef $transfer, StepToken $token): TransferReceipt
    {
        $this->factory->steps++;

        if ($this->factory->crashBeforeCheckpoint) {
            $this->factory->crashBeforeCheckpoint = false;

            throw new RuntimeException('crash before checkpoint');
        }

        $completed = $this->factory->tokens['completed'] ?? null;

        if ($completed === $token->checkpointSequence && $token->checkpointSequence !== '0') {
            return new TransferReceipt(
                state: 'running',
                cursor: new SourceCursor($token->checkpointSequence, null, $token->sourceHash),
                checkpointSequence: $token->checkpointSequence,
                stats: ['succeeded' => 1],
                gaps: [],
                errorCode: null,
            );
        }

        $next = (string) ((int) $token->checkpointSequence + 1);
        $this->factory->sinkWrites++;
        $this->factory->tokens['completed'] = $next;

        return new TransferReceipt(
            state: 'running',
            cursor: new SourceCursor($next, null, $token->sourceHash),
            checkpointSequence: $next,
            stats: ['succeeded' => 1],
            gaps: [],
            errorCode: null,
        );
    }
}

final class ExportProbe implements ExportRunner
{
    public function __construct(private BulkTransferFactory $factory) {}

    public function seal(DataScope $scope, string $jobId, ExportManifest $draft, ExportCursor $after): ExportManifest
    {
        $this->factory->steps++;

        return $draft;
    }

    public function emit(DataScope $scope, ExportManifest $manifest, ExportCursor $cursor): string
    {
        return $manifest->id;
    }
}
