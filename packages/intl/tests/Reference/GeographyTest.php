<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Intl\Currency\Exceptions\SnapshotExpiredException;
use CoreX\Intl\Territory\Contracts\DatasetImporter;
use CoreX\Intl\Territory\Contracts\GeographyProvider;
use CoreX\Intl\Territory\Data\DatasetCandidate;
use CoreX\Intl\Territory\Data\DatasetGeneration;
use CoreX\Intl\Territory\Data\DatasetManifest;
use CoreX\Intl\Territory\Data\GeographyQuery;
use CoreX\Intl\Territory\Data\ReferenceIdentity;
use CoreX\Intl\Territory\Exceptions\SchemaMismatchException;
use CoreX\Intl\Territory\Internal\Reference\BoxesReferenceProvider;
use CoreX\Intl\Territory\Internal\Reference\CompactReferenceProvider;
use CoreX\Intl\Territory\Internal\Reference\FixtureReferenceNormalizer;
use CoreX\Intl\Territory\Internal\Reference\ReferenceCursorCodec;

function geoScope(): DataScope
{
    return DataScope::fromTenantContext(
        context: new TenantContext(account: new AccountRef(id: 'acct', slug: 'acct', status: 'active', features: [], limits: [])),
        connectionIdentity: 'sqlite::memory:',
        actorId: 'geo',
        restoreEpoch: str_repeat('c', 64),
    );
}

function boxesProvider(): BoxesReferenceProvider
{
    return new BoxesReferenceProvider(
        fixturePath: __DIR__.'/../fixtures/reference/boxes.json',
        normalizer: app(FixtureReferenceNormalizer::class),
        cursors: app(ReferenceCursorCodec::class),
    );
}

function compactProvider(): CompactReferenceProvider
{
    return new CompactReferenceProvider(
        fixturePath: __DIR__.'/../fixtures/reference/compact.json',
        normalizer: app(FixtureReferenceNormalizer::class),
        cursors: app(ReferenceCursorCodec::class),
    );
}

it('GEO-01 queries equivalent coverage from two fixture providers', function (): void {
    $scope = geoScope();
    $query = new GeographyQuery(
        kind: 'subdivision',
        countryKey: new ReferenceIdentity(namespace: 'iso3166-1', sourceId: 'US'),
        parentKey: null,
        placeKey: null,
        query: 'california',
        locale: 'en',
        snapshotId: 'boxes-fixture-v1',
        mode: 'prefix',
        limit: 10,
    );

    $boxes = boxesProvider()->query($scope, $query);
    $compact = compactProvider()->query($scope, new GeographyQuery(
        kind: 'subdivision',
        countryKey: new ReferenceIdentity(namespace: 'iso3166-1', sourceId: 'US'),
        parentKey: null,
        placeKey: null,
        query: 'california',
        locale: 'en',
        snapshotId: 'compact-fixture-v1',
        mode: 'prefix',
        limit: 10,
    ));

    expect(JsonDocuments::list($boxes->records)[0]['sourceId'])->toBe('US-CA')
        ->and(JsonDocuments::list($compact->records)[0]['sourceId'])->toBe('US-CA');
});

it('GEO-02 exposes manifest provenance and hashes', function (): void {
    $manifest = boxesProvider()->describe(geoScope());

    expect($manifest->schema)->toBe('reference-data/1')
        ->and($manifest->provider)->toBe('boxes')
        ->and(strlen($manifest->rawHash))->toBe(64)
        ->and(strlen($manifest->normalizedHash))->toBe(64)
        ->and($manifest->licenseUri)->not->toBe('');
});

it('GEO-03 rejects malformed fixture publication and keeps active snapshot unchanged', function (): void {
    $importer = app(DatasetImporter::class);
    $normalizer = app(FixtureReferenceNormalizer::class);
    $parsed = $normalizer->parse(provider: 'broken', fixturePath: __DIR__.'/../fixtures/reference/boxes.json');
    $brokenManifest = $parsed['manifest'];

    $candidate = new DatasetCandidate(
        manifest: new DatasetManifest(
            schema: $brokenManifest->schema,
            snapshotId: $brokenManifest->snapshotId,
            provider: $brokenManifest->provider,
            sourceUri: $brokenManifest->sourceUri,
            retrievedAt: $brokenManifest->retrievedAt,
            rawHash: 'deadbeef',
            normalizedHash: $brokenManifest->normalizedHash,
            sourceVersion: $brokenManifest->sourceVersion,
            importerVersion: $brokenManifest->importerVersion,
            licenseUri: $brokenManifest->licenseUri,
            attribution: $brokenManifest->attribution,
            coveredCountries: $brokenManifest->coveredCountries,
            types: $brokenManifest->types,
            counts: $brokenManifest->counts,
            coverageNotes: $brokenManifest->coverageNotes,
            tzdbVersion: $brokenManifest->tzdbVersion,
            status: $brokenManifest->status,
        ),
        immutableRef: __DIR__.'/../fixtures/reference/boxes.json',
        byteLength: 100,
    );

    expect(fn () => $importer->publish(geoScope(), $candidate, new DatasetGeneration(expectedSnapshot: null, generation: 0)))
        ->toThrow(SchemaMismatchException::class);
});

it('GEO-05 pins keyset cursor to snapshot and query hash', function (): void {
    $provider = boxesProvider();
    $scope = geoScope();
    $query = new GeographyQuery(
        kind: 'subdivision', countryKey: new ReferenceIdentity(namespace: 'iso3166-1', sourceId: 'US'), parentKey: null, placeKey: null, query: null, locale: 'en', snapshotId: 'boxes-fixture-v1', mode: 'exact', limit: 1,
    );

    $first = $provider->query($scope, $query);
    $second = $provider->query($scope, $query, $first->next);

    expect(JsonDocuments::list($first->records))->toHaveCount(1)
        ->and($first->next)->not->toBeNull()
        ->and(JsonDocuments::list($second->records))->toHaveCount(1)
        ->and(JsonDocuments::list($second->records)[0]['sourceId'])->not->toBe(JsonDocuments::list($first->records)[0]['sourceId']);
});

it('GEO-06 normalizes bounded UTF-8 queries without merging ambiguous names', function (): void {
    $provider = boxesProvider();
    $scope = geoScope();

    $page = $provider->query($scope, new GeographyQuery(
        kind: 'country', countryKey: null, parentKey: null, placeKey: null, query: 'united states', locale: 'en', snapshotId: 'boxes-fixture-v1', mode: 'prefix', limit: 100,
    ));

    expect(JsonDocuments::list($page->records))->toHaveCount(1)
        ->and(fn () => app(FixtureReferenceNormalizer::class)->normalizeQuery(str_repeat('a', 201)))
        ->toThrow(SchemaMismatchException::class);
});

it('GEO-07 returns multiple timezone candidates for countries without guessing a single zone', function (): void {
    $provider = boxesProvider();
    $scope = geoScope();

    $country = $provider->query($scope, new GeographyQuery(
        kind: 'country', countryKey: new ReferenceIdentity(namespace: 'iso3166-1', sourceId: 'US'), parentKey: null, placeKey: null, query: null, locale: 'en', snapshotId: 'boxes-fixture-v1', mode: 'exact', limit: 10,
    ));

    expect(count(JsonDocuments::list($country->records)[0]['timezones']))->toBeGreaterThan(1);
});

it('GEO-09 keeps read-only lookup scoped without public write API on provider', function (): void {
    $provider = boxesProvider();
    $reflection = new ReflectionClass($provider);
    $publicMethods = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    expect($publicMethods)->toEqual(['__construct', 'describe', 'query']);
});

it('GEO-10 supports exact and prefix modes on sqlite fixtures', function (): void {
    $provider = boxesProvider();
    $scope = geoScope();

    $exact = $provider->query($scope, new GeographyQuery(
        kind: 'timezone', countryKey: null, parentKey: null, placeKey: null, query: 'america/new_york', locale: 'en', snapshotId: 'boxes-fixture-v1', mode: 'exact', limit: 10,
    ));
    $prefix = $provider->query($scope, new GeographyQuery(
        kind: 'timezone', countryKey: null, parentKey: null, placeKey: null, query: 'america/new', locale: 'en', snapshotId: 'boxes-fixture-v1', mode: 'prefix', limit: 10,
    ));

    expect($exact->status)->toBe('available')
        ->and($prefix->status)->toBe('available');
});

it('GEO-04 publishes fixture dataset through SQL importer', function (): void {
    $normalizer = app(FixtureReferenceNormalizer::class);
    $parsed = $normalizer->parse(provider: 'boxes', fixturePath: __DIR__.'/../fixtures/reference/boxes.json');
    $candidate = new DatasetCandidate(
        manifest: $parsed['manifest'],
        immutableRef: __DIR__.'/../fixtures/reference/boxes.json',
        byteLength: filesize(__DIR__.'/../fixtures/reference/boxes.json'),
    );

    $receipt = app(DatasetImporter::class)->publish(
        scope: geoScope(),
        candidate: $candidate,
        expected: new DatasetGeneration(expectedSnapshot: null, generation: 0),
    );

    expect($receipt->status)->toBe('published')
        ->and(app(GeographyProvider::class)->describe(geoScope())->snapshotId)->toBe('boxes-fixture-v1');
});

it('GEO-05 rejects expired snapshot pins', function (): void {
    $provider = app(GeographyProvider::class);

    expect(fn () => $provider->query(geoScope(), new GeographyQuery(
        kind: 'country', countryKey: null, parentKey: null, placeKey: null, query: null, locale: 'en', snapshotId: 'missing-snapshot', mode: 'exact', limit: 10,
    )))->toThrow(SnapshotExpiredException::class);
});
