<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Contracts\TransferSource;
use CoreX\ImportExport\Data\RowBatch;
use CoreX\ImportExport\Data\SourceCursor;
use CoreX\ImportExport\Data\SourceManifest;
use CoreX\ImportExport\Data\SourceRow;
use CoreX\ImportExport\Data\TransferLimits;
use RuntimeException;

final readonly class NdjsonEquipment implements TransferSource
{
    public function __construct(private string $name, private string $schema, private string $codec) {}

    public function describe(DataScope $scope): SourceManifest
    {
        $bytes = InputIdentity::content($this->name);

        return new SourceManifest($this->name, hash('sha256', $bytes), strlen($bytes), 'ndjson', $this->codec, 'UTF-8', ',', '"', '', $this->schema);
    }

    public function read(SourceManifest $source, SourceCursor $after, TransferLimits $limits): RowBatch
    {
        $bytes = InputIdentity::content($this->name);
        $offset = (int) $after->boundaryByteOffset;
        $ordinal = (int) $after->ordinal;

        if ($source->sha256 !== hash('sha256', $bytes) || $source->sha256 !== $after->sourceHash
            || $source->length !== strlen($bytes) || $source->length > $limits->maxFileBytes
            || $source->parserVersion !== $this->codec || $source->schemaHash !== $this->schema
            || $offset > $source->length || substr_count(substr($bytes, 0, $offset), "\n") !== $ordinal
            || ($offset > 0 && $bytes[$offset - 1] !== "\n")) {
            throw new RuntimeException('Pinned NDJSON manifest or byte boundary differs.');
        }

        $rows = [];
        while ($offset < $source->length && count($rows) < $limits->batchRows) {
            $end = strpos($bytes, "\n", $offset);

            if ($end === false || $end - $offset > $limits->maxRecordBytes) {
                throw new RuntimeException('NDJSON row exceeds the pinned byte limits.');
            }
            $values = json_decode(substr($bytes, $offset, $end - $offset), true, $limits->maxDepth, JSON_THROW_ON_ERROR);

            if (! is_array($values) || array_is_list($values) || count($values) > $limits->maxFields) {
                throw new RuntimeException('NDJSON row must be a bounded JSON object.');
            }
            $ordinal++;
            $offset = $end + 1;
            $rows[] = new SourceRow((string) $ordinal, $values, $source->sha256);
        }
        Journal::record('import-read', ['ordinal' => $after->ordinal, 'ids' => array_map(static fn (SourceRow $row): string => $row->ordinal, $rows),
            'rowLimit' => $limits->batchRows, 'seconds' => $limits->maxRuntimeSeconds, 'source' => $source->sha256]);

        return new RowBatch($rows, new SourceCursor((string) $ordinal, (string) $offset, $source->sha256), $offset < $source->length);
    }
}
