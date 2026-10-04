<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveTenantFromHost;
use CoreX\Tenancy\Laravel\Http\Middleware\ScopeSessionToTenant;
use CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
    endTenantContext();
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

/** Runs the middleware with the given session and returns the HTTP status it ended with. */
function scopeStatusTSS(Store $session): int
{
    $request = Request::create('http://tenant.example.test/', 'GET');
    $request->setLaravelSession($session);

    try {
        $response = app(ScopeSessionToTenant::class)->handle($request, fn (Request $r) => new Response('ok'));
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }

    return $response->getStatusCode();
}

it('а: sessions создаётся тенантской миграцией; уже созданный handler переезжает в БД аккаунта, не в root', function (): void {
    $account = makeAccountTSS();

    /** @var Store $driver */
    $driver = app('session')->driver('database');
    $central = $driver->getHandler();   // built BEFORE the account context existed

    initTenantContext($account);
    migrateTenantSessionsTSS();

    expect(Schema::connection('tenant')->hasTable('sessions'))->toBeTrue()
        ->and(Schema::connection('pgsql')->hasTable('sessions'))->toBeFalse()
        ->and($driver->getHandler())->not->toBe($central);

    // Store::setId() silently discards a malformed id (fails its own
    // isValidId() regex — 40 alphanumeric chars) and generates a random one
    // instead, so the id actually written has to be read back via getId().
    $driver->put('probe', 'value');
    $driver->save();

    expect(DB::connection('tenant')->table('sessions')->where('id', $driver->getId())->exists())->toBeTrue();

    endTenantContext();

    // revert: the very same Store goes back to a central handler, nothing is deleted
    expect($driver->getHandler())->toBe($central)
        ->and(config('session.connection'))->toBeNull()
        ->and(config('database.connections.tenant'))->toBeNull(); // the last account's route is not left behind
});

it('б: cookie-сессия тенанта A на домене тенанта B → 403 и invalidate (F19)', function (): void {
    $accountA = makeAccountTSS();
    $accountB = makeAccountTSS();

    $session = new Store('tenant_scope_probe', new FileSessionHandler(app('files'), config('session.files'), 120));
    $session->start();

    initTenantContext($accountA);
    expect(scopeStatusTSS($session))->toBe(200)
        ->and($session->get('corex.tenancy.account_id'))->toBe($accountA->id);
    $session->put('login_web_x', 'user-a');

    endTenantContext();
    initTenantContext($accountB);

    expect(scopeStatusTSS($session))->toBe(403)
        ->and($session->get('login_web_x'))->toBeNull()
        ->and($session->get('corex.tenancy.account_id'))->toBeNull();
});

it('г: первая привязка сбрасывает чужие атрибуты (central/auth) и ротирует токен', function (): void {
    $account = makeAccountTSS();
    $session = new Store('tenant_scope_probe', new FileSessionHandler(app('files'), config('session.files'), 120));
    $session->start();
    $session->put('login_web_central', 'central-user');
    $session->regenerateToken();
    $token = $session->token();

    initTenantContext($account);

    expect(scopeStatusTSS($session))->toBe(200)
        ->and($session->get('login_web_central'))->toBeNull()
        ->and($session->token())->not->toBe($token)
        ->and($session->get('corex.tenancy.account_id'))->toBe($account->id);
});

it('д: сессия того же аккаунта сохраняет вход', function (): void {
    $account = makeAccountTSS();
    $session = new Store('tenant_scope_probe', new FileSessionHandler(app('files'), config('session.files'), 120));
    $session->start();

    initTenantContext($account);
    expect(scopeStatusTSS($session))->toBe(200);
    $session->put('login_web_x', 'user-a');

    expect(scopeStatusTSS($session))->toBe(200)
        ->and($session->get('login_web_x'))->toBe('user-a');
});

it('е: без контекста аккаунта middleware отказывает типизированной ошибкой', function (): void {
    $session = new Store('tenant_scope_probe', new FileSessionHandler(app('files'), config('session.files'), 120));

    expect(fn () => scopeStatusTSS($session))->toThrow(TenantContextUnavailable::class);
    expect(app(TenancyManager::class)->initialized())->toBeFalse();
});

it('в: host_identification.enabled=false (boxed) → middleware не зарегистрирована, приложение поднимается без root-БД', function (): void {
    // Our contribution to the tenant group and the aliases this package owns outright stay
    // absent under the flag.
    expect(config('tenancy.host_identification.enabled'))->toBeFalse()
        ->and(app('router')->getMiddlewareGroups()['tenant'] ?? [])->not->toContain(ResolveTenantFromHost::class)
        ->and(app('router')->getMiddleware())->not->toHaveKey('tenancy.host')
        ->and(app('router')->getMiddleware())->not->toHaveKey('tenancy.status');
});
