<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Intl\Currency\Contracts\RateImporter;
use CoreX\Intl\Currency\Data\RateBatch;
use CoreX\Intl\Currency\Data\RateSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateIntl();
});

it('FX-04 imports concurrent rate batches into one immutable row set', function (): void {
    $scope = DataScope::fromTenantContext(
        context: new TenantContext(account: new AccountRef(id: 'acct', slug: 'acct', status: 'active', features: [], limits: [])),
        connectionIdentity: 'pgsql:'.(getenv('PGSQL_DATABASE') ?: 'corex_test'),
        actorId: 'fx-pg',
        restoreEpoch: str_repeat('d', 64),
    );

    $snapshot = new RateSnapshot(
        schema: 'rate-snapshot/1',
        base: 'USD',
        quote: 'EUR',
        rateDecimal: '0.95',
        validOn: '2026-09-15',
        observedAt: new DateTimeImmutable('2026-09-15T00:00:00+00:00'),
        source: 'manual',
        publicationId: 'pg-pub',
        publicationRevision: 'rev-1',
        payloadSha256: hash('sha256', '0.95'),
        id: (string) Str::ulid(),
    );

    $batch = new RateBatch(
        snapshots: [$snapshot],
        expectedGeneration: 1,
        formatHash: hash('sha256', 'fmt-pg'),
        sourceManifest: JsonDocuments::capture(['source' => 'manual']),
    );

    $importer = resolve(RateImporter::class);
    $tokenA = new WriteToken(operationKey: hash('sha256', 'pg-a'), payloadHash: hash('sha256', 'payload-a'), expectedRecordVersion: 'rev-1');
    $tokenB = new WriteToken(operationKey: hash('sha256', 'pg-b'), payloadHash: hash('sha256', 'payload-b'), expectedRecordVersion: 'rev-1');

    $first = $importer->import($scope, $batch, $tokenA);
    $second = $importer->import($scope, $batch, $tokenB);

    expect($first->imported)->toBe(1)
        ->and($second->imported)->toBe(0)
        ->and($second->noop)->toBe(1)
        ->and($second->status)->toBe('published')
        ->and(DB::table('corex_rate_snapshots')->count())->toBe(1)
        ->and(DB::table('corex_rate_batches')->count())->toBe(2);
})->group('pg');
