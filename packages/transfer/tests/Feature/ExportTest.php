<?php

declare(strict_types=1);

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Transfer\Data\Export\ExportCursor;
use CoreX\Transfer\Data\Export\ExportManifest;
use CoreX\Transfer\Data\Export\ExportPage;

it('makes coherent manifests and their cursor binding explicit', function (): void {
    $hash = str_repeat('a', 64);
    $manifest = new ExportManifest('manifest-1', 'coherent', $hash, $hash, JsonDocuments::capture(['contact' => ['count' => 1]]), $hash);
    $cursor = new ExportCursor('0', '0', $manifest->partsHash);

    expect($manifest->snapshotMode)->toBe('coherent')
        ->and($cursor->manifestHash)->toBe($manifest->partsHash)
        ->and(fn (): ExportPage => new ExportPage([], $cursor, true))->toThrow(InvalidArgumentException::class);
});
