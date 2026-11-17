<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Contracts\CancellationSignal;
use CoreX\ImportExport\Contracts\ExportSource;
use CoreX\ImportExport\Contracts\ImportExportFactory;
use CoreX\ImportExport\Contracts\RecordMapper;
use CoreX\ImportExport\Contracts\TransferSink;
use CoreX\ImportExport\Contracts\TransferSource;
use CoreX\ImportExport\Data\ConflictRule;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\ExportSession;
use CoreX\ImportExport\Data\ImportSession;
use CoreX\ImportExport\Data\MappingSnapshot;
use CoreX\ImportExport\Data\StepToken;
use CoreX\ImportExport\Data\TransferLimits;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Exceptions\SessionUnavailable;
use CoreX\ImportExport\Internal\Export\CanonicalExportCodec;
use CoreX\ImportExport\Internal\Export\DatabaseExportRunner;
use CoreX\ImportExport\Internal\Transfer\NeverCancelled;
use CoreX\ImportExport\Internal\Transfer\SqlTransferExecutor;
use CoreX\ImportExport\Internal\Transfer\SqlTransferResultReader;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Override;
use Psr\Clock\ClockInterface;

final readonly class SqlImportExportFactory implements ImportExportFactory
{
    public function __construct(
        private DatabaseManager $database,
        private DataScopeAuthorizer $authorizer,
        private ClockInterface $clock,
        private TenantStorage $storage,
        private TransferLimits $limits,
        private SessionRuntime $runtime,
    ) {}

    #[Override]
    public function openImport(DataScope $scope, TransferRef $transfer, TransferSource $source, RecordMapper $mapper, TransferSink $target, MappingSnapshot $mapping, ConflictRule $conflicts, bool $dryRun = false, ?CancellationSignal $cancellation = null): ImportSession
    {
        $connection = $this->connection($scope, $transfer);
        $manifest = $source->describe($scope);

        if (! hash_equals($manifest->schemaHash, $mapping->schemaHash)) {
            throw new SessionUnavailable('The source and mapping schemas differ.');
        }

        $token = $this->open(
            connection: $connection,
            scope: $scope,
            transfer: $transfer,
            mode: $dryRun ? 'dryRun' : 'import',
            sourceHash: $manifest->sha256,
            mappingHash: $mapping->sha256,
            schemaHash: $manifest->schemaHash,
            sourceManifest: json_encode($manifest, JSON_THROW_ON_ERROR),
            mappingSnapshot: json_encode($mapping, JSON_THROW_ON_ERROR),
            requestIdentity: json_encode([$manifest, $mapping, $conflicts, $dryRun], JSON_THROW_ON_ERROR),
        );
        $cancellation ??= new NeverCancelled;

        $runtime = $this->runtime->forTransfer($transfer->id);

        return new ImportSession(
            runner: new SqlTransferExecutor($connection, $source, $mapper, $target, $mapping, $conflicts, $this->limits, $this->authorizer, $this->clock, $cancellation, runtime: $runtime),
            transfer: $transfer,
            token: $token,
            results: new SqlTransferResultReader($connection, $this->authorizer, $runtime),
            cancellation: $cancellation,
        );
    }

    #[Override]
    public function openExport(DataScope $scope, TransferRef $transfer, ExportSource $source, ExportManifest $manifest, ExportCursor $cursor, ?CancellationSignal $cancellation = null): ExportSession
    {
        $connection = $this->connection($scope, $transfer);

        if ($manifest->snapshotMode !== 'coherent' || ! hash_equals($scope->restoreEpoch, $manifest->restoreEpoch)
            || $cursor->partNumber !== '0' || $cursor->rowOrdinal !== '0') {
            throw new SessionUnavailable('A coherent export under the current restore epoch is required.');
        }

        $identity = json_encode([$manifest, $cursor], JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $identity);
        $token = $this->open($connection, $scope, $transfer, 'export', $hash, $hash, $manifest->schemaHash, json_encode($manifest, JSON_THROW_ON_ERROR), '{}', $identity);
        $cancellation ??= new NeverCancelled;

        $runtime = $this->runtime->forTransfer($transfer->id, $identity);

        return new ExportSession(
            runner: new DatabaseExportRunner($connection, $this->storage, $source, $this->limits, new CanonicalExportCodec, $this->authorizer, $cancellation, runtime: $runtime),
            transfer: $transfer,
            token: $token,
            results: new SqlTransferResultReader($connection, $this->authorizer, $runtime),
            cancellation: $cancellation,
            manifest: $manifest,
            cursor: $cursor,
        );
    }

    private function connection(DataScope $scope, TransferRef $transfer): ConnectionInterface
    {
        $connection = $this->database->connection();
        $this->runtime->assertCurrent($connection, $scope);

        if (! Str::isUuid($transfer->id)
            || $scope->connectionIdentity !== $connection->getDriverName().':'.$connection->getDatabaseName()
            || ! $this->authorizer->authorize($scope, '*', $transfer->id, [], 'transfer.open')->allowed) {
            throw new SessionUnavailable('The transfer session is unavailable under the current authority.');
        }

        return $connection;
    }

    private function open(ConnectionInterface $connection, DataScope $scope, TransferRef $transfer, string $mode, string $sourceHash, string $mappingHash, string $schemaHash, string $sourceManifest, string $mappingSnapshot, string $requestIdentity): StepToken
    {
        $requestHash = hash('sha256', $requestIdentity);
        $manifest = Value::map(json_decode($sourceManifest, true, 32, JSON_THROW_ON_ERROR));
        $manifest['_scope_hash'] = SessionIdentity::scopeHash($scope);
        $manifest['_request_hash'] = $requestHash;

        return $connection->transaction(function () use ($connection, $scope, $transfer, $mode, $sourceHash, $mappingHash, $schemaHash, $manifest, $mappingSnapshot, $requestHash): StepToken {
            $connection->table('corex_transfer_jobs')->insertOrIgnore([
                'id' => $transfer->id,
                'source_manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
                'source_hash' => $sourceHash,
                'mapping_snapshot' => $mappingSnapshot,
                'mapping_hash' => $mappingHash,
                'schema_hash' => $schemaHash,
                'restore_epoch' => $scope->restoreEpoch,
                'mode' => $mode,
                'state' => 'pending',
                'checkpoint_seq' => '0',
                'cursor' => '{"ordinal":"0","boundaryByteOffset":"0"}',
                'stats' => '{}',
                'claim_epoch' => '1',
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $job = $connection->table('corex_transfer_jobs')->where('id', $transfer->id)->lockForUpdate()->first();

            if ($job === null) {
                throw new SessionUnavailable('The transfer session could not be persisted.');
            }

            SessionIdentity::assertScope($job, $scope);
            $stored = json_decode(Value::text($job->source_manifest), true, 32, JSON_THROW_ON_ERROR);

            if (! is_array($stored) || ($stored['_request_hash'] ?? null) !== $requestHash
                || ($stored['_scope_hash'] ?? null) !== $manifest['_scope_hash'] || $job->mode !== $mode) {
                throw new SessionUnavailable('The transfer identity is already bound to another request.');
            }

            return new StepToken(Value::text($job->checkpoint_seq), Value::text($job->claim_epoch), $sourceHash, $mappingHash, $schemaHash, $scope->restoreEpoch);
        });
    }
}
