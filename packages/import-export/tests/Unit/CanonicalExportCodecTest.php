<?php

declare(strict_types=1);

use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Internal\Export\CanonicalExportCodec;

it('keeps raw NDJSON separate from the labelled display-safe CSV projection', function (): void {
    $record = new CanonicalRecord('contact', 'archive', '=SUM(A1:A2)', 'rev-1', null, ['amount' => '10.00', 'missing' => null], str_repeat('a', 64));
    $codec = new CanonicalExportCodec;

    expect($codec->rawNdjson([$record], 1024))->toContain('"externalId":"=SUM(A1:A2)"')
        ->and($codec->displaySafeCsv([$record], 1024)['csv'])->toContain("'=SUM(A1:A2)")
        ->and($codec->displaySafeCsv([$record], 1024)['profile'])->toBe('displaySafe/spreadsheet-formula-prefix/1');
});
