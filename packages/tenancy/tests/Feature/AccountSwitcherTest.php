<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Switcher\AccountListEntry;
use CoreX\Tenancy\Membership\Switcher\AccountsQuery;
use CoreX\Tenancy\Membership\Switcher\AccountsResult;
use CoreX\Tenancy\Membership\Transport\CentralUnavailable;
use CoreX\Tenancy\Tests\Support\Membership\AccountMenuFixture;
use Illuminate\Support\Str;

function switcherQuery(string $product = 'product-a'): AccountsQuery
{
    return new AccountsQuery(identityId: '11111111-1111-4111-8111-111111111111', product: $product);
}

function switcherResult(AccountsQuery $query, string $version, array $accountIds): AccountsResult
{
    return new AccountsResult(
        identityId: $query->identityId,
        product: $query->product,
        membershipVersion: $version,
        accounts: array_map(fn (string $accountId): AccountListEntry => new AccountListEntry(
            accountId: $accountId,
            name: 'Account '.substr($accountId, 0, 8),
            slug: 'account-'.substr($accountId, 0, 8),
            primaryHost: 'account.example.test',
            status: 'active',
        ), $accountIds),
        observedAt: new DateTimeImmutable('2026-09-14T00:00:00+00:00'),
    );
}

it('recomputes after only the version key is lost and never adopts a surviving revoked list', function (): void {
    $fixture = new AccountMenuFixture;
    $query = switcherQuery();
    $revokedAccountId = '22222222-2222-4222-8222-222222222222';
    $fixture->put(query: $query, result: switcherResult(query: $query, version: '7', accountIds: [$revokedAccountId]), ttlSeconds: 300);
    $fixture->loseVersionOnly(query: $query);
    $fixture->enqueue(switcherResult(query: $query, version: '7', accountIds: [])); // Counter restart repeats 7.

    $menu = $fixture->reader()->read(query: $query);

    expect($menu->available)->toBeTrue()
        ->and($menu->result?->accounts)->toBe([])
        ->and($fixture->transportCalls)->toBe(1)
        ->and($fixture->cacheLookups)->toBe(0)
        ->and($fixture->lastTtl)->toBe(300);
});

it('keeps a regrant current and does not duplicate a repeated authoritative result', function (): void {
    $fixture = new AccountMenuFixture;
    $query = switcherQuery();
    $regrantedAccountId = '33333333-3333-4333-8333-333333333333';
    $fixture->enqueue(switcherResult(query: $query, version: '8', accountIds: [$regrantedAccountId]));

    $first = $fixture->reader()->read(query: $query);
    $fixture->loseVersionOnly(query: $query);
    $fixture->enqueue(switcherResult(query: $query, version: '8', accountIds: [$regrantedAccountId]));
    $second = $fixture->reader()->read(query: $query);

    expect($first->result?->accounts)->toHaveCount(1)
        ->and($second->result?->accounts)->toHaveCount(1)
        ->and($fixture->transportCalls)->toBe(2);
});

it('does not cross-hit a same identity cache entry for another product', function (): void {
    $fixture = new AccountMenuFixture;
    $firstProduct = switcherQuery(product: 'product-a');
    $secondProduct = switcherQuery(product: 'product-b');
    $fixture->put(query: $firstProduct, result: switcherResult(query: $firstProduct, version: '1', accountIds: [(string) Str::uuid()]), ttlSeconds: 300);
    $fixture->enqueue(switcherResult(query: $secondProduct, version: '1', accountIds: []));

    $menu = $fixture->reader()->read(query: $secondProduct);

    expect($menu->available)->toBeTrue()
        ->and($menu->result?->product)->toBe('product-b')
        ->and($fixture->transportCalls)->toBe(1);
});

it('returns an unavailable empty menu when the authoritative read fails or has a mismatched scope', function (): void {
    $fixture = new AccountMenuFixture;
    $query = switcherQuery();
    $fixture->enqueue(new CentralUnavailable('reference outage'));

    $unavailable = $fixture->reader()->read(query: $query);
    $fixture->enqueue(switcherResult(query: switcherQuery(product: 'product-b'), version: '1', accountIds: []));
    $mismatched = $fixture->reader()->read(query: $query);

    expect($unavailable->available)->toBeFalse()
        ->and($unavailable->result)->toBeNull()
        ->and($mismatched->available)->toBeFalse()
        ->and($mismatched->result)->toBeNull();
});

it('always delegates a stale-menu click to the central authorization boundary', function (): void {
    $fixture = new AccountMenuFixture;
    $query = switcherQuery();
    $fixture->authorizationResult = false;

    $authorized = $fixture->reader()->authorizeClick(query: $query, accountId: '44444444-4444-4444-8444-444444444444');

    expect($authorized)->toBeFalse()->and($fixture->authorizationCalls)->toBe(1);
});
