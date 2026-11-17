<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal\Codecs;

use InvalidArgumentException;

final class NdjsonCodec
{
    /** @return list<array<array-key, mixed>> */
    public function decode(string $bytes, int $maxRecordBytes = 1_048_576): array
    {
        if ($maxRecordBytes < 1) {
            throw new InvalidArgumentException('NDJSON record limit must be positive.');
        }

        $records = [];
        foreach (explode("\n", $bytes) as $line) {
            $line = rtrim($line, "\r");

            if ($line === '') {
                continue;
            }

            if (strlen($line) > $maxRecordBytes) {
                throw new InvalidArgumentException('NDJSON record exceeds its byte limit.');
            }
            $value = json_decode($line, true, 64, JSON_THROW_ON_ERROR);

            if (! is_array($value) || array_is_list($value)) {
                throw new InvalidArgumentException('Each NDJSON record must be an object.');
            }
            $records[] = $value;
        }

        return $records;
    }
}
