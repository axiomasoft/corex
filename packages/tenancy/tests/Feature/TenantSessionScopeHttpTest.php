<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\DomainTrust\Internal\Models\Domain;
use CoreX\Tenancy\Laravel\Http\Middleware\AccountStatusGate;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveTenantFromHost;
use CoreX\Tenancy\Laravel\Http\Middleware\ScopeSessionToTenant;
use CoreX\Tenancy\Runtime\Exceptions\TenantCouldNotBeIdentifiedByHostException;
use CoreX\Tenancy\Tests\Support\TestTenantHost;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;

uses()->group('pg');

beforeAll(static function (): void {
    TestCase::$hostIdentificationEnabled = true;
    TestCase::$runtimeSessionEnabled = true;
});

afterAll(static function (): void {
    TestCase::$hostIdentificationEnabled = false;
    TestCase::$runtimeSessionEnabled = false;
});

/**
 * Full HTTP stack on the real kernel priority: ResolveTenantFromHost wraps StartSession, so the
 * session handler is built for the account and the session is saved inside it (PLAN3.D100).
 */
beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');

    $this->accounts = [];

    foreach (['a', 'b'] as $name) {
        $cluster = (string) Str::uuid();
        $identity = (string) Str::uuid();
        DB::table('root_clusters')->insert(['id' => $cluster, 'name' => $name, 'pg_host_writer' => 'writer.test', 'pgbouncer_host' => 'pool.test', 'app_db_user' => 'corex']);
        DB::table('root_identities')->insert(['id' => $identity, 'email' => $identity.'@example.test']);
        $account = Account::on('pgsql')->create(['cluster_id' => $cluster, 'db_name' => 'tss_http_'.$name.'_test', 'slug' => 'acc-'.$name, 'template_version' => 1, 'embedding_model' => 'bge-m3', 'embedding_dim' => 1024, 'owner_identity_id' => $identity, 'status' => 'active']);
        Domain::query()->create(['id' => (string) Str::uuid(), 'account_id' => $account->id, 'host' => $name.'.example.test', 'type' => 'platform', 'verified_at' => now()]);
        $this->accounts[$name] = $account->id;
    }

    Route::middleware([StartSession::class, 'tenant'])->group(function (): void {
        Route::get('/put/{value}', function (Request $request, string $value): array {
            $request->session()->put('login_web_probe', $value);

            return ['ok' => true];
        });
        Route::get('/get', fn (Request $request): array => [
            'login' => $request->session()->get('login_web_probe'),
            'bound' => $request->session()->get('corex.tenancy.account_id'),
            'account' => resolve(TenancyManager::class)->context()?->account->id,
            'database' => DB::connection()->getDatabaseName(),
        ]);
        Route::get('/boom', function (Request $request): never {
            $request->session()->put('login_web_probe', 'boom');

            throw new RuntimeException('downstream failure');
        });
    });
});

/** @return array{status: int, cookie: ?string, json: array<string, mixed>} */
function visitTSH(string $host, string $path, ?string $cookie = null): array
{
    $test = test();

    if ($cookie !== null) {
        $test->withUnencryptedCookie(config('session.cookie'), $cookie);
    }

    $response = $test->get('http://'.$host.$path);
    $set = collect($response->headers->getCookies())->first(fn ($c): bool => $c->getName() === config('session.cookie'));

    return ['status' => $response->getStatusCode(), 'cookie' => $set?->getValue(), 'json' => $response->headers->get('content-type') !== null && str_contains((string) $response->headers->get('content-type'), 'json') ? (array) $response->json() : []];
}

function tenantSessionDirTSH(string $accountId): string
{
    return resolve(TestTenantHost::class)->sessionRoot($accountId);
}

it('orders the assembled web+tenant route: host before StartSession, scope after it, gate next, errors/auth after', function (): void {
    resolve(Kernel::class); // the kernel syncs its priority list into the router when it is resolved
    $router = resolve('router');
    $route = Route::middleware([ShareErrorsFromSession::class, StartSession::class, 'tenant'])->get('/order', fn () => 'x');
    $order = $router->gatherRouteMiddleware($route);

    $index = static fn (string $class): int|false => array_search($class, $order, true);

    expect($index(ResolveTenantFromHost::class))->toBeLessThan($index(StartSession::class))
        ->and($index(StartSession::class))->toBeLessThan($index(ScopeSessionToTenant::class))
        ->and($index(ScopeSessionToTenant::class))->toBeLessThan($index(AccountStatusGate::class))
        ->and($index(AccountStatusGate::class))->toBeLessThan($index(ShareErrorsFromSession::class));
});

it('saves the session inside the account: tenant directory 0700 with a real file, nothing in the central one', function (): void {
    $first = visitTSH('a.example.test', '/put/secret');
    $dir = tenantSessionDirTSH($this->accounts['a']);

    expect($first['status'])->toBe(200)
        ->and($first['cookie'])->not->toBeNull()
        ->and(is_dir($dir))->toBeTrue()
        ->and(decoct(fileperms($dir) & 0777))->toBe('700')
        ->and(is_file($dir.'/'.$first['cookie']))->toBeTrue()
        ->and(is_file(rtrim((string) config('session.files'), '/').'/'.$first['cookie']))->toBeFalse();

    // read back and garbage-collect through the real handler of the account
    $again = visitTSH('a.example.test', '/get', $first['cookie']);
    expect($again['json']['login'])->toBe('secret')
        ->and($again['json']['account'])->toBe($this->accounts['a'])
        ->and($again['json']['database'])->toBe('tss_http_a_test');

});

it('keeps the login of the same account across requests', function (): void {
    $first = visitTSH('a.example.test', '/put/user-a');
    $second = visitTSH('a.example.test', '/get', $first['cookie']);

    expect($second['json']['login'])->toBe('user-a')->and($second['json']['bound'])->toBe($this->accounts['a']);
});

it('a cookie of account A on account B is denied with 403 and never opens a session there', function (): void {
    $first = visitTSH('a.example.test', '/put/user-a');
    $onB = visitTSH('b.example.test', '/get', $first['cookie']);

    expect($onB['status'])->toBe(200)         // B has no such session file: a fresh one, bound to B
        ->and($onB['json']['login'])->toBeNull()
        ->and($onB['json']['bound'])->toBe($this->accounts['b']);

    // the same id forced into B's directory (a copied cookie + file) is still refused by the marker
    $dirB = tenantSessionDirTSH($this->accounts['b']);
    copy(tenantSessionDirTSH($this->accounts['a']).'/'.$first['cookie'], $dirB.'/'.$first['cookie']);
    $forced = visitTSH('b.example.test', '/get', $first['cookie']);

    expect($forced['status'])->toBe(403);
});

it('a central session cookie does not carry an auth id into the account', function (): void {
    $central = rtrim((string) config('session.files'), '/');
    $id = Str::random(40);
    file_put_contents($central.'/'.$id, serialize(['login_web_probe' => 'central-user', '_token' => 'x']));

    $onA = visitTSH('a.example.test', '/get', $id);

    expect($onA['status'])->toBe(200)
        ->and($onA['json']['login'])->toBeNull()
        ->and($onA['json']['bound'])->toBe($this->accounts['a']);
});

it('a resident process does not carry attributes or the login from A into the next request for B', function (): void {
    visitTSH('a.example.test', '/put/user-a'); // leaves residual attributes on the long-lived Store in a resident worker
    $onB = visitTSH('b.example.test', '/get');

    expect($onB['json']['login'])->toBeNull()->and($onB['json']['account'])->toBe($this->accounts['b']);
});

it('after a downstream exception the next request starts clean and the context is closed', function (): void {
    expect(visitTSH('a.example.test', '/boom')['status'])->toBe(500);
    expect(resolve(TenancyManager::class)->initialized())->toBeFalse()
        ->and(DB::getDefaultConnection())->toBe('pgsql');

    $next = visitTSH('b.example.test', '/get');
    expect($next['json']['login'])->toBeNull()->and($next['json']['account'])->toBe($this->accounts['b']);
});

it('an unknown host is refused with the typed error before any session or context exists', function (): void {
    test()->withoutExceptionHandling();

    expect(fn () => test()->get('http://ghost.example.test/get'))
        ->toThrow(TenantCouldNotBeIdentifiedByHostException::class);
    expect(resolve(TenancyManager::class)->initialized())->toBeFalse()
        ->and(DB::getDefaultConnection())->toBe('pgsql');
});
