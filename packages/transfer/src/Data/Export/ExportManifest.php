<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data\Export;

use InvalidArgumentException;

final readonly class ExportManifest
{
    /** @param array<string, null|bool|int|string|array<array-key, mixed>> $roster */
    public function __construct(
        public string $id,
        public string $snapshotMode,
        public string $schemaHash,
        public string $restoreEpoch,
        public array $roster,
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
            if (! preg_match('/^[a-f0-9]{64}$/', $hash)) {
                throw new InvalidArgumentException('Export manifest hashes must be lowercase SHA-256 values.');
            }
        }
    }
}
