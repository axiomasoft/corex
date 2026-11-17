<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\ExportSource;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\Export\ExportPage;
use CoreX\ImportExport\Data\TransferLimits;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Internal\Export\CanonicalExportCodec;
use CoreX\ImportExport\Internal\Export\DatabaseExportRunner;
use CoreX\ImportExport\Tests\PgTestCase;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Storage\Data\ExportArchiveRef;
use CoreX\Storage\Data\StorageCapabilities;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(PgTestCase::class)->group('pg');

it('seals verified immutable parts and emits them only under current authority', function (): void {
    $hash = str_repeat('a', 64);
    $jobId = '11111111-1111-4111-8111-111111111111';
    $manifestId = '22222222-2222-4222-8222-222222222222';
    exportInsertJob($jobId, $hash);
    $runner = exportRunner(record: exportRecord($hash));
    $draft = new ExportManifest($manifestId, 'coherent', $hash, $hash, JsonDocuments::capture(['contact' => ['count' => 1]]), str_repeat('0', 64));

    $sealed = $runner->seal(scope: exportScope($hash), jobId: $jobId, draft: $draft, after: new ExportCursor('0', '0', $hash));
    $bytes = $runner->emit(scope: exportScope($hash), manifest: $sealed, cursor: new ExportCursor('0', '0', $sealed->partsHash));

    expect($sealed->partsHash)->not->toBe(str_repeat('0', 64))
        ->and($bytes)->toContain('"externalId":"doc-1"')
        ->and(DB::table('corex_export_manifests')->value('state'))->toBe('sealed')
        ->and(DB::table('corex_export_parts')->value('state'))->toBe('verified');
});

it('refuses output after a current policy revocation or an incompatible restore epoch', function (): void {
    $hash = str_repeat('b', 64);
    $jobId = '33333333-3333-4333-8333-333333333333';
    $manifestId = '44444444-4444-4444-8444-444444444444';
    exportInsertJob($jobId, $hash);
    $runner = exportRunner(record: exportRecord($hash));
    $sealed = $runner->seal(scope: exportScope($hash), jobId: $jobId, draft: new ExportManifest($manifestId, 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64)), after: new ExportCursor('0', '0', $hash));

    expect(fn (): string => exportRunner(record: exportRecord($hash), allowed: false)->emit(scope: exportScope($hash), manifest: $sealed, cursor: new ExportCursor('0', '0', $sealed->partsHash)))
        ->toThrow(ExportUnavailable::class)
        ->and(fn (): string => $runner->emit(scope: exportScope(str_repeat('c', 64)), manifest: $sealed, cursor: new ExportCursor('0', '0', $sealed->partsHash)))
        ->toThrow(ExportUnavailable::class);
});

it('aborts an unsealed capture instead of resuming its mutable snapshot', function (): void {
    $hash = str_repeat('d', 64);
    $jobId = '55555555-5555-4555-8555-555555555555';
    $manifestId = '66666666-6666-4666-8666-666666666666';
    exportInsertJob($jobId, $hash);
    DB::table('corex_export_manifests')->insert([
        'id' => $manifestId, 'job_id' => $jobId, 'capture_attempt' => hash('sha256', $manifestId), 'schema_hash' => $hash,
        'restore_epoch' => $hash, 'roster' => '{}', 'parts_hash' => null, 'snapshot_mode' => 'coherent', 'state' => 'capturing',
    ]);

    expect(fn (): ExportManifest => exportRunner(record: exportRecord($hash))->seal(
        scope: exportScope($hash), jobId: $jobId,
        draft: new ExportManifest($manifestId, 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    ))->toThrow(ExportUnavailable::class)
        ->and(DB::table('corex_export_manifests')->value('state'))->toBe('aborted');
});

it('captures source pages inside a repeatable-read transaction', function (): void {
    $hash = str_repeat('e', 64);
    $jobId = '77777777-7777-4777-8777-777777777777';
    $manifestId = '88888888-8888-4888-8888-888888888888';
    $observed = (object) ['isolation' => null];
    exportInsertJob($jobId, $hash);
    $source = new readonly class(exportRecord($hash), $observed) implements ExportSource
    {
        public function __construct(private CanonicalRecord $record, private object $observed) {}

        public function page(DataScope $scope, ExportManifest $manifest, ExportCursor $after, TransferLimits $limits): ExportPage
        {
            $this->observed->isolation = DB::selectOne('show transaction_isolation')->transaction_isolation;

            return new ExportPage([$this->record], null, true);
        }
    };

    exportRunner(record: exportRecord($hash), source: $source)->seal(
        scope: exportScope($hash), jobId: $jobId,
        draft: new ExportManifest($manifestId, 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    );

    expect($observed->isolation)->toBe('repeatable read');
});

function exportInsertJob(string $id, string $hash): void
{
    DB::table('corex_transfer_jobs')->insert([
        'id' => $id, 'source_manifest' => '{}', 'source_hash' => $hash, 'mapping_snapshot' => '{}', 'mapping_hash' => $hash,
        'schema_hash' => $hash, 'restore_epoch' => $hash, 'mode' => 'export', 'state' => 'running', 'checkpoint_seq' => '0',
        'cursor' => '{"ordinal":"0","boundaryByteOffset":"0"}', 'stats' => '{}', 'claim_epoch' => '1', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function exportScope(string $hash): DataScope
{
    return DataScope::fromTenantContext(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: 'pgsql:corex_test', actorId: 'user-1',
        restoreEpoch: $hash,
    );
}

function exportRecord(string $hash): CanonicalRecord
{
    return new CanonicalRecord('contact', 'archive', 'doc-1', 'rev-1', null, ['name' => 'Ada'], $hash);
}

function exportRunner(CanonicalRecord $record, ?ExportSource $source = null, bool $allowed = true): DatabaseExportRunner
{
    $source ??= new readonly class($record) implements ExportSource
    {
        public function __construct(private CanonicalRecord $record) {}

        public function page(DataScope $scope, ExportManifest $manifest, ExportCursor $after, TransferLimits $limits): ExportPage
        {
            return new ExportPage([$this->record], null, true);
        }
    };
    $storage = new readonly class implements TenantStorage
    {
        private Filesystem $disk;

        public function __construct()
        {
            $this->disk = Storage::build([
                'driver' => 'local',
                'root' => sys_get_temp_dir().'/corex-import-export-'.bin2hex(random_bytes(8)),
            ]);
        }

        public function disk(): Filesystem
        {
            return $this->disk;
        }

        public function ephemeralDisk(): Filesystem
        {
            return $this->disk;
        }

        public function capabilities(): StorageCapabilities
        {
            return new StorageCapabilities(usage: false, export: true);
        }

        public function usageBytes(): int
        {
            return 0;
        }

        public function exportArchive(): ExportArchiveRef
        {
            return new ExportArchiveRef('not-used', 0, new DateTimeImmutable);
        }
    };

    return new DatabaseExportRunner(
        DB::connection(),
        $storage,
        $source,
        new TransferLimits,
        new CanonicalExportCodec,
        new CallableDataScopeAuthorizer(static fn (): bool => $allowed),
    );
}
