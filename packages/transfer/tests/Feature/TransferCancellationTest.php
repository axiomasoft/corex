<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\Time\Laravel\SystemClock;
use CoreX\Transfer\Contracts\CancellationSignal;
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
use CoreX\Transfer\Tests\PgTestCase;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('returns cancelled at the read boundary without the next target call', function (): void {
    $hash = str_repeat('a', 64);
    $jobId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
    insertCancelJob(id: $jobId, hash: $hash, mode: 'import');
    $calls = (object) ['target' => 0, 'read' => 0];

    $receipt = cancelRunner(hash: $hash, calls: $calls, cancelled: true)
        ->step(scope: cancelScope($hash), transfer: new TransferRef($jobId), token: cancelToken($hash));

    expect($receipt->state)->toBe('cancelled')
        ->and($receipt->checkpointSequence)->toBe('0')
        ->and($calls->target)->toBe(0)
        ->and($calls->read)->toBe(0)
        ->and(DB::table('corex_transfer_jobs')->value('state'))->toBe('cancelled')
        ->and(DB::table('corex_transfer_jobs')->value('checkpoint_seq'))->toBe('0')
        ->and(DB::table('corex_transfer_rows')->count())->toBe(0);
});

it('does not write checkpoint, source, or target state when a dry run is cancelled', function (): void {
    $hash = str_repeat('b', 64);
    $jobId = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb1';
    insertCancelJob(id: $jobId, hash: $hash, mode: 'dryRun');
    $calls = (object) ['target' => 0, 'read' => 0];

    $receipt = cancelRunner(hash: $hash, calls: $calls, cancelled: true)
        ->step(scope: cancelScope($hash), transfer: new TransferRef($jobId), token: cancelToken($hash));

    expect($receipt->state)->toBe('cancelled')
        ->and($calls->target)->toBe(0)
        ->and(DB::table('corex_transfer_jobs')->value('state'))->toBe('running')
        ->and(DB::table('corex_transfer_jobs')->value('checkpoint_seq'))->toBe('0')
        ->and(DB::table('corex_transfer_rows')->count())->toBe(0);
});

function insertCancelJob(string $id, string $hash, string $mode): void
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

function cancelScope(string $hash): DataScope
{
    return DataScope::fromTenantContext(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: 'pgsql:corex_test',
        actorId: 'user-1',
        restoreEpoch: $hash,
    );
}

function cancelToken(string $hash): StepToken
{
    return new StepToken('0', '1', $hash, $hash, $hash, $hash);
}

function cancelRunner(string $hash, object $calls, bool $cancelled): SqlTransferExecutor
{
    $manifest = new SourceManifest('object://imports/cancel', $hash, 42, 'csv', '1', 'UTF-8', ',', '"', '', $hash);
    $source = new readonly class($manifest, $calls) implements TransferSource
    {
        public function __construct(private SourceManifest $manifest, private object $calls) {}

        public function describe(DataScope $scope): SourceManifest
        {
            return $this->manifest;
        }

        public function read(SourceManifest $source, SourceCursor $after, TransferLimits $limits): RowBatch
        {
            $this->calls->read++;

            return new RowBatch([new SourceRow('1', ['name' => 'Ada'], $source->sha256)], new SourceCursor('1', '42', $source->sha256), false);
        }
    };
    $record = new CanonicalRecord('contact', 'archive', 'doc-1', 'rev-1', null, ['name' => 'Ada'], $hash);
    $mapper = new readonly class($record) implements RecordMapper
    {
        public function __construct(private CanonicalRecord $record) {}

        public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult
        {
            return new MappingResult('mapped', $this->record, null);
        }
    };
    $target = new readonly class($calls) implements TransferSink
    {
        public function __construct(private object $calls) {}

        public function preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision
        {
            $this->calls->target++;

            return new ImportDecision('apply', null);
        }

        public function apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult
        {
            $this->calls->target++;

            return new TargetResult('applied', new RecordRef('contact', 'local-1'), 'v1', null, null);
        }

        public function lookup(DataScope $scope, WriteToken $token): TargetResult
        {
            $this->calls->target++;

            return new TargetResult('noop', new RecordRef('contact', 'local-1'), 'v1', null, null);
        }
    };
    $signal = new readonly class($cancelled) implements CancellationSignal
    {
        public function __construct(private bool $cancelled) {}

        public function isCancelled(): bool
        {
            return $this->cancelled;
        }
    };

    return new SqlTransferExecutor(
        DB::connection(),
        $source,
        $mapper,
        $target,
        new MappingSnapshot('1', $hash, [], $hash),
        new ConflictRule('create-only'),
        new TransferLimits,
        new CallableDataScopeAuthorizer(static fn (): bool => true),
        new SystemClock,
        cancellation: $signal,
    );
}
