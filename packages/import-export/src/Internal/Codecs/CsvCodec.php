<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Codecs;

use InvalidArgumentException;

final class CsvCodec
{
    /** @return list<list<string>> */
    public function decode(string $bytes, int $maxRecordBytes = 1_048_576, int $maxFields = 256): array
    {
        if ($maxRecordBytes < 1 || $maxFields < 1) {
            throw new InvalidArgumentException('CSV limits must be positive.');
        }

        $rows = [];
        $record = '';
        $quoted = false;
        $length = strlen($bytes);

        for ($offset = 0; $offset < $length; $offset++) {
            $byte = $bytes[$offset];
            $record .= $byte;

            if (strlen($record) > $maxRecordBytes) {
                throw new InvalidArgumentException('CSV logical record exceeds its byte limit.');
            }

            if ($byte === '"') {
                if ($quoted && (($bytes[$offset + 1] ?? null) === '"')) {
                    $record .= $bytes[++$offset];

                    continue;
                }
                $quoted = ! $quoted;
            }

            if ($byte !== "\n" || $quoted) {
                continue;
            }
            $rows[] = $this->parseRecord($record, $maxFields);
            $record = '';
        }

        if ($quoted) {
            throw new InvalidArgumentException('CSV contains an unterminated quoted record.');
        }

        if ($record !== '') {
            $rows[] = $this->parseRecord($record, $maxFields);
        }

        return $rows;
    }

    /** @return list<string> */
    private function parseRecord(string $record, int $maxFields): array
    {
        $fields = str_getcsv(rtrim($record, "\r\n"), ',', '"', '\\');

        if (count($fields) > $maxFields) {
            throw new InvalidArgumentException('CSV field limit or syntax violation.');
        }

        return $fields;
    }
}
