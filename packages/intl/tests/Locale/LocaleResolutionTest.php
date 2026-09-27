<?php

declare(strict_types=1);

use Brick\Money\Money;
use CoreX\Intl\Contracts\LocaleResolver;
use CoreX\Intl\Contracts\MoneyFormatter;
use CoreX\Intl\Contracts\TranslationReader;
use CoreX\Intl\Data\FormatPolicy;
use CoreX\Intl\Data\GeographyQuery;
use CoreX\Intl\Data\LocaleInputs;
use CoreX\Intl\Data\LocalePolicy;
use CoreX\Intl\Data\LocalizedText;
use CoreX\Intl\Exceptions\InvalidLocaleException;
use CoreX\Intl\Exceptions\SchemaMismatchException;
use CoreX\Intl\Internal\Locale\Bcp47Validator;
use CoreX\Intl\Internal\Locale\LocalePolicyBuilder;
use CoreX\Intl\Internal\Locale\RequestLocaleScope;
use CoreX\Intl\Internal\Reference\BoxesReferenceProvider;
use CoreX\Intl\Internal\Reference\CompactReferenceProvider;
use CoreX\Intl\Internal\Reference\FixtureReferenceNormalizer;
use CoreX\Intl\Internal\Reference\ReferenceCursorCodec;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;

it('LOC-01 validates supported allowlist and rejects invalid tags', function (): void {
    $policy = app(LocalePolicy::class);
    $validator = app(Bcp47Validator::class);

    expect($policy->supportedTags)->not->toBeEmpty()
        ->and($validator->validate('en-US'))->toBe('en-US')
        ->and(fn () => $validator->validate(str_repeat('a', 129)))->toThrow(InvalidLocaleException::class)
        ->and(fn () => $validator->validate('xx-INVALID'))->toThrow(InvalidLocaleException::class);
});

it('LOC-02 resolves user settings app and fallback chain explicitly', function (): void {
    $resolver = app(LocaleResolver::class);
    $policy = app(LocalePolicy::class);

    $user = $resolver->resolve(
        inputs: new LocaleInputs(projectedUserTag: 'ru-RU', accountSettingsTag: 'en', requestContextKey: 'req-1'),
        policy: $policy,
    );

    expect($user->used)->toBe('ru-RU')
        ->and($user->origin)->toBe('user')
        ->and($user->requested)->toBe('ru-RU')
        ->and($user->wasFallback)->toBeFalse();

    $settings = $resolver->resolve(
        inputs: new LocaleInputs(projectedUserTag: null, accountSettingsTag: 'ru', requestContextKey: 'req-2'),
        policy: $policy,
    );

    expect($settings->used)->toBe('ru')
        ->and($settings->origin)->toBe('settings');

    $parent = $resolver->resolve(
        inputs: new LocaleInputs(projectedUserTag: 'en-GB', accountSettingsTag: null, requestContextKey: 'req-3'),
        policy: $policy,
    );

    expect($parent->used)->toBe('en')
        ->and($parent->wasFallback)->toBeTrue()
        ->and($parent->fallbackChain)->toContain('en-GB');
});

it('LOC-03 restores request locale scope without global leakage', function (): void {
    $scope = app(RequestLocaleScope::class);
    $resolver = app(LocaleResolver::class);
    $policy = app(LocalePolicy::class);

    $a = $resolver->resolve(
        inputs: new LocaleInputs(projectedUserTag: 'ru', accountSettingsTag: null, requestContextKey: 'a'),
        policy: $policy,
    );
    $b = $resolver->resolve(
        inputs: new LocaleInputs(projectedUserTag: 'en-US', accountSettingsTag: null, requestContextKey: 'b'),
        policy: $policy,
    );

    $scope->run($a, function () use ($scope, $b): void {
        expect($scope->current()?->used)->toBe('ru');

        $scope->run($b, function () use ($scope): void {
            expect($scope->current()?->used)->toBe('en-US');

            throw new RuntimeException('rollback');
        });
    });

    expect($scope->current())->toBeNull();
})->throws(RuntimeException::class);

it('LOC-04 builds deterministic policy hash without remote calls', function (): void {
    $builder = new LocalePolicyBuilder;
    $first = $builder->build(config: config('corex-intl.locale'));
    $second = $builder->build(config: config('corex-intl.locale'));

    expect($first->policyHash)->toBe($second->policyHash)
        ->and(strlen($first->policyHash))->toBe(64);
});

it('LOC-05 reads translations with explicit fallback order', function (): void {
    $reader = app(TranslationReader::class);
    $resolver = app(LocaleResolver::class);
    $policy = app(LocalePolicy::class);

    $locale = $resolver->resolve(
        inputs: new LocaleInputs(projectedUserTag: 'ru', accountSettingsTag: null, requestContextKey: 't'),
        policy: $policy,
    );

    $found = $reader->read(
        text: new LocalizedText(declaredField: null, schemaHash: null, map: ['ru' => 'Привет', 'en' => 'Hello']),
        locale: $locale,
    );

    expect($found->status)->toBe('found')
        ->and($found->value)->toBe('Привет');

    $empty = $reader->read(
        text: new LocalizedText(declaredField: null, schemaHash: null, map: ['ru' => '']),
        locale: $locale,
    );

    expect($empty->value)->toBe('');

    $missing = $reader->read(
        text: new LocalizedText(declaredField: null, schemaHash: null, map: ['ru' => null, 'en' => 'Hello']),
        locale: $locale,
    );

    expect($missing->status)->toBe('found')
        ->and($missing->value)->toBe('Hello')
        ->and($missing->wasFallback)->toBeTrue();
});

it('LOC-06 rejects undeclared localized field without schema hash', function (): void {
    $reader = app(TranslationReader::class);
    $locale = app(LocaleResolver::class)->resolve(
        inputs: new LocaleInputs(projectedUserTag: 'en', accountSettingsTag: null, requestContextKey: 'f'),
        policy: app(LocalePolicy::class),
    );

    expect(fn () => $reader->read(
        text: new LocalizedText(declaredField: 'secret.path', schemaHash: null, map: []),
        locale: $locale,
    ))->toThrow(SchemaMismatchException::class);
});

it('LOC-07 uses existing translator adapter without new storage', function (): void {
    app('translator')->addLines(['intl.test.label' => 'Label'], 'en');
    $reader = app(TranslationReader::class);
    $locale = app(LocaleResolver::class)->resolve(
        inputs: new LocaleInputs(projectedUserTag: 'en', accountSettingsTag: null, requestContextKey: 'ui'),
        policy: app(LocalePolicy::class),
    );

    $result = $reader->read(
        text: new LocalizedText(declaredField: 'intl.test.label', schemaHash: hash('sha256', 'intl.test.label'), map: []),
        locale: $locale,
    );

    expect($result->status)->toBe('found')
        ->and($result->value)->toBe('Label');
});

it('LOC-08 boots with plain formatting fallback when ICU path unavailable', function (): void {
    config(['corex-intl.formatting.icu_enabled' => false]);
    $this->app->forgetScopedInstances();

    $formatter = app(MoneyFormatter::class);
    $locale = app(LocaleResolver::class)->resolve(
        inputs: new LocaleInputs(projectedUserTag: 'en', accountSettingsTag: null, requestContextKey: 'fmt'),
        policy: app(LocalePolicy::class),
    );

    $formatted = $formatter->format(
        amount: Money::of('12.34', 'USD'),
        locale: $locale,
        policy: new FormatPolicy(mode: 'plainExact', fallback: 'plainExact'),
    );

    expect($formatted->method)->toBe('plainExact')
        ->and($formatted->text)->toBe('USD 12.34');
});

it('LOC-09 reads localized reference metadata from two providers deterministically', function (): void {
    $normalizer = app(FixtureReferenceNormalizer::class);
    $cursors = app(ReferenceCursorCodec::class);

    $boxes = new BoxesReferenceProvider(
        fixturePath: __DIR__.'/../fixtures/reference/boxes.json',
        normalizer: $normalizer,
        cursors: $cursors,
    );
    $compact = new CompactReferenceProvider(
        fixturePath: __DIR__.'/../fixtures/reference/compact.json',
        normalizer: $normalizer,
        cursors: $cursors,
    );

    $scope = DataScope::fromTenantContext(
        context: new TenantContext(account: new AccountRef(id: 'acct', slug: 'acct', status: 'active', features: [], limits: [])),
        connectionIdentity: 'sqlite::memory:',
        actorId: 'reader',
        restoreEpoch: str_repeat('a', 64),
    );

    $boxesCountry = $boxes->query($scope, new GeographyQuery(
        kind: 'country', countryKey: null, parentKey: null, placeKey: null, query: 'united', locale: 'en', snapshotId: null, mode: 'prefix', limit: 10,
    ));
    $compactCountry = $compact->query($scope, new GeographyQuery(
        kind: 'country', countryKey: null, parentKey: null, placeKey: null, query: 'united', locale: 'en', snapshotId: null, mode: 'prefix', limit: 10,
    ));

    expect($boxesCountry->status)->toBe('available')
        ->and($compactCountry->status)->toBe('available')
        ->and($boxesCountry->records[0]['sourceId'])->toBe('US')
        ->and($compactCountry->records[0]['sourceId'])->toBe('US')
        ->and($boxesCountry->records[0]['names']['ru'])->toBe('Соединённые Штаты');
});
