<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\ImportExport\Contracts\RecordMapper;
use CoreX\ImportExport\Contracts\TransferSink;
use CoreX\ImportExport\Contracts\TransferSource;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\ConflictRule;
use CoreX\ImportExport\Data\ImportDecision;
use CoreX\ImportExport\Data\MappingResult;
use CoreX\ImportExport\Data\MappingSnapshot;
use CoreX\ImportExport\Data\RowBatch;
use CoreX\ImportExport\Data\SourceCursor;
use CoreX\ImportExport\Data\SourceManifest;
use CoreX\ImportExport\Data\SourceRow;
use CoreX\ImportExport\Data\StepToken;
use CoreX\ImportExport\Data\TargetResult;
use CoreX\ImportExport\Data\TransferLimits;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Exceptions\ImportUnavailable;
use CoreX\ImportExport\Internal\SessionIdentity;
use CoreX\ImportExport\Internal\Transfer\ExecutionGuard;
use CoreX\ImportExport\Internal\Transfer\SqlTransferExecutor;
use CoreX\ImportExport\Internal\Transfer\TransferLimitReached;
use CoreX\ImportExport\Tests\PgTestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use CoreX\Time\Laravel\SystemClock;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('rejects an oversized source batch before any target write', function (): void {
    $hash = str_repeat('c', 64);
    $jobId = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc1';
    insertLimitJob(id: $jobId, hash: $hash, mode: 'import');
    $calls = (object) ['target' => 0];
    $manifest = new SourceManifest('object://imports/oversize', $hash, 42, 'csv', '1', 'UTF-8', ',', '"', '', $hash);

    expect(fn () => limitRunner(manifest: $manifest, calls: $calls, rows: 2, limits: new TransferLimits(batchRows: 1))
        ->step(scope: limitScope($hash), transfer: new TransferRef($jobId), token: limitToken($hash)))
        ->toThrow(ImportUnavailable::class)
        ->and($calls->target)->toBe(0)
        ->and(DB::table('corex_transfer_rows')->count())->toBe(0)
        ->and(DB::table('corex_transfer_jobs')->value('checkpoint_seq'))->toBe('0');
});

it('rolls the batch back when a runtime limit is reached and does not record a row failure', function (): void {
    $hash = str_repeat('d', 64);
    $jobId = 'dddddddd-dddd-4ddd-8ddd-ddddddddddd1';
    insertLimitJob(id: $jobId, hash: $hash, mode: 'import');
    $calls = (object) ['target' => 0];
    $ticks = [0, 0, 0, 31_000_000_000];
    $index = 0;
    $guard = ExecutionGuard::injected(
        limits: new TransferLimits(maxRuntimeSeconds: 30),
        monotonicNanoseconds: function () use (&$ticks, &$index): int {
            $value = $ticks[$index] ?? 31_000_000_000;
            $index++;

            return $value;
        },
        memoryBytes: static fn (): int => 1024,
    );
    $manifest = new SourceManifest('object://imports/runtime', $hash, 42, 'csv', '1', 'UTF-8', ',', '"', '', $hash);

    $receipt = limitRunner(manifest: $manifest, calls: $calls, rows: 1, limits: new TransferLimits, guard: $guard)
        ->step(scope: limitScope($hash), transfer: new TransferRef($jobId), token: limitToken($hash));

    expect($receipt->state)->toBe('running')
        ->and($receipt->errorCode)->toBe('runtimeLimitReached')
        ->and($receipt->checkpointSequence)->toBe('0')
        ->and($receipt->cursor->ordinal)->toBe('0')
        ->and($calls->target)->toBe(0)
        ->and(DB::table('corex_transfer_jobs')->value('checkpoint_seq'))->toBe('0')
        ->and(DB::table('corex_transfer_rows')->count())->toBe(0);
});

it('does not let a limit raised inside the row write become a swallowed row failure', function (): void {
    $hash = str_repeat('e', 64);
    $jobId = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1';
    insertLimitJob(id: $jobId, hash: $hash, mode: 'import');
    $cursor = new SourceCursor('0', '0', $hash);
    $manifest = new SourceManifest('object://imports/row-limit', $hash, 42, 'csv', '1', 'UTF-8', ',', '"', '', $hash);

    $receipt = limitRunner(manifest: $manifest, calls: (object) ['target' => 0], rows: 1, limits: new TransferLimits, raise: new TransferLimitReached('memoryLimitReached', $cursor, '0', []))
        ->step(scope: limitScope($hash), transfer: new TransferRef($jobId), token: limitToken($hash));

    expect($receipt->errorCode)->toBe('memoryLimitReached')
        ->and($receipt->checkpointSequence)->toBe('0')
        ->and(DB::table('corex_transfer_rows')->where('result', 'invalid')->count())->toBe(0)
        ->and(DB::table('corex_transfer_jobs')->value('checkpoint_seq'))->toBe('0');
});

it('writes nothing when a dry run reaches a memory limit', function (): void {
    $hash = str_repeat('f', 64);
    $jobId = 'ffffffff-ffff-4fff-8fff-fffffffffff1';
    insertLimitJob(id: $jobId, hash: $hash, mode: 'dryRun');
    $guard = ExecutionGuard::injected(
        limits: new TransferLimits(maxMemoryMiB: 1),
        monotonicNanoseconds: static fn (): int => 0,
        memoryBytes: static fn (): int => 2_097_152,
    );
    $manifest = new SourceManifest('object://imports/dry-limit', $hash, 42, 'ndjson', '1', 'UTF-8', ',', '"', '', $hash);

    $receipt = limitRunner(manifest: $manifest, calls: (object) ['target' => 0], rows: 1, limits: new TransferLimits, guard: $guard)
        ->step(scope: limitScope($hash), transfer: new TransferRef($jobId), token: limitToken($hash));

    expect($receipt->state)->toBe('running')
        ->and($receipt->errorCode)->toBe('memoryLimitReached')
        ->and(DB::table('corex_transfer_jobs')->value('checkpoint_seq'))->toBe('0')
        ->and(DB::table('corex_transfer_jobs')->value('state'))->toBe('running')
        ->and(DB::table('corex_transfer_rows')->count())->toBe(0);
});

function insertLimitJob(string $id, string $hash, string $mode): void
{
    DB::table('corex_transfer_jobs')->insert([
        'id' => $id,
        'source_manifest' => json_encode(['_scope_hash' => SessionIdentity::scopeHash(limitScope($hash))], JSON_THROW_ON_ERROR),
        'source_hash' => $hash,
        'mapping_snapshot' => '{}',
        'mapping_hash' => $hash,
        'schema_hash' => $hash,
        'restore_epoch' => $hash,
        'mode' => $mode,
        'state' => 'running',
        'checkpoint_seq' => '0',
        'cursor' => '{"ordinal":"0","boundaryByteOffset":"0"}',
        'stats' => '{}',
        'claim_epoch' => '1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function limitScope(string $hash): DataScope
{
    return CallerWorkScopeFixture::forContext(new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true), 'user-1', $hash);
}

function limitToken(string $hash): StepToken
{
    return new StepToken('0', '1', $hash, $hash, $hash, $hash);
}

function limitRunner(SourceManifest $manifest, object $calls, int $rows, TransferLimits $limits, ?ExecutionGuard $guard = null, ?TransferLimitReached $raise = null): SqlTransferExecutor
{
    $source = new readonly class($manifest, $rows) implements TransferSource
    {
        public function __construct(private SourceManifest $manifest, private int $rows) {}

        public function describe(DataScope $scope): SourceManifest
        {
            return $this->manifest;
        }

        public function read(SourceManifest $source, SourceCursor $after, TransferLimits $limits): RowBatch
        {
            $batch = [];

            for ($index = 1; $index <= $this->rows; $index++) {
                $batch[] = new SourceRow((string) $index, ['name' => 'Ada'], $source->sha256);
            }

            return new RowBatch($batch, new SourceCursor((string) $this->rows, '42', $source->sha256), false);
        }
    };
    $record = new CanonicalRecord('contact', 'archive', 'doc-1', 'rev-1', null, ['name' => 'Ada'], $manifest->sha256);
    $mapper = new readonly class($record) implements RecordMapper
    {
        public function __construct(private CanonicalRecord $record) {}

        public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult
        {
            return new MappingResult('mapped', $this->record, null);
        }
    };
    $target = new readonly class($calls, $raise) implements TransferSink
    {
        public function __construct(private object $calls, private ?TransferLimitReached $raise) {}

        public function preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision
        {
            $this->calls->target++;

            return new ImportDecision('apply', null);
        }

        public function apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult
        {
            $this->calls->target++;

            if ($this->raise !== null) {
                throw $this->raise;
            }

            return new TargetResult('applied', new RecordRef('contact', 'local-1'), 'v1', null, null);
        }

        public function lookup(DataScope $scope, WriteToken $token): TargetResult
        {
            return new TargetResult('noop', new RecordRef('contact', 'local-1'), 'v1', null, null);
        }
    };

    return new SqlTransferExecutor(
        DB::connection(),
        $source,
        $mapper,
        $target,
        new MappingSnapshot('1', $manifest->schemaHash, [], $manifest->sha256),
        new ConflictRule('create-only'),
        $limits,
        new CallableDataScopeAuthorizer(static fn (): bool => true),
        new SystemClock,
        guard: $guard,
    );
}
