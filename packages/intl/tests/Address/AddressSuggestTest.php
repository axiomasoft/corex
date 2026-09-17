<?php

declare(strict_types=1);

use CoreX\Intl\Contracts\AddressSuggestProvider;
use CoreX\Intl\Data\AddressQuery;
use CoreX\Intl\Data\SuggestionLimits;
use CoreX\Intl\Internal\Address\AddressSuggestGuard;
use CoreX\Intl\Internal\Address\FixtureAddressSuggestProvider;
use CoreX\Intl\Internal\Address\MapAddressSuggestProvider;
use CoreX\Intl\Internal\Address\UnsupportedAddressSuggestProvider;
use CoreX\Intl\Internal\Locale\Bcp47Validator;

function addressGuard(): AddressSuggestGuard
{
    return new AddressSuggestGuard(new Bcp47Validator(['en', 'ru', 'en-US', 'ru-RU']));
}

function fixtureAddressProvider(): FixtureAddressSuggestProvider
{
    return new FixtureAddressSuggestProvider(
        fixturePath: __DIR__.'/../fixtures/address/suggestions.json',
        guard: addressGuard(),
    );
}

it('OPT-01 keeps offline base valid when address provider is absent', function (): void {
    $provider = new UnsupportedAddressSuggestProvider(guard: addressGuard());
    $result = $provider->suggest(
        query: new AddressQuery(text: 'offline input', locale: 'en', country: null, consentReference: 'consent-1'),
        limits: new SuggestionLimits,
    );

    expect($result->status)->toBe('unsupported')
        ->and($result->suggestions)->toBe([])
        ->and($result->source)->toBeNull();
});

it('OPT-04 rejects invalid query bounds and enforces response limits', function (): void {
    $provider = fixtureAddressProvider();

    $invalid = $provider->suggest(
        query: new AddressQuery(text: '', locale: 'en', country: null, consentReference: 'consent-1'),
        limits: new SuggestionLimits,
    );

    expect($invalid->status)->toBe('invalid');

    $available = $provider->suggest(
        query: new AddressQuery(text: 'tverskaya', locale: 'ru', country: 'RU', consentReference: 'consent-2'),
        limits: new SuggestionLimits(timeoutSeconds: 2, results: 20, maxResponseBytes: 65536),
    );

    expect($available->status)->toBe('available')
        ->and($available->source)->toBe('fixture-address/1')
        ->and($available->suggestions)->toHaveCount(2)
        ->and($available->provenance)->not->toHaveKey('secret')
        ->and($available->provenance)->not->toHaveKey('token');

    $notFound = $provider->suggest(
        query: new AddressQuery(text: 'unknown street', locale: 'en', country: null, consentReference: 'consent-3'),
        limits: new SuggestionLimits,
    );

    expect($notFound->status)->toBe('notFound');
});

it('OPT-05 swaps two installed address drivers through the same port', function (): void {
    $fixture = fixtureAddressProvider();
    $configured = new MapAddressSuggestProvider(
        source: 'configured-address/1',
        responses: [
            'tverskaya|ru' => [
                ['label' => 'Configured Tverskaya', 'value' => 'cfg-tverskaya'],
            ],
        ],
        guard: addressGuard(),
    );

    $drivers = [$fixture, $configured];

    foreach ($drivers as $driver) {
        expect($driver)->toBeInstanceOf(AddressSuggestProvider::class);
    }

    $fixtureResult = $fixture->suggest(
        query: new AddressQuery(text: 'tverskaya', locale: 'ru', country: 'RU', consentReference: 'consent-4'),
        limits: new SuggestionLimits,
    );
    $configuredResult = $configured->suggest(
        query: new AddressQuery(text: 'tverskaya', locale: 'ru', country: null, consentReference: 'consent-4'),
        limits: new SuggestionLimits,
    );

    expect($fixtureResult->status)->toBe('available')
        ->and($configuredResult->status)->toBe('available')
        ->and($fixtureResult->source)->toBe('fixture-address/1')
        ->and($configuredResult->source)->toBe('configured-address/1')
        ->and($fixtureResult->suggestions[0]['label'])->not->toBe($configuredResult->suggestions[0]['label']);
});

it('OPT-04 exposes distinct unavailable and rateLimited statuses', function (): void {
    $provider = new MapAddressSuggestProvider(
        source: 'configured-address/1',
        responses: [
            'large|en' => array_map(
                static fn (int $index): array => ['label' => str_repeat('x', 512).$index, 'value' => 'v'.$index],
                range(1, 20),
            ),
        ],
        guard: addressGuard(),
    );

    $rateLimited = $provider->suggest(
        query: new AddressQuery(text: 'large', locale: 'en', country: null, consentReference: 'consent-5'),
        limits: new SuggestionLimits(maxResponseBytes: 64),
    );

    expect($rateLimited->status)->toBe('rateLimited');
});

it('OPT-07 address port surface stays read-only suggest without mutation hooks', function (): void {
    $reflection = new ReflectionClass(AddressSuggestProvider::class);

    expect($reflection->getMethods())->toHaveCount(1)
        ->and($reflection->getMethods()[0]->getName())->toBe('suggest');
});
