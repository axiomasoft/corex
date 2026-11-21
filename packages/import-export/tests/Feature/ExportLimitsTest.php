<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\CancellationSignal;
use CoreX\ImportExport\Contracts\ExportSource;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\Export\ExportPage;
use CoreX\ImportExport\Data\TransferLimits;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Internal\Export\CanonicalExportCodec;
use CoreX\ImportExport\Internal\Export\DatabaseExportRunner;
use CoreX\ImportExport\Internal\SessionIdentity;
use CoreX\ImportExport\Internal\Transfer\ExecutionGuard;
use CoreX\ImportExport\Internal\Transfer\TransferLimitReached;
use CoreX\ImportExport\Tests\PgTestCase;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Storage\Data\ExportArchiveRef;
use CoreX\Storage\Data\StorageCapabilities;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(PgTestCase::class)->group('pg');

it('rejects a page that exceeds the row cap even when it is a single part', function (): void {
    $hash = str_repeat('a', 64);
    $jobId = '11111111-1111-4111-8111-111111111101';
    $manifestId = '11111111-1111-4111-8111-111111111102';
    exportLimitJob($jobId, $hash);

    expect(fn () => exportLimitRunner(records: 2, limits: new TransferLimits(batchRows: 1))->seal(
        scope: exportLimitScope($hash),
        jobId: $jobId,
        draft: new ExportManifest($manifestId, 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    ))->toThrow(ExportUnavailable::class)
        ->and(DB::table('corex_export_manifests')->whereIn('state', ['sealed', 'published'])->count())->toBe(0);
});

it('seals several parts when each page stays inside the row cap', function (): void {
    $hash = str_repeat('b', 64);
    $jobId = '22222222-2222-4222-8222-222222222201';
    $manifestId = '22222222-2222-4222-8222-222222222202';
    exportLimitJob($jobId, $hash);

    $sealed = exportLimitRunner(records: 1, pages: 2, limits: new TransferLimits(batchRows: 1))->seal(
        scope: exportLimitScope($hash),
        jobId: $jobId,
        draft: new ExportManifest($manifestId, 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    );

    expect($sealed->partsHash)->not->toBe(str_repeat('0', 64))
        ->and(DB::table('corex_export_parts')->count())->toBe(2);
});

it('enforces the byte cap independently of the part count', function (): void {
    $hash = str_repeat('c', 64);
    $jobId = '33333333-3333-4333-8333-333333333301';
    $manifestId = '33333333-3333-4333-8333-333333333302';
    exportLimitJob($jobId, $hash);

    expect(fn () => exportLimitRunner(records: 1, limits: new TransferLimits(maxFileBytes: 1, maxRecordBytes: 1))->seal(
        scope: exportLimitScope($hash),
        jobId: $jobId,
        draft: new ExportManifest($manifestId, 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    ))->toThrow(ExportUnavailable::class)
        ->and(DB::table('corex_export_manifests')->where('state', 'sealed')->count())->toBe(0);
});

it('restarts an interrupted capture under the original manifest identity', function (): void {
    $hash = str_repeat('d', 64);
    $jobId = '44444444-4444-4444-8444-444444444401';
    $interrupted = '44444444-4444-4444-8444-444444444402';
    exportLimitJob($jobId, $hash);
    $signal = new class implements CancellationSignal
    {
        public function isCancelled(): bool
        {
            return true;
        }
    };

    expect(fn () => exportLimitRunner(records: 1, cancellation: $signal)->seal(
        scope: exportLimitScope($hash),
        jobId: $jobId,
        draft: new ExportManifest($interrupted, 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    ))->toThrow(ExportUnavailable::class);

    $sealed = exportLimitRunner(records: 1)->seal(
        scope: exportLimitScope($hash),
        jobId: $jobId,
        draft: new ExportManifest($interrupted, 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    );

    expect($sealed->id)->toBe($interrupted)
        ->and(DB::table('corex_export_manifests')->where('id', $interrupted)->value('state'))->toBe('sealed');
});

it('checks runtime and memory before each page and rolls the capture back', function (): void {
    $hash = str_repeat('e', 64);
    $jobId = '55555555-5555-4555-8555-555555555501';
    $manifestId = '55555555-5555-4555-8555-555555555502';
    exportLimitJob($jobId, $hash);
    $guard = ExecutionGuard::injected(
        limits: new TransferLimits(maxMemoryMiB: 1),
        monotonicNanoseconds: static fn (): int => 0,
        memoryBytes: static fn (): int => 3_145_728,
    );

    expect(fn () => exportLimitRunner(records: 1, guard: $guard)->seal(
        scope: exportLimitScope($hash),
        jobId: $jobId,
        draft: new ExportManifest($manifestId, 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    ))->toThrow(TransferLimitReached::class)
        ->and(DB::table('corex_export_manifests')->whereIn('state', ['sealed', 'published'])->count())->toBe(0);
});

function exportLimitJob(string $id, string $hash): void
{
    DB::table('corex_transfer_jobs')->insert([
        'id' => $id, 'source_manifest' => json_encode(['_scope_hash' => SessionIdentity::scopeHash(exportLimitScope($hash))], JSON_THROW_ON_ERROR), 'source_hash' => $hash, 'mapping_snapshot' => '{}', 'mapping_hash' => $hash,
        'schema_hash' => $hash, 'restore_epoch' => $hash, 'mode' => 'export', 'state' => 'running', 'checkpoint_seq' => '0',
        'cursor' => '{"ordinal":"0","boundaryByteOffset":"0"}', 'stats' => '{}', 'claim_epoch' => '1', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function exportLimitScope(string $hash): DataScope
{
    return CallerWorkScopeFixture::forContext(new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true), 'user-1', $hash);
}

function exportLimitRunner(int $records, int $pages = 1, ?TransferLimits $limits = null, ?CancellationSignal $cancellation = null, ?ExecutionGuard $guard = null): DatabaseExportRunner
{
    $limits ??= new TransferLimits;
    $source = new class($records, $pages) implements ExportSource
    {
        private int $served = 0;

        public function __construct(private readonly int $records, private readonly int $pages) {}

        public function page(DataScope $scope, ExportManifest $manifest, ExportCursor $after, TransferLimits $limits): ExportPage
        {
            $this->served++;
            $rows = [];

            for ($index = 0; $index < $this->records; $index++) {
                $rows[] = new CanonicalRecord('contact', 'archive', 'doc-'.$index, 'rev-1', null, ['name' => 'Ada'], $manifest->schemaHash);
            }

            $complete = $this->served >= $this->pages;

            return new ExportPage($rows, $complete ? null : new ExportCursor((string) $this->served, '0', $manifest->schemaHash), $complete);
        }
    };
    $storage = new readonly class implements TenantStorage
    {
        private Filesystem $disk;

        public function __construct()
        {
            $this->disk = Storage::build(['driver' => 'local', 'root' => sys_get_temp_dir().'/corex-export-'.bin2hex(random_bytes(8))]);
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
        $limits,
        new CanonicalExportCodec,
        new CallableDataScopeAuthorizer(static fn (): bool => true),
        cancellation: $cancellation ?? new class implements CancellationSignal
        {
            public function isCancelled(): bool
            {
                return false;
            }
        },
        guard: $guard,
    );
}
