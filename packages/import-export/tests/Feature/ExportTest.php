<?php

declare(strict_types=1);

use CoreX\ImportExport\Support\Export\ExportCursor;
use CoreX\ImportExport\Support\Export\ExportManifest;
use CoreX\ImportExport\Support\Export\ExportPage;

it('makes coherent manifests and their cursor binding explicit', function (): void {
    $hash = str_repeat('a', 64);
    $manifest = new ExportManifest('manifest-1', 'coherent', $hash, $hash, ['contact' => ['count' => 1]], $hash);
    $cursor = new ExportCursor('0', '0', $manifest->partsHash);

    expect($manifest->snapshotMode)->toBe('coherent')
        ->and($cursor->manifestHash)->toBe($manifest->partsHash)
        ->and(fn (): ExportPage => new ExportPage([], $cursor, true))->toThrow(InvalidArgumentException::class);
});
