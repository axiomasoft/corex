<?php

declare(strict_types=1);

use CoreX\Contracts\TenantStorage;
use CoreX\Storage\ExportArchiveRef;
use CoreX\Storage\StorageCapabilities;
use CoreX\Support\Data\CallableDataScopeAuthorizer;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use CoreX\Transfer\Contracts\CancellationSignal;
use CoreX\Transfer\Contracts\ExportSource;
use CoreX\Transfer\Data\CanonicalRecord;
use CoreX\Transfer\Data\Export\ExportCursor;
use CoreX\Transfer\Data\Export\ExportManifest;
use CoreX\Transfer\Data\Export\ExportPage;
use CoreX\Transfer\Data\TransferLimits;
use CoreX\Transfer\Internal\Export\CanonicalExportCodec;
use CoreX\Transfer\Internal\Export\DatabaseExportRunner;
use CoreX\Transfer\Internal\Export\ExportUnavailable;
use CoreX\Transfer\Internal\Transfer\ExecutionGuard;
use CoreX\Transfer\Internal\Transfer\TransferLimitReached;
use CoreX\Transfer\Tests\PgTestCase;
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
        draft: new ExportManifest($manifestId, 'coherent', $hash, $hash, [], str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    ))->toThrow(ExportUnavailable::class)
        ->and(DB::table('corex_export_manifests')->count())->toBe(0);
});

it('seals several parts when each page stays inside the row cap', function (): void {
    $hash = str_repeat('b', 64);
    $jobId = '22222222-2222-4222-8222-222222222201';
    $manifestId = '22222222-2222-4222-8222-222222222202';
    exportLimitJob($jobId, $hash);

    $sealed = exportLimitRunner(records: 1, pages: 2, limits: new TransferLimits(batchRows: 1))->seal(
        scope: exportLimitScope($hash),
        jobId: $jobId,
        draft: new ExportManifest($manifestId, 'coherent', $hash, $hash, [], str_repeat('0', 64)),
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
        draft: new ExportManifest($manifestId, 'coherent', $hash, $hash, [], str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    ))->toThrow(ExportUnavailable::class)
        ->and(DB::table('corex_export_manifests')->where('state', 'sealed')->count())->toBe(0);
});

it('drops an interrupted capture so a new manifest can be sealed', function (): void {
    $hash = str_repeat('d', 64);
    $jobId = '44444444-4444-4444-8444-444444444401';
    $interrupted = '44444444-4444-4444-8444-444444444402';
    $fresh = '44444444-4444-4444-8444-444444444403';
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
        draft: new ExportManifest($interrupted, 'coherent', $hash, $hash, [], str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    ))->toThrow(ExportUnavailable::class);

    $sealed = exportLimitRunner(records: 1)->seal(
        scope: exportLimitScope($hash),
        jobId: $jobId,
        draft: new ExportManifest($fresh, 'coherent', $hash, $hash, [], str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    );

    expect(DB::table('corex_export_manifests')->where('id', $interrupted)->count())->toBe(0)
        ->and($sealed->id)->toBe($fresh)
        ->and(DB::table('corex_export_manifests')->where('id', $fresh)->value('state'))->toBe('sealed');
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
        draft: new ExportManifest($manifestId, 'coherent', $hash, $hash, [], str_repeat('0', 64)),
        after: new ExportCursor('0', '0', $hash),
    ))->toThrow(TransferLimitReached::class)
        ->and(DB::table('corex_export_manifests')->count())->toBe(0);
});

function exportLimitJob(string $id, string $hash): void
{
    DB::table('corex_transfer_jobs')->insert([
        'id' => $id, 'source_manifest' => '{}', 'source_hash' => $hash, 'mapping_snapshot' => '{}', 'mapping_hash' => $hash,
        'schema_hash' => $hash, 'restore_epoch' => $hash, 'mode' => 'export', 'state' => 'running', 'checkpoint_seq' => '0',
        'cursor' => '{"ordinal":"0","boundaryByteOffset":"0"}', 'stats' => '{}', 'claim_epoch' => '1', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function exportLimitScope(string $hash): DataScope
{
    return DataScope::fromTenantContext(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: 'pgsql:corex_test',
        actorId: 'user-1',
        restoreEpoch: $hash,
    );
}

function exportLimitRunner(int $records, int $pages = 1, ?TransferLimits $limits = null, ?CancellationSignal $cancellation = null, ?ExecutionGuard $guard = null): DatabaseExportRunner
{
    $limits ??= new TransferLimits;
    $source = new class($records, $pages) implements ExportSource
    {
        private int $served = 0;

        public function __construct(private int $records, private int $pages) {}

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
    $storage = new class implements TenantStorage
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
