<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data\Export;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class ExportManifest
{
    /** The closed JSON roster retains the original transport shape. */
    public function __construct(
        public string $id,
        public string $snapshotMode,
        public string $schemaHash,
        public string $restoreEpoch,
        public JsonDocument $roster,
        public string $partsHash,
    ) {
        if (! in_array($snapshotMode, ['coherent', 'liveBounded'], true)) {
            throw new InvalidArgumentException('Export snapshot mode is invalid.');
        }

        foreach ([$id, $schemaHash, $restoreEpoch, $partsHash] as $value) {
            if ($value === '') {
                throw new InvalidArgumentException('Export manifest values must not be empty.');
            }
        }

        foreach ([$schemaHash, $restoreEpoch, $partsHash] as $hash) {
            if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                throw new InvalidArgumentException('Export manifest hashes must be lowercase SHA-256 values.');
            }
        }
    }
}
