<?php

declare(strict_types=1);

use CoreX\Intl\Contracts\DatasetImporter;
use CoreX\Intl\Contracts\GeographyProvider;
use CoreX\Intl\Data\DatasetCandidate;
use CoreX\Intl\Data\DatasetGeneration;
use CoreX\Intl\Internal\Reference\FixtureReferenceNormalizer;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateIntl();
});

it('GEO-04 publishes reference dataset with CAS generation on PostgreSQL', function (): void {
    $scope = DataScope::fromTenantContext(
        context: new TenantContext(account: new AccountRef(id: 'acct', slug: 'acct', status: 'active', features: [], limits: [])),
        connectionIdentity: 'pgsql:'.(getenv('PGSQL_DATABASE') ?: 'corex_test'),
        actorId: 'geo-pg',
        restoreEpoch: str_repeat('e', 64),
    );

    $parsed = app(FixtureReferenceNormalizer::class)->parse(provider: 'boxes', fixturePath: __DIR__.'/../fixtures/reference/boxes.json');
    $candidate = new DatasetCandidate(
        manifest: $parsed['manifest'],
        immutableRef: __DIR__.'/../fixtures/reference/boxes.json',
        byteLength: filesize(__DIR__.'/../fixtures/reference/boxes.json'),
    );

    $receipt = app(DatasetImporter::class)->publish($scope, $candidate, new DatasetGeneration(expectedSnapshot: null, generation: 0));

    expect($receipt->status)->toBe('published')
        ->and($receipt->generation)->toBe(1)
        ->and(DB::table('corex_reference_records')->count())->toBeGreaterThan(0)
        ->and(app(GeographyProvider::class)->describe($scope)->snapshotId)->toBe('boxes-fixture-v1');
})->group('pg');
