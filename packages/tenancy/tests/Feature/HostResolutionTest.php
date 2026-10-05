<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\DomainTrust\Internal\Models\Domain;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveTenantFromHost;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Runtime\Exceptions\TenantCouldNotBeIdentifiedByHostException;
use CoreX\Tenancy\Runtime\Internal\HostAccountResolver;
use Illuminate\Cache\Events\WritingKey;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

const ROOT_MIGRATIONS_PATH_HR = __DIR__.'/../../database/migrations/root';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_HR),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    // Host identification with its explicit cache; options are lazy, so rebuild the snapshot.
    config([
        'corex-tenancy.host_identification.enabled' => true,
        'corex-tenancy.host_identification.cache' => true,
        'corex-tenancy.host_identification.cache_ttl' => 300,
        'corex-tenancy.host_identification.cache_store' => 'host_file',
        'cache.stores.host_file' => [
            'driver' => 'file',
            'path' => config('cache.stores.tenant_file.path').'/host-cache',
        ],
    ]);
    app()->forgetInstance(TenancyOptions::class);
    config(['database.connections.host_reader' => config('database.connections.pgsql')]);
});

afterEach(function (): void {
    endTenantContext();
    while (DB::connection('pgsql')->transactionLevel() > 0) {
        DB::connection('pgsql')->rollBack();
    }
    DB::purge('host_reader');
    DB::purge('pgsql');
    DB::purge('tenant');

    foreach ($GLOBALS['__hr_test_dbs'] ?? [] as $db) {
        DB::statement('DROP DATABASE IF EXISTS '.$db);
    }
    $GLOBALS['__hr_test_dbs'] = [];
});

function trackDbHR(string $db): void
{
    $GLOBALS['__hr_test_dbs'][] = $db;
}

function makeClusterHR(): string
{
    $id = (string) Str::uuid();

    DB::table('root_clusters')->insert([
        'id' => $id,
        'name' => 'cell-'.$id,
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer-a.local',
        'app_db_user' => 'corex',
    ]);

    return $id;
}

function makeIdentityHR(): string
{
    $id = (string) Str::uuid();

    DB::table('root_identities')->insert([
        'id' => $id,
        'email' => $id.'@example.test',
    ]);

    return $id;
}

/** @return array{0: Account, 1: ?Domain} */
function makeAccountWithDomainHR(?string $host, array $accountOverrides = [], array $domainOverrides = []): array
{
    $dbName = 'acc_hr_'.Str::lower(Str::random(8)).'_test';
    trackDbHR($dbName);
    DB::statement('DROP DATABASE IF EXISTS '.$dbName);
    DB::statement('CREATE DATABASE '.$dbName);

    $account = Account::on('pgsql')->create(array_merge([
        'cluster_id' => makeClusterHR(),
        'db_name' => $dbName,
        'slug' => 'acc-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'owner_identity_id' => makeIdentityHR(),
        'status' => 'active',
    ], $accountOverrides));

    $domain = null;

    if ($host !== null) {
        $domain = Domain::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'host' => $host,
            'type' => 'platform',
            'verified_at' => now(),
        ], $domainOverrides));
    }

    return [$account, $domain];
}

function resolverHR(): HostAccountResolver
{
    return app(HostAccountResolver::class);
}

function readerResolverHR(): HostAccountResolver
{
    $options = TenancyOptions::fromArray(array_replace(config('corex-tenancy'), [
        'central_connection' => 'host_reader',
    ]));

    return new HostAccountResolver(app('db'), app(CacheFactory::class), $options);
}

it('fences a host refilled by another connection before the writer commits', function (string $change): void {
    [$account, $domain] = makeAccountWithDomainHR('race.example.test');
    [$other] = makeAccountWithDomainHR(null);
    $reader = readerResolverHR();
    expect($reader->resolve('race.example.test'))->toBe($account->id);

    DB::connection('pgsql')->beginTransaction();
    match ($change) {
        'revoke' => $domain->update(['verified_at' => null]),
        'rename' => $domain->update(['host' => 'renamed.example.test']),
        'reassign' => $domain->update(['account_id' => $other->id, 'verified_at' => null]),
        'domain-delete' => $domain->delete(),
        'account-pending' => $account->update(['slug' => null, 'status' => 'pending']),
        'account-delete' => $account->delete(),
    };

    // A second PDO sees only the old committed row and may refill the shared cache.
    expect($reader->resolve('race.example.test'))->toBe($account->id);
    DB::connection('pgsql')->commit();

    expect(fn () => $reader->resolve('race.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
})->with(['revoke', 'rename', 'reassign', 'domain-delete', 'account-pending', 'account-delete']);

it('rejects a lookup whose cache publication arrives after revocation commits', function (): void {
    [$account, $domain] = makeAccountWithDomainHR('late.example.test');
    DB::connection('pgsql')->beginTransaction();
    $domain->update(['verified_at' => null]);

    $committed = false;
    app('events')->listen(WritingKey::class, function (WritingKey $event) use (&$committed): void {
        if (! $committed && $event->key === HostAccountResolver::key('late.example.test')) {
            $committed = true;
            DB::connection('pgsql')->commit();
        }
    });

    $reader = readerResolverHR();
    expect(fn () => $reader->resolve('late.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
    expect($committed)->toBeTrue();
    expect(fn () => $reader->resolve('late.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
});

it('never publishes an uncommitted verification into the shared host cache', function (): void {
    [$account, $domain] = makeAccountWithDomainHR('rollback.example.test', domainOverrides: ['verified_at' => null]);
    DB::connection('pgsql')->beginTransaction();
    $domain->update(['verified_at' => now()]);
    expect(resolverHR()->resolve('rollback.example.test'))->toBe($account->id);
    DB::connection('pgsql')->rollBack();

    expect(fn () => readerResolverHR()->resolve('rollback.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
});

it('bypasses a warmed cache inside a transaction and preserves it after rollback', function (): void {
    [$account, $domain] = makeAccountWithDomainHR('warm-rollback.example.test');
    $reader = readerResolverHR();
    expect($reader->resolve('warm-rollback.example.test'))->toBe($account->id);
    DB::connection('pgsql')->beginTransaction();
    $domain->update(['verified_at' => null]);
    expect(fn () => resolverHR()->resolve('warm-rollback.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
    DB::connection('pgsql')->rollBack();
    expect($reader->resolve('warm-rollback.example.test'))->toBe($account->id);
});

it('rejects late stale entries even if their generation was evicted', function (): void {
    [$account, $domain] = makeAccountWithDomainHR('evicted.example.test');
    $reader = readerResolverHR();
    expect($reader->resolve('evicted.example.test'))->toBe($account->id);
    $store = app(CacheFactory::class)->store('host_file');
    $oldEntry = $store->get(HostAccountResolver::key('evicted.example.test'));
    $domain->update(['verified_at' => null]);
    $store->put(HostAccountResolver::key('evicted.example.test'), $oldEntry, 300);
    $store->forget(HostAccountResolver::generationKey());

    expect(fn () => $reader->resolve('evicted.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
});

/** Runs the host middleware and returns the account id that was active INSIDE the request. */
function accountSeenByHostMiddlewareHR(string $host, array $server = []): ?string
{
    $seen = null;
    app(ResolveTenantFromHost::class)->handle(
        Request::create('http://'.$host.'/', 'GET', server: $server),
        function (Request $request) use (&$seen): Response {
            $seen = app(TenancyManager::class)->context()?->account->id;

            return new Response('ok');
        },
    );

    return $seen;
}

it('а: неизвестный host → отказ резолва (наш exception)', function (): void {
    expect(fn () => resolverHR()->resolve('ghost.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
});

it('б: host аккаунта с slug IS NULL (pending-болванка) → отказ (§7.3 п.9)', function (): void {
    [$account] = makeAccountWithDomainHR(
        'pending.example.test',
        accountOverrides: ['status' => 'pending', 'slug' => null],
    );

    expect(fn () => resolverHR()->resolve('pending.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
});

it('в: host с verified_at IS NULL → отказ', function (): void {
    makeAccountWithDomainHR('unverified.example.test', domainOverrides: ['verified_at' => null]);

    expect(fn () => resolverHR()->resolve('unverified.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
});

it('г: deleted_at у домена → отказ', function (): void {
    makeAccountWithDomainHR('gone-domain.example.test', domainOverrides: ['deleted_at' => now()]);

    expect(fn () => resolverHR()->resolve('gone-domain.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
});

it('г: deleted_at у аккаунта → отказ', function (): void {
    makeAccountWithDomainHR('gone-account.example.test', accountOverrides: ['deleted_at' => now()]);

    expect(fn () => resolverHR()->resolve('gone-account.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
});

it('д: валидный host → внутри запроса контекст account активен, после ответа — закрыт', function (): void {
    [$account] = makeAccountWithDomainHR('acme.example.test');

    expect(accountSeenByHostMiddlewareHR('acme.example.test'))->toBe($account->id)
        ->and(app(TenancyManager::class)->initialized())->toBeFalse()
        ->and(DB::getDefaultConnection())->toBe('pgsql');
});

it('е: второй запрос по тому же host делает 0 запросов в central-коннекшн (кеш-хит)', function (): void {
    [$account] = makeAccountWithDomainHR('cached.example.test');

    $resolver = resolverHR();
    $resolver->resolve('cached.example.test'); // primes the cache

    DB::connection('pgsql')->flushQueryLog();
    DB::connection('pgsql')->enableQueryLog();

    $resolved = $resolver->resolve('cached.example.test');

    expect($resolved)->toBe($account->id)
        ->and(DB::connection('pgsql')->getQueryLog())->toHaveCount(0);
});

it('ж: смена домена → старый host больше не резолвится СРАЗУ (без ожидания TTL)', function (): void {
    [$account, $oldDomain] = makeAccountWithDomainHR('old.example.test');

    $resolver = resolverHR();
    expect($resolver->resolve('old.example.test'))->toBe($account->id); // primes the cache

    // Retiring a domain (soft-delete, D122/§7.3 п.9 semantics) — NOT an
    // in-place rename of `host`: InvalidatesTenantsResolverCache invalidates
    // via the account's CURRENT `domains` set, which still surfaces a
    // soft-deleted row (the relation is unscoped) as long as its `host`
    // column itself is untouched.
    $oldDomain->deleted_at = now();
    $oldDomain->save();

    Domain::query()->create([
        'id' => (string) Str::uuid(),
        'account_id' => $account->id,
        'host' => 'new.example.test',
        'type' => 'platform',
        'verified_at' => now(),
    ]);

    expect(fn () => resolverHR()->resolve('old.example.test'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);

    expect(resolverHR()->resolve('new.example.test'))->toBe($account->id);
});

it('з: два аккаунта × два домена, чередование запросов → 0 пересечений (AC-30)', function (): void {
    [$accountA] = makeAccountWithDomainHR('tenant-a.example.test');
    [$accountB] = makeAccountWithDomainHR('tenant-b.example.test');

    for ($i = 0; $i < 25; $i++) {
        expect(accountSeenByHostMiddlewareHR('tenant-a.example.test'))->toBe($accountA->id);
        expect(accountSeenByHostMiddlewareHR('tenant-b.example.test'))->toBe($accountB->id);
    }
});

it('и: следующий host всегда читается из central после инициализации другого account', function (): void {
    [$accountA] = makeAccountWithDomainHR('first.example.test');
    [$accountB] = makeAccountWithDomainHR('second.example.test');

    initTenantContext($accountA);

    expect(resolverHR()->resolve('second.example.test'))->toBe($accountB->id);
});

it('й: подделанные X-Account-Id/X-Forwarded-Host не влияют на резолв', function (): void {
    [$account] = makeAccountWithDomainHR('real-host.example.test');
    makeAccountWithDomainHR('evil.example.test');

    $seen = accountSeenByHostMiddlewareHR('real-host.example.test', [
        'HTTP_X_ACCOUNT_ID' => 'fake-account-id',
        'HTTP_X_FORWARDED_HOST' => 'evil.example.test',
    ]);

    expect($seen)->toBe($account->id);
});
