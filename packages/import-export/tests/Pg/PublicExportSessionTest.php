<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\ExportSource;
use CoreX\ImportExport\Contracts\ImportExportFactory;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\Export\ExportPage;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Exceptions\SessionUnavailable;
use CoreX\ImportExport\Tests\PgTestCase;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Storage\Data\StorageCapabilities;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('seals, reads and reopens a public export without touching private tables', function (): void {
    $resolver = Mockery::mock(TenantContextResolver::class);
    $resolver->shouldReceive('current')->andReturn(new TenantContext(new AccountRef('account', 'a', 'active', [], [])));
    app()->instance(TenantContextResolver::class, $resolver);
    $hash = str_repeat('a', 64);
    $scope = new DataScope('account', null, 'pgsql:'.DB::connection()->getDatabaseName(), 'user-a', $hash);
    $authorizer = Mockery::mock(DataScopeAuthorizer::class);
    $authorizer->shouldReceive('authorize')->andReturn(new DataScopeAccessDecision(true, 'allowed'));
    app()->instance(DataScopeAuthorizer::class, $authorizer);
    $files = [];
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('exists')->andReturnUsing(static function (string $path) use (&$files): bool {
        return isset($files[$path]);
    });
    $disk->shouldReceive('put')->andReturnUsing(static function (string $path, string $bytes) use (&$files): bool {
        $files[$path] = $bytes;

        return true;
    });
    $disk->shouldReceive('get')->andReturnUsing(static function (string $path) use (&$files): ?string {
        return $files[$path] ?? null;
    });
    $storage = Mockery::mock(TenantStorage::class);
    $storage->shouldReceive('disk')->andReturn($disk);
    $storage->shouldReceive('capabilities')->andReturn(new StorageCapabilities(false, true));
    app()->instance(TenantStorage::class, $storage);
    $source = Mockery::mock(ExportSource::class);
    $source->shouldReceive('page')->once()->andReturn(new ExportPage([
        new CanonicalRecord('note', 'sample', '1', '1', null, ['title' => 'Example'], $hash),
    ], null, true));
    $draft = new ExportManifest('11111111-1111-4111-8111-111111111111', 'coherent', $hash, $hash, JsonDocuments::capture(['note' => ['count' => 1]]), str_repeat('0', 64));
    $cursor = new ExportCursor('0', '0', str_repeat('0', 64));
    $factory = resolve(ImportExportFactory::class);
    $session = $factory->openExport($scope, new TransferRef('22222222-2222-4222-8222-222222222222'), $source, $draft, $cursor);
    $sealed = $session->runner->seal($scope, $session->transfer->id, $draft, $cursor);
    $output = new ExportCursor('0', '0', $sealed->partsHash);
    $bytes = $session->runner->emit($scope, $sealed, $output);

    expect($bytes)->toContain('"title":"Example"')
        ->and($session->results->result($scope, $session->transfer)['state'])->toBe('completed');

    $reopened = $factory->openExport($scope, $session->transfer, $source, $draft, $cursor);
    $replayed = $reopened->runner->seal($scope, $session->transfer->id, $draft, $cursor);
    expect($replayed->partsHash)->toBe($sealed->partsHash)
        ->and($reopened->runner->emit($scope, $replayed, $output))->toBe($bytes);

    $foreign = new DataScope('account', null, $scope->connectionIdentity, 'user-b', $hash);
    expect(fn () => $session->runner->emit($foreign, $sealed, $output))->toThrow(SessionUnavailable::class);

    $other = $factory->openExport($scope, new TransferRef('33333333-3333-4333-8333-333333333333'), $source, $draft, $cursor);
    expect(fn () => $other->runner->seal($scope, $other->transfer->id, $draft, $cursor))->toThrow(ExportUnavailable::class);
});
