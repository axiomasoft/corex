<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use App\Scenario\Scenario;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Contracts\ExportSource;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\Export\ExportPage;
use CoreX\ImportExport\Data\TransferLimits;
use RuntimeException;

final class PostgresExport implements ExportSource
{
    public function page(DataScope $scope, ExportManifest $manifest, ExportCursor $after, TransferLimits $limits): ExportPage
    {
        $connection = resolve('db')->connection();
        app(CurrentWorkScope::class)->assert($scope, $connection);
        $isolation = $connection->selectOne('SHOW transaction_isolation')->transaction_isolation;

        if ($connection->transactionLevel() < 1 || ! in_array($isolation, ['repeatable read', 'serializable'], true)) {
            throw new RuntimeException('Export page is outside the owner coherent PostgreSQL snapshot.');
        }
        $row = $connection->table('bulk_fixture_export')->where('ordinal', '>', (int) $after->rowOrdinal)->orderBy('ordinal')->first();

        if ($row === null) {
            throw new RuntimeException('Export cursor does not address an expected equipment row.');
        }
        $complete = (int) $row->ordinal === 2;
        $values = [InputIdentity::SERIAL => $row->serial];
        Journal::record('export-read', [
            'ordinal' => (int) $row->ordinal, 'serial' => $row->serial, 'snapshot' => $connection->selectOne('SELECT pg_current_snapshot() AS id')->id,
            'isolation' => $isolation, 'rowLimit' => $limits->batchRows, 'seconds' => $limits->maxRuntimeSeconds,
        ]);

        if (getenv('COREX_BULK_MODE') === 'export-ack' && (int) $row->ordinal === 1) {
            Scenario::barrier('export-snapshot-open', Scenario::describe($connection));
        }

        return new ExportPage([new CanonicalRecord(
            InputIdentity::ENTITY, 'equipment-fixture', $row->id, $row->revision, null, $values, InputIdentity::digest($values),
        )], $complete ? null : new ExportCursor((string) ((int) $after->partNumber + 1), (string) $row->ordinal, $manifest->partsHash), $complete);
    }
}
