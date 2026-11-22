<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Export;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\ExportPublications;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Internal\SessionRuntime;
use CoreX\ImportExport\Internal\TransferReferences;
use CoreX\ImportExport\Internal\Value;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Database\Connection;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class SqlExportPublications implements ExportPublications
{
    public function __construct(
        private Connection $connection,
        private TenantStorage $storage,
        private DataScopeAuthorizer $authorizer,
        private SessionRuntime $runtime,
        private TransferReferences $references,
        private ClockInterface $clock,
        private CurrentExportSchema $schema,
    ) {}

    public function publish(DataScope $scope, TransferRef $transfer, ExportManifest $manifest): void
    {
        if ($this->connection->transactionLevel() !== 0) {
            throw new ExportUnavailable('Export publication requires an independent owner transaction.');
        }

        $this->connection->transaction(function () use ($scope, $transfer, $manifest): void {
            if ($this->connection->getDriverName() === 'pgsql') {
                $this->connection->statement('SET LOCAL TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $this->runtime->assertCurrent(connection: $this->connection, scope: $scope);
            $verify = new SealedExportParts(connection: $this->connection, storage: $this->storage, authorizer: $this->authorizer, schema: $this->schema);
            $stored = $verify->manifest(scope: $scope, jobId: $transfer->id, published: false);

            if (Value::field(row: $stored, name: 'id') !== $manifest->id || Value::field(row: $stored, name: 'parts_hash') !== $manifest->partsHash
                || Value::field(row: $stored, name: 'schema_hash') !== $manifest->schemaHash || $scope->restoreEpoch !== $manifest->restoreEpoch
                || ! Value::sameMap(left: Value::map(json_decode(Value::text(Value::field(row: $stored, name: 'roster')), true, flags: JSON_THROW_ON_ERROR)), right: JsonDocuments::map($manifest->roster))) {
                throw new ExportUnavailable('Export publication does not address the sealed manifest.');
            }
            $verify->summary(scope: $scope, manifest: $stored);

            if (Value::field(row: $stored, name: 'state') === 'published') {
                return;
            }

            $this->references->enlist(scope: $scope, connection: $this->connection, stamp: JsonDocuments::map($manifest->roster));
            $changed = $this->connection->table('corex_export_manifests')->where('id', $manifest->id)->where('job_id', $transfer->id)
                ->where('state', 'sealed')->where('parts_hash', $manifest->partsHash)->update(['state' => 'published', 'published_at' => $this->clock->now()]);

            if ($changed !== 1) {
                throw new ExportUnavailable('Export publication could not acknowledge the seal.');
            }
            $this->connection->table('corex_transfer_jobs')->where('id', $transfer->id)->update(['state' => 'completed']);
        });
    }
}
