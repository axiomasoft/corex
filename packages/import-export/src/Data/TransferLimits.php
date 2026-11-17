<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class TransferLimits
{
    public function __construct(public int $maxFileBytes = 1_073_741_824, public int $maxRecordBytes = 1_048_576, public int $maxFields = 256, public int $maxDepth = 32, public int $batchRows = 500, public int $maxErrors = 1_000, public int $maxRuntimeSeconds = 30, public int $maxMemoryMiB = 128)
    {
        foreach ([$maxFileBytes, $maxRecordBytes, $maxFields, $maxDepth, $batchRows, $maxErrors, $maxRuntimeSeconds, $maxMemoryMiB] as $limit) {
            if ($limit < 1) {
                throw new InvalidArgumentException('Transfer limits must be positive.');
            }
        }

        if ($maxRecordBytes > $maxFileBytes || $maxFileBytes > 1_073_741_824 || $maxFields > 256 || $maxDepth > 32 || $batchRows > 500 || $maxErrors > 1_000 || $maxRuntimeSeconds > 30 || $maxMemoryMiB > 1024) {
            throw new InvalidArgumentException('Transfer limits exceed the qualified profile.');
        }
    }
}
