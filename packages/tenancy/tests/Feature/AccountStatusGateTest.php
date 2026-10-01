<?php

declare(strict_types=1);

use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\DomainTrust\Internal\Models\Domain;
use CoreX\Tenancy\Laravel\Http\Middleware\AccountStatusGate;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveTenantFromHost;
use CoreX\Tenancy\Laravel\Resolvers\HostTenantResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses()->group('pg');

const ROOT_MIGRATIONS_PATH_ASG = __DIR__.'/../../database/migrations/root';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_ASG),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    config([
        'tenancy.status_gate.payment_route' => '/billing',
        'tenancy.status_gate.export_route' => '/export',
        'tenancy.status_gate.read_only_methods' => ['GET', 'HEAD', 'OPTIONS'],
        'tenancy.identification.resolvers.'.HostTenantResolver::class => [
            'cache' => true,
            'cache_ttl' => 300,
            'cache_store' => null,
        ],
    ]);
});

afterEach(function (): void {
    app(Tenancy::class)->end();
    DB::purge('pgsql');
    DB::purge('tenant');

    foreach ($GLOBALS['__asg_test_dbs'] ?? [] as $db) {
        DB::statement('DROP DATABASE IF EXISTS '.$db);
    }
    $GLOBALS['__asg_test_dbs'] = [];
});

function trackDbASG(string $db): void
{
    $GLOBALS['__asg_test_dbs'][] = $db;
}

function makeClusterASG(): string
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

function makeIdentityASG(): string
{
    $id = (string) Str::uuid();

    DB::table('root_identities')->insert(['id' => $id, 'email' => $id.'@example.test']);

    return $id;
}

/** Creates an Account with a real (empty) tenant DB and initializes tenancy for it. */
function initializedAccountASG(string $status, array $overrides = []): Account
{
    $dbName = 'acc_asg_'.Str::lower(Str::random(8));
    trackDbASG($dbName);
    DB::statement('DROP DATABASE IF EXISTS '.$dbName);
    DB::statement('CREATE DATABASE '.$dbName);

    $account = Account::on('pgsql')->create(array_merge([
        'cluster_id' => makeClusterASG(),
        'db_name' => $dbName,
        'slug' => 'acc-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'owner_identity_id' => makeIdentityASG(),
        'status' => $status,
    ], $overrides));

    app(Tenancy::class)->initialize($account);

    return $account;
}

/** Swaps the `auth` container binding for a {@see Guard} stub — mirrors WorkspaceResolverTest's actingAsIdWsp(). */
function actingAsIdASG(?string $userId): void
{
    app()->instance('auth', new class($userId) implements Guard
    {
        public function __construct(private readonly ?string $id) {}

        public function check(): bool
        {
            return $this->id !== null;
        }

        public function guest(): bool
        {
            return $this->id === null;
        }

        public function user(): ?Authenticatable
        {
            return null;
        }

        public function id(): ?string
        {
            return $this->id;
        }

        public function validate(array $credentials = []): bool
        {
            return false;
        }

        public function hasUser(): bool
        {
            return $this->id !== null;
        }

        public function setUser(Authenticatable $user): static
        {
            return $this;
        }
    });
}

/** Runs a dispatch closure, collapsing an aborted HttpException into its status code. */
function statusOfASG(Closure $dispatch): int
{
    try {
        /** @var Response $response */
        $response = $dispatch();
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }

    return $response->getStatusCode();
}

function dispatchGateASG(Request $request): mixed
{
    return (new AccountStatusGate)->handle($request, fn (Request $r): Response => new Response('ok', 200));
}

it('trial → 200', function (): void {
    $account = initializedAccountASG('trial');
    actingAsIdASG(null);

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/', 'GET'))))->toBe(200);
});

it('active → 200', function (): void {
    $account = initializedAccountASG('active');
    actingAsIdASG(null);

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/', 'GET'))))->toBe(200);
});

it('suspended + не владелец → 403', function (): void {
    $account = initializedAccountASG('suspended');
    actingAsIdASG((string) Str::uuid()); // authenticated, but not the owner

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/billing', 'GET'))))->toBe(403);
});

it('suspended + владелец на payment-route → 200', function (): void {
    $account = initializedAccountASG('suspended');
    actingAsIdASG($account->owner_identity_id);

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/billing', 'GET'))))->toBe(200);
});

it('suspended + владелец вне payment-route → 403', function (): void {
    $account = initializedAccountASG('suspended');
    actingAsIdASG($account->owner_identity_id);

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/dashboard', 'GET'))))->toBe(403);
});

it('grace + владелец + GET → 200', function (): void {
    $account = initializedAccountASG('grace');
    actingAsIdASG($account->owner_identity_id);

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/anything', 'GET'))))->toBe(200);
});

it('grace + владелец + POST → 403', function (): void {
    $account = initializedAccountASG('grace');
    actingAsIdASG($account->owner_identity_id);

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/anything', 'POST'))))->toBe(403);
});

it('grace + владелец + POST на export-route → 200', function (): void {
    $account = initializedAccountASG('grace');
    actingAsIdASG($account->owner_identity_id);

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/export', 'POST'))))->toBe(200);
});

it('exporting → 410', function (): void {
    $account = initializedAccountASG('exporting');
    actingAsIdASG($account->owner_identity_id);

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/', 'GET'))))->toBe(410);
});

it('deleted → 410', function (): void {
    $account = initializedAccountASG('deleted');
    actingAsIdASG($account->owner_identity_id);

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/', 'GET'))))->toBe(410);
});

it('pending/provisioning/выдуманный статус → 403 (fail-closed)', function (string $status): void {
    $account = initializedAccountASG('trial'); // CHECK constraint allows only pending/provisioning with slug=null
    tenancy()->tenant->status = $status; // in-memory only — DB CHECK forbids persisting an unknown status
    actingAsIdASG($account->owner_identity_id);

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/', 'GET'))))->toBe(403);
})->with(['pending', 'provisioning', 'bogus-status']);

it('смена status у аккаунта → следующий запрос видит НОВОЕ правило без Cache::flush()', function (): void {
    $dbName = 'acc_asg_'.Str::lower(Str::random(8));
    trackDbASG($dbName);
    DB::statement('DROP DATABASE IF EXISTS '.$dbName);
    DB::statement('CREATE DATABASE '.$dbName);

    $account = Account::on('pgsql')->create([
        'cluster_id' => makeClusterASG(),
        'db_name' => $dbName,
        'slug' => 'acc-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'owner_identity_id' => makeIdentityASG(),
        'status' => 'active',
    ]);

    Domain::query()->create([
        'id' => (string) Str::uuid(),
        'account_id' => $account->id,
        'host' => 'status-flip.example.test',
        'type' => 'platform',
        'verified_at' => now(),
    ]);

    $pipeline = fn (Request $request): mixed => app(ResolveTenantFromHost::class)->handle(
        $request,
        fn (Request $r): mixed => (new AccountStatusGate)->handle($r, fn (Request $rr): Response => new Response('ok', 200)),
    );

    actingAsIdASG(null);

    expect(statusOfASG(fn () => $pipeline(Request::create('http://status-flip.example.test/', 'GET'))))->toBe(200);
    app(Tenancy::class)->end();

    $account->status = 'suspended';
    $account->save(); // InvalidatesResolverCache (inherited from base Tenant, D119) busts the resolver's cache

    expect(statusOfASG(fn () => $pipeline(Request::create('http://status-flip.example.test/', 'GET'))))->toBe(403);
});
