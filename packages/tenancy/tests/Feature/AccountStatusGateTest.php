<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\DomainTrust\Internal\Models\Domain;
use CoreX\Tenancy\Laravel\Http\Middleware\AccountStatusGate;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveTenantFromHost;
use CoreX\Tenancy\Laravel\Support\StatusGatePolicy;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses()->group('pg');

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');

    config([
        'corex-tenancy.status_gate.payment_route' => '/billing',
        'corex-tenancy.status_gate.export_route' => '/export',
        'corex-tenancy.status_gate.read_only_methods' => ['GET', 'HEAD', 'OPTIONS'],
        'corex-tenancy.host_identification.enabled' => true,
        'corex-tenancy.host_identification.cache' => true,
        'corex-tenancy.host_identification.cache_store' => null,
    ]);
    app()->forgetInstance(TenancyOptions::class);
});

afterEach(function (): void {
    endTenantContext();
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

    initTenantContext($account);

    return $account;
}

/** Swaps the `auth` container binding for a {@see Guard} stub — mirrors WorkspaceResolverTest's actingAsIdWsp(). */
function actingAsIdASG(?string $userId): void
{
    $guard = new class($userId) implements Guard
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
    };

    app()->instance('auth', new class($guard) implements AuthFactory
    {
        public function __construct(private readonly Guard $guard) {}

        public function guard($name = null): Guard
        {
            return $this->guard;
        }

        public function shouldUse($name): void {}
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

function gateASG(?AccountRead $accounts = null, ?TenantContextResolver $contexts = null): AccountStatusGate
{
    return new AccountStatusGate(
        statusGate: app(StatusGatePolicy::class),
        contexts: $contexts ?? app(TenantContextResolver::class),
        accounts: $accounts ?? app(AccountRead::class),
        auth: app('auth'),
        centralConnection: 'pgsql',
    );
}

function dispatchGateASG(Request $request, ?AccountStatusGate $gate = null): mixed
{
    return ($gate ?? gateASG())->handle($request, fn (Request $r): Response => new Response('ok', 200));
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
    actingAsIdASG($account->owner_identity_id);
    // A status the DB CHECK forbids persisting exists only on the in-memory context.
    $contexts = new class(new AccountRef(id: $account->id, slug: (string) $account->slug, status: $status, features: [], limits: [])) implements TenantContextResolver
    {
        public function __construct(private readonly AccountRef $account) {}

        public function current(): TenantContext
        {
            return new TenantContext($this->account);
        }
    };

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/', 'GET'), gateASG(contexts: $contexts))))->toBe(403);
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
        fn (Request $r): mixed => gateASG()->handle($r, fn (Request $rr): Response => new Response('ok', 200)),
    );

    actingAsIdASG(null);

    expect(statusOfASG(fn () => $pipeline(Request::create('http://status-flip.example.test/', 'GET'))))->toBe(200);
    endTenantContext();

    $account->status = 'suspended';
    $account->save(); // status is read per request from the central row, never cached with the host entry

    expect(statusOfASG(fn () => $pipeline(Request::create('http://status-flip.example.test/', 'GET'))))->toBe(403);
});

/** @param list<array{string, ?string}> $calls */
function recordingAccountReadASG(array &$calls, ?string $owner): AccountRead
{
    return new class($calls, $owner, app(AccountRead::class)) implements AccountRead
    {
        /** @param list<array{string, ?string}> $calls */
        public function __construct(private array &$calls, private readonly ?string $owner, private readonly AccountRead $inner) {}

        public function ownerIdentityId(string $accountId, ?string $connectionName = null): ?string
        {
            $this->calls[] = [$accountId, $connectionName];

            return $this->owner;
        }

        public function find(string $accountId, ?string $connectionName = null): ?AccountRef
        {
            return $this->inner->find($accountId, $connectionName);
        }
    };
}

it('читает владельца через AccountRead явно в central, пока default-соединение — tenant', function (): void {
    $account = initializedAccountASG('suspended');
    actingAsIdASG($account->owner_identity_id);
    $calls = [];
    $gate = gateASG(accounts: recordingAccountReadASG($calls, $account->owner_identity_id));

    expect(DB::getDefaultConnection())->toBe('tenant')
        ->and(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/billing', 'GET'), $gate)))->toBe(200)
        ->and($calls)->toBe([[$account->id, 'pgsql']]);
});

it('аккаунт без владельца никого не пропускает: suspended на payment-route и grace на GET → 403', function (string $status, string $path): void {
    $account = initializedAccountASG($status);
    actingAsIdASG((string) Str::uuid());
    $calls = [];
    $gate = gateASG(accounts: recordingAccountReadASG($calls, null));

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x'.$path, 'GET'), $gate)))->toBe(403);
})->with([['suspended', '/billing'], ['grace', '/anything']]);

it('неаутентифицированный пользователь не владелец, даже если владелец не задан', function (): void {
    $account = initializedAccountASG('grace');
    actingAsIdASG(null);
    $calls = [];
    $gate = gateASG(accounts: recordingAccountReadASG($calls, null));

    expect(statusOfASG(fn () => dispatchGateASG(Request::create('http://x/anything', 'GET'), $gate)))->toBe(403)
        ->and($calls)->toBe([]);
});

it('HttpRegistrar собирает AccountStatusGate с пятью зависимостями и central-соединением из options', function (): void {
    $gate = app(AccountStatusGate::class);
    $constructor = (new ReflectionClass(AccountStatusGate::class))->getConstructor();
    $central = (new ReflectionProperty($gate, 'centralConnection'))->getValue($gate);

    expect($constructor->getNumberOfRequiredParameters())->toBe(5)
        ->and($central)->toBe(app(TenancyOptions::class)->centralConnection);
});
