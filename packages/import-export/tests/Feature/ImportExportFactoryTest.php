<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\ExportRunner;
use CoreX\ImportExport\Contracts\ExportSchemaWitness;
use CoreX\ImportExport\Contracts\ExportSource;
use CoreX\ImportExport\Contracts\ImportExportFactory;
use CoreX\ImportExport\Contracts\RecordMapper;
use CoreX\ImportExport\Contracts\TransferSink;
use CoreX\ImportExport\Contracts\TransferSource;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\ConflictRule;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\ImportDecision;
use CoreX\ImportExport\Data\MappingResult;
use CoreX\ImportExport\Data\MappingSnapshot;
use CoreX\ImportExport\Data\RowBatch;
use CoreX\ImportExport\Data\SourceCursor;
use CoreX\ImportExport\Data\SourceManifest;
use CoreX\ImportExport\Data\SourceRow;
use CoreX\ImportExport\Data\TargetResult;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Exceptions\SessionUnavailable;
use CoreX\ImportExport\Tests\FactoryTestCase;
use CoreX\ImportExport\Tests\TransferAdmissionFixture;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Support\Facades\DB;

uses(FactoryTestCase::class);

beforeEach(function (): void {
    $resolver = Mockery::mock(TenantContextResolver::class);
    $resolver->shouldReceive('current')->andReturn(new TenantContext(new AccountRef('account-a', 'a', 'active', [], []), boxed: true));
    app()->instance(TenantContextResolver::class, $resolver);
    CallerWorkScopeFixture::bind(app(), factoryScope());
    TransferAdmissionFixture::bind(app());
    $this->migrateSchemaSets(['axiomasoft/corex-import-export:tenant']);
    $authorizer = Mockery::mock(DataScopeAuthorizer::class);
    $authorizer->shouldReceive('authorize')->andReturn(new DataScopeAccessDecision(true, 'allowed'));
    app()->instance(DataScopeAuthorizer::class, $authorizer);
    $schema = Mockery::mock(ExportSchemaWitness::class);
    $schema->shouldReceive('matches')->andReturnTrue();
    app()->instance(ExportSchemaWitness::class, $schema);
});

function factoryScope(string $actor = 'user-a', string $epoch = 'a'): DataScope
{
    return CallerWorkScopeFixture::scope('account-a', null, $actor, str_repeat($epoch, 64));
}

/** @return array{TransferSource, RecordMapper, TransferSink, MappingSnapshot, ConflictRule} */
function factoryImportPorts(): array
{
    $hash = str_repeat('a', 64);
    $manifest = new SourceManifest('tenant-file', $hash, 20, 'ndjson', '1', 'UTF-8', ',', '"', '', $hash);
    $source = Mockery::mock(TransferSource::class);
    $source->shouldReceive('describe')->andReturn($manifest);
    $source->shouldReceive('read')->andReturnUsing(static function ($manifest, SourceCursor $after) use ($hash): RowBatch {
        $ordinal = (string) ((int) $after->ordinal + 1);

        return new RowBatch([new SourceRow($ordinal, ['title' => 'Note '.$ordinal], $hash)], new SourceCursor($ordinal, $ordinal.'0', $hash), $ordinal === '1');
    });
    $mapper = Mockery::mock(RecordMapper::class);
    $mapper->shouldReceive('map')->andReturnUsing(static fn (SourceRow $row): MappingResult => new MappingResult(
        'mapped', new CanonicalRecord('note', 'sample', $row->ordinal, '1', null, $row->values, $hash), null,
    ));
    $target = Mockery::mock(TransferSink::class);
    $target->shouldReceive('lookup')->andReturn(new TargetResult('absent-with-proof', null, null, null, null));
    $target->shouldReceive('preview')->andReturn(new ImportDecision('apply', null));
    $target->shouldReceive('apply')->andReturnUsing(static fn ($scope, CanonicalRecord $record): TargetResult => new TargetResult(
        'applied', new RecordRef('note', 'local-'.$record->externalId), '1', null, null,
    ));

    return [$source, $mapper, $target, new MappingSnapshot('1', $hash, [], $hash), new ConflictRule('source-newer')];
}

it('creates and resumes a complete import using only exported ports', function (): void {
    $factory = resolve(ImportExportFactory::class);
    $scope = factoryScope();
    $ref = new TransferRef('11111111-1111-4111-8111-111111111111');
    $ports = factoryImportPorts();
    $session = $factory->openImport($scope, $ref, ...$ports);
    $receipt = $session->runner->step($scope, $session->transfer, $session->token);

    expect($receipt->errorCode)->toBeNull()
        ->and($receipt->state)->toBe('running')
        ->and($receipt->checkpointSequence)->toBe('1');

    $resumed = $factory->openImport($scope, $ref, ...$ports);
    expect($resumed->token->checkpointSequence)->toBe('1');
    $complete = $resumed->runner->step($scope, $ref, $resumed->token);
    expect($complete->state)->toBe('completed')
        ->and($resumed->results->result($scope, $ref)['state'])->toBe('completed')
        ->and(DB::table('corex_transfer_jobs')->count())->toBe(1)
        ->and(DB::table('corex_transfer_rows')->count())->toBe(2);

    $terminal = $factory->openImport($scope, $ref, ...$ports);
    expect(fn () => $terminal->runner->step($scope, $ref, $terminal->token))->toThrow(RuntimeException::class);
});

it('rejects scope drift for reopen, execution and result reads', function (): void {
    $factory = resolve(ImportExportFactory::class);
    $ref = new TransferRef('22222222-2222-4222-8222-222222222222');
    $ports = factoryImportPorts();
    $session = $factory->openImport(factoryScope(), $ref, ...$ports);

    foreach ([factoryScope('user-b'), factoryScope(epoch: 'b')] as $scope) {
        expect(fn () => $factory->openImport($scope, $ref, ...$ports))->toThrow(SessionUnavailable::class)
            ->and(fn () => $session->runner->step($scope, $ref, $session->token))->toThrow(SessionUnavailable::class)
            ->and(fn () => $session->results->result($scope, $ref))->toThrow(RuntimeException::class);
    }

    expect(DB::table('corex_transfer_rows')->count())->toBe(0);
});

it('binds an idempotent identity to the original mapping and conflict rule', function (): void {
    $factory = resolve(ImportExportFactory::class);
    $ref = new TransferRef('33333333-3333-4333-8333-333333333333');
    $ports = factoryImportPorts();
    $factory->openImport(factoryScope(), $ref, ...$ports);
    $ports[4] = new ConflictRule('create-only');

    expect(fn () => $factory->openImport(factoryScope(), $ref, ...$ports))->toThrow(SessionUnavailable::class);
});

it('refuses session creation before source I/O when authority is denied', function (): void {
    $denied = Mockery::mock(DataScopeAuthorizer::class);
    $denied->shouldReceive('authorize')->andReturn(new DataScopeAccessDecision(false, 'denied'));
    app()->instance(DataScopeAuthorizer::class, $denied);
    $ports = factoryImportPorts();
    $source = Mockery::mock(TransferSource::class);
    $source->shouldNotReceive('describe');
    $ports[0] = $source;

    expect(fn () => resolve(ImportExportFactory::class)->openImport(
        factoryScope(), new TransferRef('44444444-4444-4444-8444-444444444444'), ...$ports,
    ))->toThrow(SessionUnavailable::class);
    expect(DB::table('corex_transfer_jobs')->count())->toBe(0);
});

it('opens the stock export runner and refuses another restore epoch', function (): void {
    $hash = str_repeat('a', 64);
    $manifest = new ExportManifest('55555555-5555-4555-8555-555555555555', 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64));
    $cursor = new ExportCursor('0', '0', str_repeat('0', 64));
    $ref = new TransferRef('66666666-6666-4666-8666-666666666666');
    $source = Mockery::mock(ExportSource::class);
    $factory = resolve(ImportExportFactory::class);
    $session = $factory->openExport(factoryScope(), $ref, $source, $manifest, $cursor);

    expect($session->runner)->toBeInstanceOf(ExportRunner::class)
        ->and($session->token->checkpointSequence)->toBe('0')
        ->and(fn () => $session->results->result(factoryScope(), $ref))->toThrow(ExportUnavailable::class);
    expect(fn () => $factory->openExport(factoryScope(epoch: 'b'), $ref, $source, $manifest, $cursor))->toThrow(SessionUnavailable::class);
});

it('refuses export before opening a job without a current schema witness', function (): void {
    app()->forgetInstance(ExportSchemaWitness::class);
    $hash = str_repeat('a', 64);
    $manifest = new ExportManifest('55555555-5555-4555-8555-555555555555', 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64));
    $source = Mockery::mock(ExportSource::class);
    $source->shouldNotReceive('page');

    expect(fn () => resolve(ImportExportFactory::class)->openExport(
        factoryScope(), new TransferRef('66666666-6666-4666-8666-666666666666'), $source, $manifest, new ExportCursor('0', '0', str_repeat('0', 64)),
    ))->toThrow(ExportUnavailable::class)
        ->and(DB::table('corex_transfer_jobs')->count())->toBe(0);
});

it('refuses retained sessions before I/O after a tenant connection switch', function (): void {
    $scope = factoryScope();
    $factory = resolve(ImportExportFactory::class);
    $import = $factory->openImport($scope, new TransferRef('77777777-7777-4777-8777-777777777777'), ...factoryImportPorts());
    $hash = str_repeat('a', 64);
    $manifest = new ExportManifest('88888888-8888-4888-8888-888888888888', 'coherent', $hash, $hash, JsonDocuments::capture([]), str_repeat('0', 64));
    $cursor = new ExportCursor('0', '0', str_repeat('0', 64));
    $source = Mockery::mock(ExportSource::class);
    $source->shouldNotReceive('page');
    $export = $factory->openExport($scope, new TransferRef('99999999-9999-4999-8999-999999999999'), $source, $manifest, $cursor);
    config(['database.connections.other' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::setDefaultConnection('other');

    expect(fn () => $import->runner->step($scope, $import->transfer, $import->token))->toThrow(SessionUnavailable::class)
        ->and(fn () => $import->results->result($scope, $import->transfer))->toThrow(SessionUnavailable::class)
        ->and(fn () => $export->runner->seal($scope, $export->transfer->id, $manifest, $cursor))->toThrow(SessionUnavailable::class)
        ->and(fn () => $export->runner->emit($scope, $manifest, $cursor))->toThrow(SessionUnavailable::class);
});

it('refuses a retained session when tenant context changes on the same connection', function (): void {
    $context = new TenantContext(new AccountRef('account-a', 'a', 'active', [], []), boxed: true);
    $resolver = Mockery::mock(TenantContextResolver::class);
    $resolver->shouldReceive('current')->andReturnUsing(static function () use (&$context): TenantContext {
        return $context;
    });
    app()->instance(TenantContextResolver::class, $resolver);
    CallerWorkScopeFixture::bind(app(), factoryScope());
    $scope = factoryScope();
    $session = resolve(ImportExportFactory::class)->openImport($scope, new TransferRef('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'), ...factoryImportPorts());
    $context = new TenantContext(new AccountRef('account-b', 'b', 'active', [], []), boxed: true);

    expect(fn () => $session->runner->step($scope, $session->transfer, $session->token))->toThrow(SessionUnavailable::class)
        ->and(fn () => $session->results->result($scope, $session->transfer))->toThrow(SessionUnavailable::class)
        ->and(DB::table('corex_transfer_rows')->count())->toBe(0);
});

it('binds each runner to its session even when source and mapping match', function (): void {
    $factory = resolve(ImportExportFactory::class);
    $scope = factoryScope();
    $ports = factoryImportPorts();
    $first = $factory->openImport($scope, new TransferRef('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'), ...$ports);
    $ports[4] = new ConflictRule('create-only');
    $second = $factory->openImport($scope, new TransferRef('cccccccc-cccc-4ccc-8ccc-cccccccccccc'), ...$ports);

    expect(fn () => $first->runner->step($scope, $second->transfer, $second->token))->toThrow(SessionUnavailable::class)
        ->and(fn () => $first->results->result($scope, $second->transfer))->toThrow(SessionUnavailable::class)
        ->and(DB::table('corex_transfer_rows')->count())->toBe(0);
});
