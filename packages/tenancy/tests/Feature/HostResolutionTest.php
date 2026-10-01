<?php

declare(strict_types=1);

use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\DomainTrust\Internal\Models\Domain;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveTenantFromHost;
use CoreX\Tenancy\Laravel\Resolvers\HostTenantResolver;
use CoreX\Tenancy\Runtime\Exceptions\TenantCouldNotBeIdentifiedByHostException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Tenancy;

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

    // HostTenantResolver's own cache subtree — the projection
    // TenancyServiceProvider::boot() would perform under
    // `host_identification.enabled`, set directly here since these tests
    // call the resolver/middleware without going through the router.
    config(['tenancy.identification.resolvers.'.HostTenantResolver::class => [
        'cache' => true,
        'cache_ttl' => 300,
        'cache_store' => null,
    ]]);
});

afterEach(function (): void {
    app(Tenancy::class)->end();
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
    $dbName = 'acc_hr_'.Str::lower(Str::random(8));
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

function resolverHR(): HostTenantResolver
{
    return app(HostTenantResolver::class);
}

function dispatchHostMiddlewareHR(string $host): mixed
{
    return app(ResolveTenantFromHost::class)->handle(
        Request::create('http://'.$host.'/', 'GET'),
        fn (Request $request): Response => new Response('ok'),
    );
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

it('д: валидный host → tenancy()->initialized === true и tenant->id ожидаемый', function (): void {
    [$account] = makeAccountWithDomainHR('acme.example.test');

    $response = dispatchHostMiddlewareHR('acme.example.test');

    expect($response->getContent())->toBe('ok')
        ->and(tenancy()->initialized)->toBeTrue()
        ->and(tenancy()->tenant->id)->toBe($account->id);
});

it('е: второй запрос по тому же host делает 0 запросов в central-коннекшн (кеш-хит)', function (): void {
    [$account] = makeAccountWithDomainHR('cached.example.test');

    $resolver = resolverHR();
    $resolver->resolve('cached.example.test'); // primes the cache

    DB::connection('pgsql')->flushQueryLog();
    DB::connection('pgsql')->enableQueryLog();

    $resolved = $resolver->resolve('cached.example.test');

    expect($resolved->id)->toBe($account->id)
        ->and(DB::connection('pgsql')->getQueryLog())->toHaveCount(0);
});

it('ж: смена домена → старый host больше не резолвится СРАЗУ (без ожидания TTL)', function (): void {
    [$account, $oldDomain] = makeAccountWithDomainHR('old.example.test');

    $resolver = resolverHR();
    expect($resolver->resolve('old.example.test')->id)->toBe($account->id); // primes the cache

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

    expect(resolverHR()->resolve('new.example.test')->id)->toBe($account->id);
});

it('з: два аккаунта × два домена, чередование запросов → 0 пересечений (AC-30)', function (): void {
    [$accountA] = makeAccountWithDomainHR('tenant-a.example.test');
    [$accountB] = makeAccountWithDomainHR('tenant-b.example.test');

    for ($i = 0; $i < 25; $i++) {
        dispatchHostMiddlewareHR('tenant-a.example.test');
        expect(tenancy()->tenant->id)->toBe($accountA->id);
        app(Tenancy::class)->end();

        dispatchHostMiddlewareHR('tenant-b.example.test');
        expect(tenancy()->tenant->id)->toBe($accountB->id);
        app(Tenancy::class)->end();
    }
});

it('и: следующий host всегда читается из central после инициализации другого account', function (): void {
    [$accountA] = makeAccountWithDomainHR('first.example.test');
    [$accountB] = makeAccountWithDomainHR('second.example.test');

    tenancy()->initialize($accountA);

    expect(resolverHR()->resolve('second.example.test')->id)->toBe($accountB->id);
});

it('й: подделанные X-Account-Id/X-Forwarded-Host не влияют на резолв', function (): void {
    [$account] = makeAccountWithDomainHR('real-host.example.test');
    makeAccountWithDomainHR('evil.example.test');

    $request = Request::create('http://real-host.example.test/', 'GET', server: [
        'HTTP_X_ACCOUNT_ID' => 'fake-account-id',
        'HTTP_X_FORWARDED_HOST' => 'evil.example.test',
    ]);

    app(ResolveTenantFromHost::class)->handle($request, fn (Request $r): Response => new Response('ok'));

    expect(tenancy()->tenant->id)->toBe($account->id);
});
