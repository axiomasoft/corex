<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Export;

use CoreX\ImportExport\Support\CanonicalRecord;

final class CanonicalExportCodec
{
    /** @param list<CanonicalRecord> $records */
    public function rawNdjson(array $records, int $maxBytes): string
    {
        $lines = [];

        foreach ($records as $record) {
            $lines[] = json_encode($this->rawRecord($record), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        $bytes = implode("\n", $lines)."\n";

        if (strlen($bytes) > $maxBytes) {
            throw new ExportUnavailable('Export part exceeds its qualified byte limit.');
        }

        return $bytes;
    }

    /** @param list<CanonicalRecord> $records
     * @return array{csv: string, schema: string, profile: string}
     */
    public function displaySafeCsv(array $records, int $maxBytes): array
    {
        $columns = ['entity', 'externalId', 'sourceRevision', 'values'];
        $lines = [implode(',', $columns)];

        foreach ($records as $record) {
            $lines[] = implode(',', [
                $this->csv($record->entity),
                $this->csv($record->externalId),
                $this->csv($record->sourceRevision),
                $this->csv(json_encode($record->values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            ]);
        }

        $csv = implode("\n", $lines)."\n";

        if (strlen($csv) > $maxBytes) {
            throw new ExportUnavailable('Export display part exceeds its qualified byte limit.');
        }

        return [
            'csv' => $csv,
            'schema' => json_encode(['columns' => $columns, 'valueEncoding' => 'json', 'presence' => 'explicit'], JSON_THROW_ON_ERROR),
            'profile' => 'displaySafe/spreadsheet-formula-prefix/1',
        ];
    }

    /** @return array<string, mixed> */
    private function rawRecord(CanonicalRecord $record): array
    {
        return [
            'entity' => $record->entity,
            'externalSystem' => $record->externalSystem,
            'externalId' => $record->externalId,
            'sourceRevision' => $record->sourceRevision,
            'sourceUpdatedAt' => $record->sourceUpdatedAt?->format('Y-m-d\\TH:i:s.u\\Z'),
            'values' => $record->values,
            'payloadHash' => $record->payloadHash,
        ];
    }

    private function csv(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            $value = "'{$value}";
        }

        return '"'.str_replace('"', '""', $value).'"';
    }
}
