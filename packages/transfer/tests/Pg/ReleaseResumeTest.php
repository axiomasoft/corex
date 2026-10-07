<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Internal\CallableDataScopeAuthorizer;
use CoreX\Time\Laravel\SystemClock;
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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('resumes the persisted checkpoint in a new executor without replaying committed rows', function (): void {
    $hash = hash('sha256', 'resume-source');
    $id = '91300000-0000-4000-8000-000000000001';
    Schema::create('p0913_target_contacts', static function (Blueprint $table): void {
        $table->string('external_id')->primary();
        $table->string('name');
    });
    DB::table('corex_transfer_jobs')->insert([
        'id' => $id, 'source_manifest' => '{}', 'source_hash' => $hash,
        'mapping_snapshot' => '{}', 'mapping_hash' => $hash, 'schema_hash' => $hash,
        'restore_epoch' => $hash, 'mode' => 'import', 'state' => 'running',
        'checkpoint_seq' => '0', 'cursor' => '{"ordinal":"0","boundaryByteOffset":"0"}',
        'stats' => '{}', 'claim_epoch' => '1', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $scope = DataScope::fromTenantContext(
        context: new TenantContext(account: new AccountRef(id: 'account-1', slug: 'account-1', status: 'active', features: [], limits: []), boxed: true),
        connectionIdentity: 'pgsql:'.DB::connection()->getDatabaseName(), actorId: 'user-1', restoreEpoch: $hash,
    );
    $manifest = new SourceManifest(immutableRef: 'object://imports/resume', sha256: $hash, length: 84, format: 'csv', parserVersion: '1', encoding: 'UTF-8', delimiter: ',', enclosure: '"', escape: '', schemaHash: $hash);
    $firstSource = new TransferResumeSourceFixture($manifest);
    $firstSink = new TransferResumeSinkFixture;
    $firstExecutor = transferResumeExecutor(source: $firstSource, sink: $firstSink, hash: $hash);
    $first = $firstExecutor->step(scope: $scope, transfer: new TransferRef(id: $id), token: new StepToken(checkpointSequence: '0', epoch: '1', sourceHash: $hash, mappingHash: $hash, schemaHash: $hash, restoreEpoch: $hash));

    expect($first->state)->toBe('running')
        ->and($first->checkpointSequence)->toBe('1')
        ->and($first->cursor->ordinal)->toBe('1')
        ->and($first->cursor->boundaryByteOffset)->toBe('42')
        ->and($firstSink->applied)->toBe(['doc-1'])
        ->and(DB::table('p0913_target_contacts')->pluck('name')->all())->toBe(['Ada']);
    unset($firstExecutor);

    $persisted = DB::table('corex_transfer_jobs')->where('id', $id)->first();
    expect($persisted)->not->toBeNull();
    assert($persisted !== null);
    expect((string) $persisted->checkpoint_seq)->toBe('1')
        ->and(json_decode($persisted->cursor, true, 512, JSON_THROW_ON_ERROR))->toBe(['ordinal' => '1', 'boundaryByteOffset' => '42']);
    $token = new StepToken(checkpointSequence: (string) $persisted->checkpoint_seq, epoch: (string) $persisted->claim_epoch, sourceHash: $hash, mappingHash: $hash, schemaHash: $hash, restoreEpoch: $hash);
    $secondSource = new TransferResumeSourceFixture($manifest);
    $secondSink = new TransferResumeSinkFixture;
    $secondExecutor = transferResumeExecutor(source: $secondSource, sink: $secondSink, hash: $hash);

    expect(fn () => $secondExecutor->step(scope: $scope, transfer: new TransferRef(id: $id), token: new StepToken(checkpointSequence: '0', epoch: '1', sourceHash: $hash, mappingHash: $hash, schemaHash: $hash, restoreEpoch: $hash)))
        ->toThrow(TransferUnavailable::class, 'Transfer checkpoint token does not match the current job.');
    $changedSource = new TransferResumeSourceFixture(new SourceManifest(immutableRef: 'object://imports/resume', sha256: hash('sha256', 'changed-source'), length: 84, format: 'csv', parserVersion: '1', encoding: 'UTF-8', delimiter: ',', enclosure: '"', escape: '', schemaHash: $hash));
    expect(fn () => transferResumeExecutor(source: $changedSource, sink: $secondSink, hash: $hash)->step(scope: $scope, transfer: new TransferRef(id: $id), token: $token))
        ->toThrow(TransferUnavailable::class, 'Source changed; transfer resume is refused.')
        ->and($secondSource->reads)->toBe([])
        ->and($changedSource->reads)->toBe([])
        ->and($secondSink->applied)->toBe([])
        ->and((string) DB::table('corex_transfer_jobs')->where('id', $id)->value('checkpoint_seq'))->toBe('1')
        ->and(DB::table('p0913_target_contacts')->count())->toBe(1);

    $second = $secondExecutor->step(scope: $scope, transfer: new TransferRef(id: $id), token: $token);
    expect($secondSource->reads)->toBe([['ordinal' => '1', 'offset' => '42', 'hash' => $hash]])
        ->and($secondSink->applied)->toBe(['doc-2'])
        ->and($second->state)->toBe('completed')
        ->and($second->checkpointSequence)->toBe('2')
        ->and($second->cursor->ordinal)->toBe('2')
        ->and($second->cursor->boundaryByteOffset)->toBe('84')
        ->and($second->stats)->toBe(['applied' => 2])
        ->and($second->gaps)->toBe([])
        ->and($second->errorCode)->toBeNull()
        ->and((string) DB::table('corex_transfer_jobs')->where('id', $id)->value('checkpoint_seq'))->toBe('2')
        ->and(DB::table('corex_transfer_jobs')->where('id', $id)->value('state'))->toBe('completed')
        ->and(json_decode(DB::table('corex_transfer_jobs')->where('id', $id)->value('cursor'), true, 512, JSON_THROW_ON_ERROR))->toBe(['ordinal' => '2', 'boundaryByteOffset' => '84'])
        ->and(json_decode(DB::table('corex_transfer_jobs')->where('id', $id)->value('stats'), true, 512, JSON_THROW_ON_ERROR))->toBe(['applied' => 2])
        ->and(DB::table('corex_transfer_rows')->where('job_id', $id)->orderBy('ordinal')->pluck('ordinal')->map(static fn (mixed $ordinal): string => (string) $ordinal)->all())->toBe(['1', '2'])
        ->and(DB::table('corex_transfer_rows')->where('job_id', $id)->pluck('result')->all())->toBe(['applied', 'applied'])
        ->and(DB::table('corex_transfer_refs')->orderBy('external_id')->pluck('external_id')->all())->toBe(['doc-1', 'doc-2'])
        ->and(DB::table('p0913_target_contacts')->orderBy('external_id')->pluck('name', 'external_id')->all())->toBe(['doc-1' => 'Ada', 'doc-2' => 'Bob']);
});

final class TransferResumeSourceFixture implements TransferSource
{
    /** @var list<array{ordinal: string, offset: ?string, hash: string}> */
    public array $reads = [];

    public function __construct(private readonly SourceManifest $manifest) {}

    public function describe(DataScope $scope): SourceManifest
    {
        return $this->manifest;
    }

    public function read(SourceManifest $source, SourceCursor $after, TransferLimits $limits): RowBatch
    {
        $this->reads[] = ['ordinal' => $after->ordinal, 'offset' => $after->boundaryByteOffset, 'hash' => $after->sourceHash];
        $ordinal = match ($after->ordinal) {
            '0' => '1',
            '1' => '2',
            default => throw new LogicException('Completed source must not be read again.'),
        };

        return new RowBatch(
            rows: [new SourceRow(ordinal: $ordinal, values: ['name' => $ordinal === '1' ? 'Ada' : 'Bob'], sourceHash: $source->sha256)],
            next: new SourceCursor(ordinal: $ordinal, boundaryByteOffset: $ordinal === '1' ? '42' : '84', sourceHash: $source->sha256),
            remaining: $ordinal === '1',
        );
    }
}

final class TransferResumeSinkFixture implements TransferSink
{
    /** @var list<string> */
    public array $applied = [];

    public function preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision
    {
        throw new LogicException('This regression performs a real import.');
    }

    public function apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult
    {
        $this->applied[] = $record->externalId;
        DB::table('p0913_target_contacts')->insert(['external_id' => $record->externalId, 'name' => $record->values['name']]);

        return new TargetResult(status: 'applied', target: new RecordRef(entity: 'contact', id: 'local-'.$record->externalId), recordVersion: 'v1', providerTask: null, errorCode: null);
    }

    public function lookup(DataScope $scope, WriteToken $token): TargetResult
    {
        throw new LogicException('Committed rows must not be replayed during resume.');
    }
}

function transferResumeExecutor(TransferSource $source, TransferSink $sink, string $hash): SqlTransferExecutor
{
    $mapper = new class implements RecordMapper
    {
        public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult
        {
            return new MappingResult(status: 'mapped', record: new CanonicalRecord(entity: 'contact', externalSystem: 'archive', externalId: 'doc-'.$row->ordinal, sourceRevision: 'rev-1', sourceUpdatedAt: null, values: $row->values, payloadHash: hash('sha256', $row->ordinal)), errorCode: null);
        }
    };

    return new SqlTransferExecutor(
        connection: DB::connection(), source: $source, mapper: $mapper, target: $sink,
        mapping: new MappingSnapshot(version: '1', schemaHash: $hash, definition: [], sha256: $hash),
        conflictRule: new ConflictRule(kind: 'create-only'), limits: new TransferLimits(batchRows: 1),
        authorizer: new CallableDataScopeAuthorizer(static fn (): bool => true), clock: new SystemClock,
    );
}
