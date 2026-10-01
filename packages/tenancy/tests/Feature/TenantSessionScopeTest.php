<?php

declare(strict_types=1);

use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveTenantFromHost;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\ScopeSessions;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses()->group('pg');

const ROOT_MIGRATIONS_PATH_TSS = __DIR__.'/../../database/migrations/root';
const TENANT_MIGRATIONS_PATH_TSS = __DIR__.'/../../database/migrations/tenant';
const CORE_MIGRATIONS_PATH_TSS = __DIR__.'/../../vendor/axiomasoft/corex/database/migrations/pg';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_TSS),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    config(['session.driver' => 'database']);
});

afterEach(function (): void {
    app(Tenancy::class)->end();
    DB::purge('pgsql');
    DB::purge('tenant');

    foreach ($GLOBALS['__tss_test_dbs'] ?? [] as $db) {
        DB::statement('DROP DATABASE IF EXISTS '.$db);
    }
    $GLOBALS['__tss_test_dbs'] = [];
});

function trackDbTSS(string $db): void
{
    $GLOBALS['__tss_test_dbs'][] = $db;
}

function makeClusterTSS(): string
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

function makeIdentityTSS(): string
{
    $id = (string) Str::uuid();

    DB::table('root_identities')->insert(['id' => $id, 'email' => $id.'@example.test']);

    return $id;
}

/** Creates an Account with a real (empty) tenant DB — tenancy is NOT initialized here. */
function makeAccountTSS(array $overrides = []): Account
{
    $dbName = 'acc_tss_'.Str::lower(Str::random(8));
    trackDbTSS($dbName);
    DB::statement('DROP DATABASE IF EXISTS '.$dbName);
    DB::statement('CREATE DATABASE '.$dbName);

    return Account::on('pgsql')->create(array_merge([
        'cluster_id' => makeClusterTSS(),
        'db_name' => $dbName,
        'slug' => 'acc-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'owner_identity_id' => makeIdentityTSS(),
        'status' => 'active',
    ], $overrides));
}

/** Golden-template chain (D14/C4, mirrors WorkspaceResolverTest) — the tenant `sessions`
 *  migration coexists with `users`/`wsp_*`, whose cross-package FK migration needs
 *  axiomasoft/corex's `sys_departments` to already exist on the same (now-switched) connection. */
function migrateTenantSessionsTSS(): void
{
    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => realpath(CORE_MIGRATIONS_PATH_TSS),
        '--realpath' => true,
        '--force' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => realpath(TENANT_MIGRATIONS_PATH_TSS),
        '--realpath' => true,
        '--force' => true,
    ]);
}

it('а: sessions создаётся тенантской миграцией; сессия пишется в БД аккаунта, не в root', function (): void {
    $account = makeAccountTSS();
    app(Tenancy::class)->initialize($account);

    migrateTenantSessionsTSS();

    expect(Schema::connection('tenant')->hasTable('sessions'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('sessions'))->toBeFalse();

    /** @var Store $driver */
    $driver = app('session')->driver('database');
    // Store::setId() silently discards a malformed id (fails its own
    // isValidId() regex — 40 alphanumeric chars) and generates a random one
    // instead, so the id actually written has to be read back via getId().
    $driver->put('probe', 'value');
    $driver->save();

    expect(DB::connection('tenant')->table('sessions')->where('id', $driver->getId())->exists())->toBeTrue();
});

it('б: cookie-сессия тенанта A на домене тенанта B → 403 (ScopeSessions, F19)', function (): void {
    $accountA = makeAccountTSS();
    $accountB = makeAccountTSS();

    $session = new Store('tenant_scope_probe', new ArraySessionHandler(120));

    app(Tenancy::class)->initialize($accountA);
    $requestA = Request::create('http://tenant-a.example.test/', 'GET');
    $requestA->setLaravelSession($session);

    (new ScopeSessions)->handle($requestA, fn (Request $r) => new Response('ok'));
    expect($session->get(ScopeSessions::$tenantIdKey))->toBe($accountA->getTenantKey());

    app(Tenancy::class)->end();
    app(Tenancy::class)->initialize($accountB);
    $requestB = Request::create('http://tenant-b.example.test/', 'GET');
    $requestB->setLaravelSession($session); // same session object — simulates a shared cookie across domains

    $statusCode = null;

    try {
        (new ScopeSessions)->handle($requestB, fn (Request $r) => new Response('ok'));
    } catch (HttpException $e) {
        $statusCode = $e->getStatusCode();
    }

    expect($statusCode)->toBe(403);
});

it('в: host_identification.enabled=false (boxed) → middleware не зарегистрирована, приложение поднимается без root-БД', function (): void {
    // stancl's OWN provider unconditionally pre-registers an EMPTY 'tenant'
    // group as a route-context flag (DealsWithRouteContexts) — the key
    // itself always exists; what must stay absent under the flag is OUR
    // contribution to it, plus the two aliases this package owns outright.
    expect(config('tenancy.host_identification.enabled'))->toBeFalse()
        ->and(app('router')->getMiddlewareGroups()['tenant'] ?? [])->not->toContain(ResolveTenantFromHost::class)
        ->and(app('router')->getMiddleware())->not->toHaveKey('tenancy.host')
        ->and(app('router')->getMiddleware())->not->toHaveKey('tenancy.status');
});
