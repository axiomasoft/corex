<?php

declare(strict_types=1);

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\ExportPublications;
use CoreX\ImportExport\Contracts\ExportSchemaWitness;
use CoreX\ImportExport\Contracts\ExportSource;
use CoreX\ImportExport\Contracts\ImportExportFactory;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\Export\ExportPage;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Exceptions\SessionUnavailable;
use CoreX\ImportExport\Internal\SessionRuntime;
use CoreX\ImportExport\Tests\PgTestCase;
use CoreX\ImportExport\Tests\TransferAdmissionFixture;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Storage\Data\StorageCapabilities;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('keeps transfer session refusal closed for a mismatched connection and a stale physical binding', function (): void {
    LocalWorkContexts::install(app())->initialize(new AccountRef('export-session', 'export-session', 'active', [], []));
    $scope = WorkScopeFixture::install(app())->scope();
    $runtime = new SessionRuntime(resolve(CurrentWorkScope::class));
    $tenant = DB::connection('pgsql');

    $assertRefused = static function (ConnectionInterface $connection, string $cause) use ($runtime, $scope): void {
        try {
            $runtime->assertCurrent($connection, $scope);
        } catch (SessionUnavailable $error) {
            expect($error->getPrevious())->toBeInstanceOf($cause);

            return;
        }

        throw new LogicException('The transfer session accepted an invalid owner.');
    };

    $runtime->assertCurrent($tenant, $scope);
    $assertRefused(DB::connection('work-scope-control'), ScopeContextMismatch::class);

    $owner = resolve(DatabaseScopeControl::class);
    $closed = $owner->drain($scope->accountId, $owner->close($scope->accountId, 1));
    $next = new PhysicalBinding($scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->accountIncarnation, str_repeat('c', 64));
    $owner->reopen($scope->accountId, $closed, $next, static fn () => true);

    $assertRefused($tenant, ScopeStale::class);
});

it('seals, reads and reopens a public export without touching private tables', function (): void {
    $resolver = Mockery::mock(TenantContextResolver::class);
    $resolver->shouldReceive('current')->andReturn(new TenantContext(new AccountRef('account', 'a', 'active', [], [])));
    app()->instance(TenantContextResolver::class, $resolver);
    $hash = str_repeat('a', 64);
    $scope = CallerWorkScopeFixture::scope('account', null, 'user-a', $hash);
    CallerWorkScopeFixture::bind(app(), $scope);
    TransferAdmissionFixture::bind(app());
    $currentSchema = $hash;
    $schema = Mockery::mock(ExportSchemaWitness::class);
    $schema->shouldReceive('matches')->andReturnUsing(static function (DataScope $current, string $expected, JsonDocument $roster) use ($scope, &$currentSchema): bool {
        return $current === $scope && JsonDocuments::map($roster) === ['note' => ['count' => 1]] && hash_equals($currentSchema, $expected);
    });
    app()->instance(ExportSchemaWitness::class, $schema);
    $authorizer = Mockery::mock(DataScopeAuthorizer::class);
    $export = true;
    $authorizer->shouldReceive('authorize')->andReturnUsing(static function ($scope, $record, $fields, FieldUse $use) use (&$export): DataScopeAccessDecision {
        return new DataScopeAccessDecision($export || $use !== FieldUse::Export, 'fixture.policy');
    });
    app()->instance(DataScopeAuthorizer::class, $authorizer);
    $files = [];
    $reads = 0;
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('exists')->andReturnUsing(static function (string $path) use (&$files): bool {
        return isset($files[$path]);
    });
    $disk->shouldReceive('put')->andReturnUsing(static function (string $path, string $bytes) use (&$files): bool {
        $files[$path] = $bytes;

        return true;
    });
    $disk->shouldReceive('get')->andReturnUsing(static function (string $path) use (&$files, &$reads): ?string {
        $reads++;

        return $files[$path] ?? null;
    });
    $storage = Mockery::mock(TenantStorage::class);
    $storage->shouldReceive('disk')->andReturn($disk);
    $storage->shouldReceive('capabilities')->andReturn(new StorageCapabilities(false, true));
    app()->instance(TenantStorage::class, $storage);
    $source = Mockery::mock(ExportSource::class);
    $source->shouldReceive('page')->twice()->andReturn(
        new ExportPage([new CanonicalRecord('note', 'sample', '1', '1', null, ['title' => 'Example'], $hash)], new ExportCursor('1', '1', str_repeat('0', 64)), false),
        new ExportPage([new CanonicalRecord('note', 'sample', '2', '1', null, ['title' => 'Second part'], $hash)], null, true),
    );
    $draft = new ExportManifest('11111111-1111-4111-8111-111111111111', 'coherent', $hash, $hash, JsonDocuments::capture(['note' => ['count' => 1]]), str_repeat('0', 64));
    $cursor = new ExportCursor('0', '0', str_repeat('0', 64));
    $factory = resolve(ImportExportFactory::class);
    $session = $factory->openExport($scope, new TransferRef('22222222-2222-4222-8222-222222222222'), $source, $draft, $cursor);
    $sealed = $session->runner->seal($scope, $session->transfer->id, $draft, $cursor);
    $output = new ExportCursor('0', '0', $sealed->partsHash);
    $bytes = $session->runner->emit($scope, $sealed, $output);

    $reader = resolve(TransferResultReader::class);
    expect(fn () => $reader->result($scope, $session->transfer))->toThrow(ExportUnavailable::class);
    $publisher = resolve(ExportPublications::class);
    $publisher->publish($scope, $session->transfer, $sealed);
    $publisher->publish($scope, $session->transfer, $sealed);
    $result = $reader->result($scope, $session->transfer);

    expect($bytes)->toContain('"title":"Example"')
        ->and($result['manifestId'])->toBe($sealed->id)
        ->and($result['partsHash'])->toBe($sealed->partsHash)
        ->and($result['parts'])->toHaveCount(2)
        ->and($result['parts'][0]['sha256'])->toBe(hash('sha256', $bytes))
        ->and($reader->failures($scope, $session->transfer, null, 10))->toBe(['records' => [], 'next' => null]);

    $reopened = $factory->openExport($scope, $session->transfer, $source, $draft, $cursor);
    $replayed = $reopened->runner->seal($scope, $session->transfer->id, $draft, $cursor);
    expect($replayed->partsHash)->toBe($sealed->partsHash)
        ->and($reopened->runner->emit($scope, $replayed, $output))->toBe($bytes);

    $readsBeforeDrift = $reads;
    $currentSchema = str_repeat('b', 64);
    expect(fn () => $reader->result($scope, $session->transfer))->toThrow(ExportUnavailable::class)
        ->and(fn () => $reader->failures($scope, $session->transfer, null, 10))->toThrow(ExportUnavailable::class)
        ->and(fn () => $session->runner->emit($scope, $sealed, $output))->toThrow(ExportUnavailable::class)
        ->and(fn () => $publisher->publish($scope, $session->transfer, $sealed))->toThrow(ExportUnavailable::class)
        ->and($reads)->toBe($readsBeforeDrift);
    $currentSchema = $hash;

    $export = false;
    expect(fn () => $reader->result($scope, $session->transfer))->toThrow(ExportUnavailable::class)
        ->and(fn () => $reader->failures($scope, $session->transfer, null, 10))->toThrow(ExportUnavailable::class)
        ->and(fn () => $session->runner->emit($scope, $sealed, $output))->toThrow(ExportUnavailable::class);
    $export = true;

    $foreign = CallerWorkScopeFixture::scope('account', null, 'user-b', $hash);
    expect(fn () => $session->runner->emit($foreign, $sealed, $output))->toThrow(SessionUnavailable::class);

    $other = $factory->openExport($scope, new TransferRef('33333333-3333-4333-8333-333333333333'), $source, $draft, $cursor);
    expect(fn () => $other->runner->seal($scope, $other->transfer->id, $draft, $cursor))->toThrow(ExportUnavailable::class);
});
