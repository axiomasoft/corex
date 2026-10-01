<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\Transfer\Contracts\RecordMapper;
use CoreX\Transfer\Contracts\TransferSink;
use CoreX\Transfer\Contracts\TransferSource;
use CoreX\Transfer\Data\CanonicalRecord;
use CoreX\Transfer\Data\ConflictRule;
use CoreX\Transfer\Data\ImportDecision;
use CoreX\Transfer\Data\MappingResult;
use CoreX\Transfer\Data\MappingSnapshot;
use CoreX\Transfer\Data\RowBatch;
use CoreX\Transfer\Data\SourceCursor;
use CoreX\Transfer\Data\SourceManifest;
use CoreX\Transfer\Data\SourceRow;
use CoreX\Transfer\Data\StepToken;
use CoreX\Transfer\Data\TargetResult;
use CoreX\Transfer\Data\TransferLimits;
use CoreX\Transfer\Data\TransferRef;
use CoreX\Transfer\Internal\Transfer\SqlTransferExecutor;
use CoreX\Transfer\Internal\Transfer\TransferUnavailable;
use CoreX\Transfer\Tests\PgTestCase;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('commits local result and fenced checkpoint in one PostgreSQL transaction', function (): void {
    $hash = str_repeat('a', 64);
    $jobId = '11111111-1111-4111-8111-111111111111';
    $manifest = new SourceManifest('object://imports/1', $hash, 42, 'csv', '1', 'UTF-8', ',', '"', '', $hash);
    insertJob(id: $jobId, hash: $hash, mode: 'import');

    $receipt = runner(manifest: $manifest, record: record($hash))
        ->step(scope: scope($hash), transfer: new TransferRef($jobId), token: new StepToken('0', '1', $hash, $hash, $hash, $hash));

    expect($receipt->state)->toBe('completed')
        ->and($receipt->checkpointSequence)->toBe('1')
        ->and(DB::table('corex_transfer_rows')->count())->toBe(1)
        ->and(DB::table('corex_transfer_jobs')->value('checkpoint_seq'))->toBe('1');
});

it('makes dry runs observational: no row or checkpoint is written', function (): void {
    $hash = str_repeat('b', 64);
    $jobId = '22222222-2222-4222-8222-222222222222';
    $manifest = new SourceManifest('object://imports/2', $hash, 42, 'ndjson', '1', 'UTF-8', ',', '"', '', $hash);
    insertJob(id: $jobId, hash: $hash, mode: 'dryRun');

    $receipt = runner(manifest: $manifest, record: record($hash))
        ->step(scope: scope($hash), transfer: new TransferRef($jobId), token: new StepToken('0', '1', $hash, $hash, $hash, $hash));

    expect($receipt->checkpointSequence)->toBe('1')
        ->and(DB::table('corex_transfer_rows')->count())->toBe(0)
        ->and(DB::table('corex_transfer_jobs')->value('checkpoint_seq'))->toBe('0');
});

it('deduplicates an identical external record across independent jobs before target apply', function (): void {
    $hash = str_repeat('c', 64);
    $manifest = new SourceManifest('object://imports/3', $hash, 42, 'csv', '1', 'UTF-8', ',', '"', '', $hash);
    $counter = (object) ['applied' => 0];
    $first = '33333333-3333-4333-8333-333333333333';
    $second = '44444444-4444-4444-8444-444444444444';
    insertJob(id: $first, hash: $hash, mode: 'import');
    insertJob(id: $second, hash: $hash, mode: 'import');

    runner(manifest: $manifest, record: record($hash), counter: $counter)->step(scope: scope($hash), transfer: new TransferRef($first), token: new StepToken('0', '1', $hash, $hash, $hash, $hash));
    runner(manifest: $manifest, record: record($hash), counter: $counter)->step(scope: scope($hash), transfer: new TransferRef($second), token: new StepToken('0', '1', $hash, $hash, $hash, $hash));

    expect($counter->applied)->toBe(1)->and(DB::table('corex_transfer_refs')->count())->toBe(1);
});

it('rolls back only the failed row savepoint and continues the batch', function (): void {
    $hash = str_repeat('d', 64);
    $jobId = '55555555-5555-4555-8555-555555555555';
    $manifest = new SourceManifest('object://imports/4', $hash, 84, 'csv', '1', 'UTF-8', ',', '"', '', $hash);
    insertJob(id: $jobId, hash: $hash, mode: 'import');
    $source = new class($manifest) implements TransferSource
    {
        public function __construct(private SourceManifest $manifest) {}

        public function describe(DataScope $scope): SourceManifest
        {
            return $this->manifest;
        }

        public function read(SourceManifest $source, SourceCursor $after, TransferLimits $limits): RowBatch
        {
            return new RowBatch([new SourceRow('1', ['id' => 'one'], $source->sha256), new SourceRow('2', ['id' => 'two'], $source->sha256)], new SourceCursor('2', '84', $source->sha256), false);
        }
    };
    $mapper = new class($hash) implements RecordMapper
    {
        public function __construct(private string $hash) {}

        public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult
        {
            return new MappingResult('mapped', new CanonicalRecord('contact', 'archive', $row->ordinal === '1' ? 'fail' : 'pass', 'rev-1', null, ['id' => $row->ordinal], $this->hash), null);
        }
    };
    $target = new class implements TransferSink
    {
        public function preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision
        {
            return new ImportDecision('apply', null);
        }

        public function apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult
        {
            if ($record->externalId === 'fail') {
                throw new RuntimeException('simulated target fault');
            }

            return new TargetResult('applied', new RecordRef('contact', 'local-'.$record->externalId), 'v1', null, null);
        }

        public function lookup(DataScope $scope, WriteToken $token): TargetResult
        {
            return new TargetResult('noop', new RecordRef('contact', 'local-pass'), 'v1', null, null);
        }
    };
    $runner = new SqlTransferExecutor(
        DB::connection(),
        $source,
        $mapper,
        $target,
        new MappingSnapshot('1', $hash, [], $hash),
        new ConflictRule('create-only'),
        new TransferLimits,
        new CallableDataScopeAuthorizer(static fn (): bool => true),
    );

    $receipt = $runner->step(scope: scope($hash), transfer: new TransferRef($jobId), token: new StepToken('0', '1', $hash, $hash, $hash, $hash));

    expect($receipt->state)->toBe('completed')
        ->and(DB::table('corex_transfer_rows')->where('result', 'invalid')->count())->toBe(1)
        ->and(DB::table('corex_transfer_rows')->where('result', 'applied')->count())->toBe(1)
        ->and(DB::table('corex_transfer_refs')->count())->toBe(1);
});

it('quarantines an unknown target acknowledgement without advancing the cursor', function (): void {
    $hash = str_repeat('e', 64);
    $jobId = '66666666-6666-4666-8666-666666666666';
    $manifest = new SourceManifest('object://imports/5', $hash, 42, 'csv', '1', 'UTF-8', ',', '"', '', $hash);
    insertJob(id: $jobId, hash: $hash, mode: 'import');

    $receipt = runner(manifest: $manifest, record: record($hash), outcome: 'unknown')
        ->step(scope: scope($hash), transfer: new TransferRef($jobId), token: new StepToken('0', '1', $hash, $hash, $hash, $hash));

    expect($receipt->state)->toBe('unknown')
        ->and($receipt->cursor->ordinal)->toBe('0')
        ->and(DB::table('corex_transfer_rows')->value('result'))->toBe('unknown')
        ->and(DB::table('corex_transfer_refs')->value('state'))->toBe('unknown');
});

it('refuses source drift and current-policy denial before a target mutation', function (): void {
    $hash = str_repeat('f', 64);
    $other = str_repeat('0', 64);
    $jobId = '77777777-7777-4777-8777-777777777777';
    insertJob(id: $jobId, hash: $hash, mode: 'import');

    expect(fn (): mixed => runner(manifest: new SourceManifest('object://imports/6', $other, 42, 'csv', '1', 'UTF-8', ',', '"', '', $hash), record: record($hash))
        ->step(scope: scope($hash), transfer: new TransferRef($jobId), token: new StepToken('0', '1', $hash, $hash, $hash, $hash)))
        ->toThrow(TransferUnavailable::class)
        ->and(DB::table('corex_transfer_rows')->count())->toBe(0)
        ->and(fn (): mixed => runner(manifest: new SourceManifest('object://imports/7', $hash, 42, 'csv', '1', 'UTF-8', ',', '"', '', $hash), record: record($hash), allowed: false)
            ->step(scope: scope($hash), transfer: new TransferRef($jobId), token: new StepToken('0', '1', $hash, $hash, $hash, $hash)))
        ->toThrow(TransferUnavailable::class)
        ->and(DB::table('corex_transfer_rows')->count())->toBe(0);
});

function insertJob(string $id, string $hash, string $mode): void
{
    DB::table('corex_transfer_jobs')->insert([
        'id' => $id,
        'source_manifest' => '{}',
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

function scope(string $hash): DataScope
{
    return DataScope::fromTenantContext(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: 'pgsql:corex_test',
        actorId: 'user-1',
        restoreEpoch: $hash,
    );
}

function record(string $hash): CanonicalRecord
{
    return new CanonicalRecord('contact', 'archive', 'doc-1', 'rev-1', null, ['name' => 'Ada'], $hash);
}

function runner(SourceManifest $manifest, CanonicalRecord $record, ?stdClass $counter = null, string $outcome = 'applied', bool $allowed = true): SqlTransferExecutor
{
    $source = new class($manifest) implements TransferSource
    {
        public function __construct(private SourceManifest $manifest) {}

        public function describe(DataScope $scope): SourceManifest
        {
            return $this->manifest;
        }

        public function read(SourceManifest $source, SourceCursor $after, TransferLimits $limits): RowBatch
        {
            return new RowBatch([new SourceRow('1', ['name' => 'Ada'], $source->sha256)], new SourceCursor('1', '42', $source->sha256), false);
        }
    };
    $mapper = new class($record) implements RecordMapper
    {
        public function __construct(private CanonicalRecord $record) {}

        public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult
        {
            return new MappingResult('mapped', $this->record, null);
        }
    };
    $target = new class($counter ?? (object) ['applied' => 0], $outcome) implements TransferSink
    {
        public function __construct(private stdClass $counter, private string $outcome) {}

        public function preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision
        {
            return new ImportDecision('apply', null);
        }

        public function apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult
        {
            $this->counter->applied++;

            if ($this->outcome === 'unknown') {
                return new TargetResult('unknown', null, null, null, 'timeout');
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
        new TransferLimits,
        new CallableDataScopeAuthorizer(static fn (): bool => $allowed),
    );
}
