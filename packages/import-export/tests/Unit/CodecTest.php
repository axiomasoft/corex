<?php

declare(strict_types=1);

use CoreX\ImportExport\Internal\Codecs\CsvCodec;
use CoreX\ImportExport\Internal\Codecs\NdjsonCodec;
use CoreX\ImportExport\Internal\Export\CanonicalExportCodec;
use CoreX\ImportExport\Support\CanonicalRecord;

it('parses a multiline CSV logical record and escaped quote as one bounded row', function (): void {
    $rows = (new CsvCodec)->decode("name,notes\nAda,\"line one\nline \"\"two\"\"\"\n");

    expect($rows)->toBe([['name', 'notes'], ['Ada', "line one\nline \"two\""]]);
});

it('refuses a CSV record before it can grow beyond its limit', function (): void {
    expect(fn (): array => (new CsvCodec)->decode(bytes: 'abcdef', maxRecordBytes: 5))
        ->toThrow(InvalidArgumentException::class);
});

it('decodes only NDJSON objects and enforces the per-record byte limit', function (): void {
    expect((new NdjsonCodec)->decode("{\"id\":\"one\"}\n{\"id\":\"two\"}\n"))->toBe([['id' => 'one'], ['id' => 'two']]);
    expect(fn (): array => (new NdjsonCodec)->decode('["not-an-object"]'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn (): array => (new NdjsonCodec)->decode(bytes: '{"id":"too-long"}', maxRecordBytes: 5))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps raw NDJSON separate from the labelled display-safe CSV projection', function (): void {
    $record = new CanonicalRecord('contact', 'archive', '=SUM(A1:A2)', 'rev-1', null, ['amount' => '10.00', 'missing' => null], str_repeat('a', 64));
    $codec = new CanonicalExportCodec;

    expect($codec->rawNdjson([$record], 1024))->toContain('"externalId":"=SUM(A1:A2)"')
        ->and($codec->displaySafeCsv([$record], 1024)['csv'])->toContain("'=SUM(A1:A2)")
        ->and($codec->displaySafeCsv([$record], 1024)['profile'])->toBe('displaySafe/spreadsheet-formula-prefix/1');
});
