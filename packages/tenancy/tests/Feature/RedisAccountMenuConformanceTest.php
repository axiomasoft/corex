<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Data\AccountListEntry;
use CoreX\Tenancy\Membership\Data\AccountsQuery;
use CoreX\Tenancy\Membership\Data\AvailableAccounts;
use CoreX\Tenancy\Membership\Exceptions\CentralProtocolViolation;
use CoreX\Tenancy\Membership\Exceptions\CentralUnauthorized;
use CoreX\Tenancy\Membership\Internal\Switcher\AccountMenuReader;
use CoreX\Tenancy\Membership\Internal\Switcher\RedisAccountMenuCache;
use CoreX\Tenancy\Membership\Internal\Switcher\StrictAccountsTransport;
use CoreX\Tenancy\Tests\Support\Membership\AccountMenuFixture;
use CoreX\Tenancy\Tests\Support\Membership\TransportFixture;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\Request;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function p3RedisRepository(): Repository
{
    if (! extension_loaded('redis')) {
        test()->markTestSkipped('Redis account menu conformance requires the optional ext-redis extension.');
    }

    $port = getenv('P3_REDIS_PORT');

    if (! is_string($port) || filter_var($port, FILTER_VALIDATE_INT) === false) {
        test()->markTestSkipped('P3 native Redis proof requires an explicitly supplied isolated Redis endpoint.');
    }

    $manager = new RedisManager(app(), 'phpredis', [
        'default' => ['host' => '127.0.0.1', 'port' => (int) $port, 'database' => 15],
    ]);

    return new Repository(new RedisStore(redis: $manager, prefix: '', connection: 'default'));
}

function p3RedisQuery(string $product = 'product-a'): AccountsQuery
{
    return new AccountsQuery(identityId: '11111111-1111-4111-8111-111111111111', product: $product);
}

/** @param list<string> $accountIds */
function p3RedisResult(AccountsQuery $query, string $version, array $accountIds): AvailableAccounts
{
    return new AvailableAccounts(
        identityId: $query->identityId,
        product: $query->product,
        membershipVersion: $version,
        accounts: array_map(static fn (string $accountId): AccountListEntry => new AccountListEntry(
            accountId: $accountId,
            name: 'Account '.substr($accountId, 0, 8),
            slug: 'account-'.substr($accountId, 0, 8),
            primaryHost: 'account.example.test',
            status: 'active',
        ), $accountIds),
        observedAt: new DateTimeImmutable('2026-09-16T00:00:00+00:00'),
    );
}

function p3RedisKey(string $namespace, AccountsQuery $query, string $version): string
{
    return rtrim($namespace, ':').':'.hash('sha256', json_encode([
        'identity_id' => $query->identityId,
        'product' => $query->product,
        'membership_version' => $version,
    ], JSON_THROW_ON_ERROR));
}

function p3RedisClient(): Redis
{
    $client = new Redis;
    $client->connect('127.0.0.1', (int) getenv('P3_REDIS_PORT'));
    $client->select(15);

    return $client;
}

it('uses an isolated Redis namespace as a display-only cache across cold loss and regrant', function (): void {
    $repository = p3RedisRepository();
    $namespace = 'corex:p3.6:'.bin2hex(random_bytes(8));
    $query = p3RedisQuery();
    $oldAccount = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    $newAccount = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    $version = 'regrant-2';
    $fixture = new AccountMenuFixture;
    $cache = new RedisAccountMenuCache(cache: $repository, versionReader: static fn (): string => $version, namespace: $namespace);
    $reader = new AccountMenuReader(transport: $fixture, cache: $cache, clickAuthorizer: $fixture);
    $raw = p3RedisClient();

    try {
        $cache->put(query: $query, result: p3RedisResult(query: $query, version: 'revoke-1', accountIds: [$oldAccount]), ttlSeconds: 300);
        expect($raw->ttl(p3RedisKey(namespace: $namespace, query: $query, version: 'revoke-1')))->toBeGreaterThan(0)->toBeLessThanOrEqual(300);

        // A new authoritative version cannot address the surviving old bytes.
        $fixture->enqueue(p3RedisResult(query: $query, version: $version, accountIds: [$newAccount]));
        $regranted = $reader->read(query: $query);
        expect($regranted->available)->toBeTrue()
            ->and($regranted->result?->accounts[0]->accountId)->toBe($newAccount)
            ->and($fixture->transportCalls)->toBe(1);

        // Cold loss is a fresh authoritative display read, never a stale hit.
        $raw->del(p3RedisKey(namespace: $namespace, query: $query, version: $version));
        $fixture->enqueue(p3RedisResult(query: $query, version: $version, accountIds: []));
        $cold = $reader->read(query: $query);
        expect($cold->available)->toBeTrue()->and($cold->result?->accounts)->toBe([])->and($fixture->transportCalls)->toBe(2);
    } finally {
        foreach ($raw->keys($namespace.':*') as $key) {
            $raw->del($key);
        }
        $raw->close();
    }
});

it('rejects poisoned Redis bytes, separates product keys, expires bounded display values, and denies unavailable authority version', function (): void {
    $repository = p3RedisRepository();
    $namespace = 'corex:p3.6:'.bin2hex(random_bytes(8));
    $query = p3RedisQuery();
    $other = p3RedisQuery(product: 'product-b');
    $version = 'cache-1';
    $cache = new RedisAccountMenuCache(cache: $repository, versionReader: static fn (): string => $version, namespace: $namespace);
    $raw = p3RedisClient();

    try {
        $poisonedKey = p3RedisKey(namespace: $namespace, query: $query, version: $version);
        $raw->set($poisonedKey, '{"identity_id":"bad","identity_id":"duplicate"}');
        expect($cache->resultFor(query: $query, membershipVersion: $version))->toBeNull()
            ->and($cache->resultFor(query: $other, membershipVersion: $version))->toBeNull();

        $result = p3RedisResult(query: $query, version: $version, accountIds: []);
        $cache->put(query: $query, result: $result, ttlSeconds: 1);
        expect($raw->ttl($poisonedKey))->toBeGreaterThan(0)->toBeLessThanOrEqual(1);
        Sleep::sleep(2);
        expect($cache->resultFor(query: $query, membershipVersion: $version))->toBeNull();

        $fixture = new AccountMenuFixture;
        $unavailable = new RedisAccountMenuCache(cache: $repository, versionReader: static fn (): ?string => null, namespace: $namespace.':unavailable');
        $reader = new AccountMenuReader(transport: $fixture, cache: $unavailable, clickAuthorizer: $fixture);
        expect($reader->read(query: $query)->available)->toBeFalse()->and($fixture->transportCalls)->toBe(0);
    } finally {
        foreach ($raw->keys($namespace.':*') as $key) {
            $raw->del($key);
        }
        $raw->close();
    }
});

it('keeps archive and laboratory Redis display-cache namespaces independent', function (): void {
    $repository = p3RedisRepository();
    $root = 'corex:p3.6:'.bin2hex(random_bytes(8));
    $query = p3RedisQuery();
    $version = 'independent-1';
    $archive = new RedisAccountMenuCache(cache: $repository, versionReader: static fn (): string => $version, namespace: $root.':archive');
    $laboratory = new RedisAccountMenuCache(cache: $repository, versionReader: static fn (): string => $version, namespace: $root.':laboratory');
    $raw = p3RedisClient();

    try {
        $archive->put(query: $query, result: p3RedisResult(query: $query, version: $version, accountIds: ['cccccccc-cccc-4ccc-8ccc-cccccccccccc']), ttlSeconds: 300);
        $laboratory->put(query: $query, result: p3RedisResult(query: $query, version: $version, accountIds: ['dddddddd-dddd-4ddd-8ddd-dddddddddddd']), ttlSeconds: 300);
        expect($archive->resultFor(query: $query, membershipVersion: $version)?->accounts[0]->accountId)->toBe('cccccccc-cccc-4ccc-8ccc-cccccccccccc')
            ->and($laboratory->resultFor(query: $query, membershipVersion: $version)?->accounts[0]->accountId)->toBe('dddddddd-dddd-4ddd-8ddd-dddddddddddd');
    } finally {
        foreach ($raw->keys($root.':*') as $key) {
            $raw->del($key);
        }
        $raw->close();
    }
});

it('uses the exact accepted accounts read profile and rejects mTLS/token or wire failures before a menu can exist', function (): void {
    $fixture = new TransportFixture;

    try {
        $claims = $fixture->accessClaims();
        $claims['scope'] .= ' accounts.read';
        $transport = new StrictAccountsTransport(transport: $fixture->client(claims: $claims), accountsUri: $fixture->binding->readUri);
        $query = new AccountsQuery(identityId: $fixture::IDENTITY, product: $fixture::PRODUCT);
        $wireCalls = 0;
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($fixture, &$wireCalls) {
            if ($wireCalls++ > 0) {
                return Http::response(['profile_version' => 'wrong/1'], headers: $fixture::headers());
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $parameters);
            expect($request->method())->toBe('GET')
                ->and($parameters['identity_id'])->toBe($fixture::IDENTITY)
                ->and($parameters['product'])->toBe($fixture::PRODUCT)
                ->and($parameters['request_id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
                ->and($request->hasHeader('Cache-Control', 'no-store'))->toBeTrue()
                ->and($request->hasHeader('X-CoreX-Membership-Profile', 'corex-membership/1'))->toBeTrue();

            return Http::response([
                'profile_version' => 'corex-membership/1',
                'request_id' => $parameters['request_id'],
                'identity_id' => $fixture::IDENTITY,
                'product' => $fixture::PRODUCT,
                'membership_version' => 'authoritative-v2',
                'observed_at' => '2026-09-16T00:00:00Z',
                'accounts' => [[
                    'account_id' => $fixture::ACCOUNT,
                    'name' => 'Reference account',
                    'slug' => 'reference-account',
                    'primary_host' => 'account.example.test',
                    'status' => 'active',
                ]],
            ], headers: $fixture::headers());
        });
        expect($transport->accountsFor(query: $query)->membershipVersion)->toBe('authoritative-v2');

        $invalidClaims = $fixture->accessClaims();
        $invalid = new StrictAccountsTransport(transport: $fixture->client(claims: $invalidClaims), accountsUri: $fixture->binding->readUri);
        expect(fn (): AvailableAccounts => $invalid->accountsFor(query: $query))->toThrow(CentralUnauthorized::class);
        Http::assertSentCount(1);

        expect(fn (): AvailableAccounts => $transport->accountsFor(query: $query))->toThrow(CentralProtocolViolation::class);
    } finally {
        $fixture->cleanup();
    }
});
