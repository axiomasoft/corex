<?php

declare(strict_types=1);

use Brick\Math\RoundingMode;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;
use CoreX\Intl\Contracts\CurrencyConverter;
use CoreX\Intl\Contracts\CurrencyMetadata;
use CoreX\Intl\Contracts\LocaleResolver;
use CoreX\Intl\Contracts\MoneyFormatter;
use CoreX\Intl\Contracts\RateImporter;
use CoreX\Intl\Contracts\RateProvider;
use CoreX\Intl\Data\ConversionPolicy;
use CoreX\Intl\Data\FormatPolicy;
use CoreX\Intl\Data\LocaleInputs;
use CoreX\Intl\Data\LocalePolicy;
use CoreX\Intl\Data\RateBatch;
use CoreX\Intl\Data\RateQuery;
use CoreX\Intl\Data\RateSnapshot;
use CoreX\Intl\Exceptions\InvalidRateException;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\WriteToken;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use Illuminate\Support\Str;

function intlScope(): DataScope
{
    return DataScope::fromTenantContext(
        context: new TenantContext(account: new AccountRef(id: 'acct', slug: 'acct', status: 'active', features: [], limits: [])),
        connectionIdentity: 'sqlite::memory:',
        actorId: 'fx-tester',
        restoreEpoch: str_repeat('b', 64),
    );
}

function sampleRate(string $base = 'USD', string $quote = 'EUR', string $rate = '0.92', string $validOn = '2026-09-15'): RateSnapshot
{
    return new RateSnapshot(
        schema: 'rate-snapshot/1',
        base: $base,
        quote: $quote,
        rateDecimal: $rate,
        validOn: $validOn,
        observedAt: new DateTimeImmutable('2026-09-15T12:00:00+00:00'),
        source: 'manual',
        publicationId: 'pub-1',
        publicationRevision: 'rev-1',
        payloadSha256: hash('sha256', $rate),
        id: (string) Str::ulid(),
    );
}

it('FX-01 returns pinned currency exponents and rejects unknown codes', function (): void {
    $metadata = app(CurrencyMetadata::class);

    expect($metadata->get('USD')->exponent)->toBe(2)
        ->and($metadata->get('JPY')->exponent)->toBe(0)
        ->and($metadata->get('KWD')->exponent)->toBe(3)
        ->and($metadata->get('CLF')->exponent)->toBe(4)
        ->and(fn () => $metadata->get('ZZZ'))->toThrow(InvalidRateException::class);
});

it('FX-02 stores positive canonical decimal rates with base to quote orientation', function (): void {
    $rate = sampleRate(rate: '1.234567890123456789');

    expect($rate->rateDecimal)->toBe('1.234567890123456789')
        ->and((float) $rate->rateDecimal)->toBeGreaterThan(0);
});

it('FX-03 imports immutable snapshots idempotently and detects conflicts', function (): void {
    $importer = app(RateImporter::class);
    $snapshot = sampleRate();
    $token = new WriteToken(
        operationKey: hash('sha256', 'fx-03'),
        payloadHash: hash('sha256', 'batch-1'),
        expectedRecordVersion: 'rev-1',
    );

    $first = $importer->import(
        scope: intlScope(),
        batch: new RateBatch(snapshots: [$snapshot], expectedGeneration: 1, formatHash: hash('sha256', 'fmt'), sourceManifest: ['source' => 'manual']),
        token: $token,
    );

    $second = $importer->import(
        scope: intlScope(),
        batch: new RateBatch(snapshots: [$snapshot], expectedGeneration: 1, formatHash: hash('sha256', 'fmt'), sourceManifest: ['source' => 'manual']),
        token: $token,
    );

    expect($first->imported)->toBe(1)
        ->and($second->status)->toBe('noop')
        ->and(DB::table('corex_rate_snapshots')->count())->toBe(1);
});

it('FX-05 distinguishes missing and stale lookup results', function (): void {
    $provider = app(RateProvider::class);

    $missing = $provider->lookup(new RateQuery(
        base: 'USD', quote: 'EUR', onDate: '2026-09-15', sourceSelection: ['manual'], publicationCutoff: null, maxAgeDays: 0,
    ));

    expect($missing->status)->toBe('missing');

    $importer = app(RateImporter::class);
    $snapshot = sampleRate(validOn: '2026-01-01');
    $importer->import(
        scope: intlScope(),
        batch: new RateBatch(snapshots: [$snapshot], expectedGeneration: 1, formatHash: hash('sha256', 'fmt2'), sourceManifest: []),
        token: new WriteToken(operationKey: hash('sha256', 'fx-05'), payloadHash: hash('sha256', 'batch-2'), expectedRecordVersion: 'rev-1'),
    );

    $stale = $provider->lookup(new RateQuery(
        base: 'USD', quote: 'EUR', onDate: '2026-09-15', sourceSelection: ['manual'], publicationCutoff: null, maxAgeDays: 30,
    ));

    expect($stale->status)->toBe('stale');
});

it('FX-06 converts with explicit rounding and deterministic receipts', function (): void {
    $converter = app(CurrencyConverter::class);
    $rate = sampleRate();
    $policy = new ConversionPolicy(
        roundingMode: RoundingMode::HalfUp,
        targetContext: new CustomContext(2),
        policyId: 'test',
        allowStale: false,
    );

    $first = $converter->convert(Money::of('10.00', 'USD'), $rate, $policy);
    $second = $converter->convert(Money::of('10.00', 'USD'), $rate, $policy);

    expect($first->result->isEqualTo(Money::of('9.20', 'EUR')))->toBeTrue()
        ->and($first->rateSnapshotId)->toBe($second->rateSnapshotId)
        ->and($first->result->isEqualTo($second->result))->toBeTrue();
});

it('FX-07 preserves same-currency identity without provider lookup', function (): void {
    $converter = app(CurrencyConverter::class);
    $amount = Money::of('42.00', 'USD');
    $rate = sampleRate(base: 'USD', quote: 'USD', rate: '1');
    $policy = new ConversionPolicy(
        roundingMode: RoundingMode::Unnecessary,
        targetContext: new CustomContext(2),
        policyId: 'identity',
        allowStale: false,
    );

    $receipt = $converter->convert($amount, $rate, $policy);

    expect($receipt->result->isEqualTo($amount))->toBeTrue();
});

it('FX-08 converts negative zero and large signed amounts exactly', function (): void {
    $converter = app(CurrencyConverter::class);
    $rate = sampleRate(rate: '2');
    $policy = new ConversionPolicy(
        roundingMode: RoundingMode::HalfUp,
        targetContext: new CustomContext(2),
        policyId: 'signed',
        allowStale: false,
    );

    $negative = $converter->convert(Money::of('-1.50', 'USD'), $rate, $policy);
    $zero = $converter->convert(Money::of('0', 'USD'), $rate, $policy);

    expect($negative->result->isEqualTo(Money::of('-3.00', 'EUR')))->toBeTrue()
        ->and($zero->result->isZero())->toBeTrue();
});

it('FX-09 formats with plain exact ISO fallback', function (): void {
    $formatter = app(MoneyFormatter::class);
    $locale = app(LocaleResolver::class)->resolve(
        inputs: new LocaleInputs(projectedUserTag: 'en', accountSettingsTag: null, requestContextKey: 'fx'),
        policy: app(LocalePolicy::class),
    );

    $formatted = $formatter->format(
        amount: Money::of('1234567890.12', 'USD'),
        locale: $locale,
        policy: new FormatPolicy(mode: 'plainExact', fallback: 'plainExact'),
    );

    expect($formatted->text)->toBe('USD 1234567890.12')
        ->and($formatted->exactDecimal)->toBe('1234567890.12');
});

it('FX-10 keeps old snapshots addressable after corrected import revision', function (): void {
    $importer = app(RateImporter::class);
    $old = sampleRate(rate: '0.90', validOn: '2026-09-14');
    $old = new RateSnapshot(
        schema: $old->schema,
        base: $old->base,
        quote: $old->quote,
        rateDecimal: $old->rateDecimal,
        validOn: $old->validOn,
        observedAt: $old->observedAt,
        source: $old->source,
        publicationId: 'pub-history',
        publicationRevision: 'rev-1',
        payloadSha256: hash('sha256', '0.90'),
        id: $old->id,
    );

    $importer->import(
        scope: intlScope(),
        batch: new RateBatch(snapshots: [$old], expectedGeneration: 1, formatHash: hash('sha256', 'hist'), sourceManifest: []),
        token: new WriteToken(operationKey: hash('sha256', 'fx-10-a'), payloadHash: hash('sha256', 'hist-a'), expectedRecordVersion: 'rev-1'),
    );

    $corrected = new RateSnapshot(
        schema: $old->schema,
        base: $old->base,
        quote: $old->quote,
        rateDecimal: '0.91',
        validOn: $old->validOn,
        observedAt: $old->observedAt,
        source: $old->source,
        publicationId: 'pub-history',
        publicationRevision: 'rev-2',
        payloadSha256: hash('sha256', '0.91'),
        id: (string) Str::ulid(),
    );

    $importer->import(
        scope: intlScope(),
        batch: new RateBatch(snapshots: [$corrected], expectedGeneration: 2, formatHash: hash('sha256', 'hist'), sourceManifest: []),
        token: new WriteToken(operationKey: hash('sha256', 'fx-10-b'), payloadHash: hash('sha256', 'hist-b'), expectedRecordVersion: 'rev-2'),
    );

    expect(DB::table('corex_rate_snapshots')->count())->toBe(2);
});
