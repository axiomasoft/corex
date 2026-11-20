<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Intl\Territory\Contracts\DatasetImporter;
use CoreX\Intl\Territory\Contracts\GeographyProvider;
use CoreX\Intl\Territory\Data\DatasetCandidate;
use CoreX\Intl\Territory\Data\DatasetGeneration;
use CoreX\Intl\Territory\Internal\Reference\FixtureReferenceNormalizer;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateIntl();
});

it('GEO-04 publishes reference dataset with CAS generation on PostgreSQL', function (): void {
    $scope = CallerWorkScopeFixture::forContext(new TenantContext(account: new AccountRef(id: 'acct', slug: 'acct', status: 'active', features: [], limits: [])), 'geo-pg', str_repeat('e', 64));

    $parsed = resolve(FixtureReferenceNormalizer::class)->parse(provider: 'boxes', fixturePath: __DIR__.'/../fixtures/reference/boxes.json');
    $candidate = new DatasetCandidate(
        manifest: $parsed['manifest'],
        immutableRef: __DIR__.'/../fixtures/reference/boxes.json',
        byteLength: filesize(__DIR__.'/../fixtures/reference/boxes.json'),
    );

    $receipt = resolve(DatasetImporter::class)->publish($scope, $candidate, new DatasetGeneration(expectedSnapshot: null, generation: 0));

    expect($receipt->status)->toBe('published')
        ->and($receipt->generation)->toBe(1)
        ->and(DB::table('corex_reference_records')->count())->toBeGreaterThan(0)
        ->and(resolve(GeographyProvider::class)->describe($scope)->snapshotId)->toBe('boxes-fixture-v1');
})->group('pg');
