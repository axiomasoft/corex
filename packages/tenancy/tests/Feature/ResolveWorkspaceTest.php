<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Internal\NullTenancyManager;
use CoreX\Departments\Internal\Models\Department;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Identity\Internal\Models\User;
use CoreX\Tenancy\Laravel\Http\Middleware\ResolveWorkspace;
use CoreX\Tenancy\Laravel\Scopes\WorkspaceScope;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Workspace\Internal\Models\Workspace;
use CoreX\Tenancy\Workspace\Internal\Models\WorkspaceMember;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses()->group('pg');

const ROOT_MIGRATIONS_PATH_RW = __DIR__.'/../../database/migrations/root';
const TENANT_MIGRATIONS_PATH_RW = __DIR__.'/../../database/migrations/tenant';
const CORE_MIGRATIONS_PATH_RW = __DIR__.'/../../vendor/axiomasoft/corex/database/migrations/pg';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_RW),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    config(['tenancy.workspace.path_segment' => 'w']);
    app()->forgetInstance(TenancyOptions::class);
});

afterEach(function (): void {
    app(Tenancy::class)->end();
    DB::purge('pgsql');
    DB::purge('tenant');

    foreach ($GLOBALS['__rw_test_dbs'] ?? [] as $db) {
        DB::statement('DROP DATABASE IF EXISTS '.$db);
    }
    $GLOBALS['__rw_test_dbs'] = [];
});

function trackDbRW(string $db): void
{
    $GLOBALS['__rw_test_dbs'][] = $db;
}

function makeClusterRW(): string
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

function makeIdentityRW(): string
{
    $id = (string) Str::uuid();

    DB::table('root_identities')->insert(['id' => $id, 'email' => $id.'@example.test']);

    return $id;
}

/** Real per-account tenant DB (golden-template chain, D14/C4) — tenancy IS initialized on return. */
function initializedAccountRW(array $overrides = []): Account
{
    $dbName = 'acc_rw_'.Str::lower(Str::random(8));
    trackDbRW($dbName);
    DB::statement('DROP DATABASE IF EXISTS '.$dbName);
    DB::statement('CREATE DATABASE '.$dbName);

    $account = Account::on('pgsql')->create(array_merge([
        'cluster_id' => makeClusterRW(),
        'db_name' => $dbName,
        'slug' => 'acc-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'owner_identity_id' => makeIdentityRW(),
        'status' => 'active',
    ], $overrides));

    app(Tenancy::class)->initialize($account);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => realpath(CORE_MIGRATIONS_PATH_RW),
        '--realpath' => true,
        '--force' => true,
    ]);
    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => realpath(TENANT_MIGRATIONS_PATH_RW),
        '--realpath' => true,
        '--force' => true,
    ]);

    return $account;
}

function makeUserRW(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'id' => (string) Str::uuid7(),
        'email' => Str::random(8).'@example.test',
        'name' => 'Test User',
    ], $overrides));
}

function makeWorkspaceRW(array $overrides = []): Workspace
{
    $id = (string) Str::uuid7();

    // forceCreate — mirrors WorkspaceResolverTest's makeWorkspaceWsp (id is
    // deliberately absent from Workspace::$fillable).
    return Workspace::query()->forceCreate(array_merge([
        'id' => $id,
        'path' => str_replace('-', '', $id),
        'type' => 'company',
        'name' => 'Acme',
        'slug' => 'acme-'.Str::random(6),
    ], $overrides));
}

/** Swaps the `auth` container binding for a {@see Guard} stub — mirrors WorkspaceResolverTest's actingAsIdWsp(). */
function actingAsIdRW(?string $userId): void
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

function dispatchRW(Request $request): mixed
{
    return app(ResolveWorkspace::class)->handle($request, fn (Request $r): Response => new Response('ok', 200));
}

/** Runs a dispatch closure, collapsing an aborted HttpException into its status code. */
function statusOfRW(Closure $dispatch): int
{
    try {
        /** @var Response $response */
        $response = $dispatch();
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }

    return $response->getStatusCode();
}

it('а: тенанси НЕ инициализирована → 403, context() остаётся пуст', function (): void {
    initializedAccountRW();
    $workspace = makeWorkspaceRW();
    $user = makeUserRW();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => 'active']);
    actingAsIdRW($user->id);

    app(Tenancy::class)->end();

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$workspace->slug, 'GET'))))->toBe(403)
        ->and(app(TenancyManager::class)->context())->toBeNull();
});

it('б: guest (auth()->id() === null) → 403 даже при активном членстве ДРУГОГО юзера', function (): void {
    initializedAccountRW();
    $workspace = makeWorkspaceRW();
    $otherUser = makeUserRW();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $otherUser->id, 'status' => 'active']);
    actingAsIdRW(null);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$workspace->slug, 'GET'))))->toBe(403);
});

it('в: не-член существующего workspace → 403', function (): void {
    initializedAccountRW();
    $workspace = makeWorkspaceRW();
    $user = makeUserRW();
    actingAsIdRW($user->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$workspace->slug, 'GET'))))->toBe(403);
});

it('г: член со status=suspended → 403', function (): void {
    initializedAccountRW();
    $workspace = makeWorkspaceRW();
    $user = makeUserRW();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => 'suspended']);
    actingAsIdRW($user->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$workspace->slug, 'GET'))))->toBe(403);
});

it('д: несуществующий slug → 403 (НЕ 404)', function (): void {
    initializedAccountRW();
    $user = makeUserRW();
    actingAsIdRW($user->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/does-not-exist', 'GET'))))->toBe(403);
});

it('е: путь /w без следующего сегмента → 403', function (): void {
    initializedAccountRW();
    $user = makeUserRW();
    actingAsIdRW($user->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w', 'GET'))))->toBe(403);
});

it('ж: partner получает свой контекст (200), /w/{parent-slug} → 403 (AC-18)', function (): void {
    initializedAccountRW();
    $parent = makeWorkspaceRW(['type' => 'company']);
    $partner = makeWorkspaceRW(['type' => 'partner', 'parent_id' => $parent->id]);
    $user = makeUserRW();
    WorkspaceMember::query()->create(['workspace_id' => $partner->id, 'user_id' => $user->id, 'status' => 'active']);
    actingAsIdRW($user->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$partner->slug, 'GET'))))->toBe(200)
        ->and(app(TenancyManager::class)->context()?->workspace?->id)->toBe($partner->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$parent->slug, 'GET'))))->toBe(403);
});

it('з: нет default-членства, путь без маркера (/dashboard) → 403, не 500 (NoDefaultWorkspaceException поймано)', function (): void {
    initializedAccountRW();
    $user = makeUserRW();
    actingAsIdRW($user->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/dashboard', 'GET'))))->toBe(403);
});

it('и: happy /w/{slug}/deals → 200, context() = резолвленный workspace', function (): void {
    initializedAccountRW();
    $workspace = makeWorkspaceRW();
    $user = makeUserRW();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => 'active']);
    actingAsIdRW($user->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$workspace->slug.'/deals', 'GET'))))->toBe(200)
        ->and(app(TenancyManager::class)->context()?->workspace?->id)->toBe($workspace->id)
        ->and(app(TenancyManager::class)->context()?->workspace?->slug)->toBe($workspace->slug);
});

it('к: happy без маркера (/dashboard) при активном default-членстве → 200, контекст = default workspace', function (): void {
    initializedAccountRW();
    $workspace = makeWorkspaceRW();
    $user = makeUserRW();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => 'active', 'is_default' => true]);
    actingAsIdRW($user->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/dashboard', 'GET'))))->toBe(200)
        ->and(app(TenancyManager::class)->context()?->workspace?->id)->toBe($workspace->id);
});

it('л: маркер глубже в пути → тот же результат; маркер дважды → выбран ПЕРВЫЙ', function (): void {
    initializedAccountRW();
    $workspaceA = makeWorkspaceRW();
    $workspaceB = makeWorkspaceRW();
    $user = makeUserRW();
    WorkspaceMember::query()->create(['workspace_id' => $workspaceA->id, 'user_id' => $user->id, 'status' => 'active']);
    WorkspaceMember::query()->create(['workspace_id' => $workspaceB->id, 'user_id' => $user->id, 'status' => 'active']);
    actingAsIdRW($user->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/app/w/'.$workspaceA->slug.'/deals', 'GET'))))->toBe(200)
        ->and(app(TenancyManager::class)->context()?->workspace?->id)->toBe($workspaceA->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$workspaceA->slug.'/w/'.$workspaceB->slug, 'GET'))))->toBe(200)
        ->and(app(TenancyManager::class)->context()?->workspace?->id)->toBe($workspaceA->id);
});

it('м: кастомный маркер path_segment=ws — /ws/{slug} резолвит, /w/{slug} трактуется как «без маркера» (default)', function (): void {
    config(['tenancy.workspace.path_segment' => 'ws']);
    app()->forgetInstance(TenancyOptions::class);

    initializedAccountRW();
    $workspace = makeWorkspaceRW();
    $defaultWorkspace = makeWorkspaceRW();
    $user = makeUserRW();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => 'active']);
    WorkspaceMember::query()->create(['workspace_id' => $defaultWorkspace->id, 'user_id' => $user->id, 'status' => 'active', 'is_default' => true]);
    actingAsIdRW($user->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/ws/'.$workspace->slug, 'GET'))))->toBe(200)
        ->and(app(TenancyManager::class)->context()?->workspace?->id)->toBe($workspace->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$workspace->slug, 'GET'))))->toBe(200)
        ->and(app(TenancyManager::class)->context()?->workspace?->id)->toBe($defaultWorkspace->id);
});

it('н: post-condition — TenancyManager с no-op setWorkspace() → 403, не тихий проход', function (): void {
    $account = initializedAccountRW();
    $workspace = makeWorkspaceRW();
    $user = makeUserRW();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => 'active']);
    actingAsIdRW($user->id);

    $stubResolver = new class($account) implements TenantContextResolver
    {
        public function __construct(private readonly Account $account) {}

        public function current(): TenantContext
        {
            return new TenantContext(new AccountRef(
                id: (string) $this->account->getTenantKey(),
                slug: (string) $this->account->slug,
                status: (string) $this->account->status,
                features: [],
                limits: [],
            ));
        }
    };

    app()->instance(TenancyManager::class, new NullTenancyManager($stubResolver));

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$workspace->slug, 'GET'))))->toBe(403);
});

it('о: под резолвленным контекстом WorkspaceScope на sys_departments отдаёт только резолвленный workspace', function (): void {
    initializedAccountRW();
    $workspaceA = makeWorkspaceRW();
    $workspaceB = makeWorkspaceRW();
    $user = makeUserRW();
    WorkspaceMember::query()->create(['workspace_id' => $workspaceA->id, 'user_id' => $user->id, 'status' => 'active']);
    actingAsIdRW($user->id);

    $deptA = Department::query()->create(['workspace_id' => $workspaceA->id, 'name' => 'Dept A', 'path' => Str::random(10)]);
    $deptB = Department::query()->create(['workspace_id' => $workspaceB->id, 'name' => 'Dept B', 'path' => Str::random(10)]);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$workspaceA->slug, 'GET'))))->toBe(200);

    $visibleIds = Department::query()->withGlobalScope('workspace', new WorkspaceScope)->pluck('id')->all();

    expect($visibleIds)->toContain($deptA->id)
        ->and($visibleIds)->not->toContain($deptB->id);
});

it('п: алиас tenancy.workspace зарегистрирован (Router::getMiddleware()), но НЕ входит в группу tenant (AC-28)', function (): void {
    expect(config('tenancy.host_identification.enabled'))->toBeFalse()
        ->and(config('tenancy.oidc.enabled'))->toBeFalse()
        ->and(app('router')->getMiddleware())->toHaveKey('tenancy.workspace')
        ->and(app('router')->getMiddlewareGroups()['tenant'] ?? [])->not->toContain(ResolveWorkspace::class);
});

it('р: после tenancy()->end() context() = null (workspace не переживает запрос)', function (): void {
    initializedAccountRW();
    $workspace = makeWorkspaceRW();
    $user = makeUserRW();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => 'active']);
    actingAsIdRW($user->id);

    expect(statusOfRW(fn () => dispatchRW(Request::create('http://x/w/'.$workspace->slug, 'GET'))))->toBe(200);

    app(Tenancy::class)->end();

    expect(app(TenancyManager::class)->context())->toBeNull();
});
