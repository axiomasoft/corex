<?php

declare(strict_types=1);

use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\ImpersonationGuard;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ImpersonationState;
use CoreX\Context\Enums\ImpersonationRestrictedAction;
use CoreX\Context\Exceptions\ImpersonationRestrictedException;
use CoreX\Tenancy\Impersonation\Internal\ImpersonatedModelWriteGuard;
use CoreX\Tenancy\Impersonation\Internal\Models\ImpersonationGrant;
use CoreX\Tenancy\Laravel\Http\Middleware\AllowImpersonatedWrites;
use CoreX\Tenancy\Laravel\Http\Middleware\DenyImpersonatedWrites;
use CoreX\Tenancy\Laravel\Http\Middleware\ImpersonationSessionGuard;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// L1/L2/L3 read-only invariants, tested directly
// against the middleware/listener/guard classes rather than through the
// package's own boot()-time conditional registration (that registration is
// gated by `corex-tenancy.oidc.impersonation.enabled`, which — like `oidc.enabled`
// itself, see TestCase::$oidcEnabled's docblock — would need to be true
// BEFORE this file's very first createApplication(); routes/listeners are
// wired here ad hoc instead so no TestCase change is needed). `state()` only
// ever reads the session (see DatabaseImpersonationService), so a plain
// FakeImpersonationService double stands in for it throughout — the real
// grant-claim path is packages/tenancy/tests/Feature/ImpersonationTest.php's
// concern, not this file's.
uses()->group('pg');

final class WriteGuardFakeImpersonationService implements ImpersonationService
{
    public function __construct(private ?ImpersonationState $fakeState = null) {}

    public function start(string $grantId, string $staffIdentityId): void {}

    public function stop(): void
    {
        $this->fakeState = null;
    }

    public function active(): ?string
    {
        return $this->fakeState?->grantId;
    }

    public function state(): ?ImpersonationState
    {
        return $this->fakeState;
    }

    public function setActive(?ImpersonationState $state): void
    {
        $this->fakeState = $state;
    }
}

final class ImpersonationGuardWriteStub extends Model
{
    protected $table = 'impersonation_guard_write_stubs';

    protected $guarded = [];

    public $timestamps = false;
}

function wgActiveState(): ImpersonationState
{
    return new ImpersonationState('grant-wg', 'staff-wg', 'target-wg', new DateTimeImmutable('+1 hour'));
}

beforeEach(function (): void {
    DB::connection('pgsql')->statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::connection('pgsql')->statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    Schema::connection('pgsql')->create('impersonation_guard_write_stubs', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
    });

    test()->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');

    test()->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');

    config(['queue.default' => 'sync']);
});

// ── L1 — HTTP layer (ч, ш) ──────────────────────────────────────────────

it('ч: L1 denies an unsafe method under impersonation without a route marker; GET passes', function (): void {
    $fake = new WriteGuardFakeImpersonationService(wgActiveState());
    app()->instance(ImpersonationService::class, $fake);

    Route::middleware([DenyImpersonatedWrites::class])->get('/im-wg-get', fn () => 'ok');
    Route::middleware([DenyImpersonatedWrites::class])->post('/im-wg-post', fn () => 'ok');

    test()->get('/im-wg-get')->assertOk();
    test()->post('/im-wg-post')->assertStatus(403);
});

it('ш: L1 permits an unsafe method carrying the AllowImpersonatedWrites marker', function (): void {
    $fake = new WriteGuardFakeImpersonationService(wgActiveState());
    app()->instance(ImpersonationService::class, $fake);

    Route::middleware([DenyImpersonatedWrites::class, AllowImpersonatedWrites::class])->post('/im-wg-post-allowed', fn () => 'ok');

    test()->post('/im-wg-post-allowed')->assertOk();
});

it('L1 passes every method outside impersonation (regression)', function (): void {
    app()->instance(ImpersonationService::class, new WriteGuardFakeImpersonationService(null));

    Route::middleware([DenyImpersonatedWrites::class])->post('/im-wg-post-outside', fn () => 'ok');

    test()->post('/im-wg-post-outside')->assertOk();
});

// ── L2 — Eloquent layer (щ, ъ, ы) ───────────────────────────────────────

it('щ: L2 blocks Model::save()/delete() under impersonation; passes outside it', function (): void {
    $fake = new WriteGuardFakeImpersonationService(null);
    app()->instance(ImpersonationService::class, $fake);

    $guard = app()->make(ImpersonatedModelWriteGuard::class);
    Event::listen('eloquent.saving: *', $guard->handle(...));
    Event::listen('eloquent.deleting: *', $guard->handle(...));

    // Outside impersonation — passes.
    $model = ImpersonationGuardWriteStub::create(['id' => (string) Str::uuid(), 'name' => 'one']);

    // Under impersonation — save() and delete() both throw.
    $fake->setActive(wgActiveState());

    expect(fn () => ImpersonationGuardWriteStub::create(['id' => (string) Str::uuid(), 'name' => 'two']))
        ->toThrow(ImpersonationRestrictedException::class);

    $model->name = 'changed';
    expect(fn () => $model->save())->toThrow(ImpersonationRestrictedException::class);
    expect(fn () => $model->delete())->toThrow(ImpersonationRestrictedException::class);
});

it('ъ: L2 exempts AuditLog — the compliance record can write while queue=sync during impersonation', function (): void {
    $fake = new WriteGuardFakeImpersonationService(new ImpersonationState('grant-wg', (string) Str::uuid(), 'target-wg', new DateTimeImmutable('+1 hour')));
    app()->instance(ImpersonationService::class, $fake);

    $guard = app()->make(ImpersonatedModelWriteGuard::class);
    Event::listen('eloquent.saving: *', $guard->handle(...));

    $context = new AccountRef('audit-write-fixture', 'fixture', 'trial', [], []);
    LocalWorkContexts::install(app())->initialize($context);
    WorkScopeFixture::install(app(), principalKind: 'service')->scope();
    resolve(AuditLogger::class)->record(new AuditEntry(
        action: 'created', actorType: ActorType::Support,
    ));
    expect(DB::connection('pgsql')->table('sys_audit_log')->count())->toBe(1);
});

it('ъ: L2 exempts ImpersonationGrant — the grant row itself is not blocked', function (): void {
    $fake = new WriteGuardFakeImpersonationService(wgActiveState());
    app()->instance(ImpersonationService::class, $fake);

    $guard = app()->make(ImpersonatedModelWriteGuard::class);
    Event::listen('eloquent.saving: *', $guard->handle(...));

    // FK-satisfying real rows (root_impersonation_grants references both).
    $staffId = (string) Str::uuid();
    DB::connection('pgsql')->table('root_identities')->insert(['id' => $staffId, 'email' => $staffId.'@example.test']);
    $clusterId = (string) Str::uuid();
    DB::connection('pgsql')->table('root_clusters')->insert([
        'id' => $clusterId,
        'name' => 'cell-wg',
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer-a.local',
        'app_db_user' => 'corex',
    ]);
    $accountId = (string) Str::uuid();
    DB::connection('pgsql')->table('root_accounts')->insert([
        'id' => $accountId,
        'cluster_id' => $clusterId,
        'db_name' => 'acc_wg_stub',
        'slug' => 'acc-wg-stub',
        'owner_identity_id' => $staffId,
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'status' => 'active',
    ]);

    ImpersonationGrant::create([
        'id' => (string) Str::uuid(),
        'staff_identity_id' => $staffId,
        'account_id' => $accountId,
        'reason' => 'support ticket #1',
    ]);

    expect(ImpersonationGrant::query()->count())->toBe(1);
});

it('ы: L2 allowing() lets an intentional write through, then blocks again after (finally resets the flag even on throw)', function (): void {
    $fake = new WriteGuardFakeImpersonationService(wgActiveState());
    app()->instance(ImpersonationService::class, $fake);

    $guard = app()->make(ImpersonatedModelWriteGuard::class);
    Event::listen('eloquent.saving: *', $guard->handle(...));

    $guard->allowing(function (): void {
        ImpersonationGuardWriteStub::create(['id' => (string) Str::uuid(), 'name' => 'inside-allowing']);
    });

    expect(ImpersonationGuardWriteStub::query()->count())->toBe(1);

    // Flag must be reset even when the callback itself throws.
    try {
        $guard->allowing(function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected — asserting the flag reset below, not this exception.
    }

    expect(fn () => ImpersonationGuardWriteStub::create(['id' => (string) Str::uuid(), 'name' => 'after-allowing']))
        ->toThrow(ImpersonationRestrictedException::class);
});

it('keeps allowing local to its scoped guard and restores an outer allowance after an inner exception', function (): void {
    $fake = new WriteGuardFakeImpersonationService(wgActiveState());
    $outer = new ImpersonatedModelWriteGuard($fake);
    $other = new ImpersonatedModelWriteGuard($fake);
    $payload = [new ImpersonationGuardWriteStub];

    $outer->allowing(function () use ($outer, $other, $payload): void {
        expect(fn () => $outer->handle('eloquent.saving', $payload))->not->toThrow(ImpersonationRestrictedException::class);
        expect(fn () => $other->handle('eloquent.saving', $payload))->toThrow(ImpersonationRestrictedException::class);

        try {
            $outer->allowing(static function (): void {
                throw new RuntimeException('inner');
            });
        } catch (RuntimeException) {
            // The outer allowance must still be active here.
        }
        expect(fn () => $outer->handle('eloquent.saving', $payload))->not->toThrow(ImpersonationRestrictedException::class);
    });

    expect(fn () => $outer->handle('eloquent.saving', $payload))->toThrow(ImpersonationRestrictedException::class);
});

// ── L3 — semantic layer (ь) ─────────────────────────────────────────────

it('ь: L3 ImpersonationGuard::assertAllowed() throws under impersonation and stays silent outside it', function (): void {
    $fake = new WriteGuardFakeImpersonationService(wgActiveState());
    $guard = app()->makeWith(ImpersonationGuard::class, ['impersonation' => $fake]);

    expect($guard->denies(ImpersonationRestrictedAction::DeleteAccount))->toBeTrue();
    expect(fn () => $guard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount))
        ->toThrow(ImpersonationRestrictedException::class);

    $fake->setActive(null);

    expect($guard->denies(ImpersonationRestrictedAction::DeleteAccount))->toBeFalse();
    $guard->assertAllowed(ImpersonationRestrictedAction::DeleteAccount);
    // no exception — assertion above is that execution reaches here.
    expect(true)->toBeTrue();
});

// ── аа — boxed/oidc-off profile carries zero impersonation surface ──────

it('аа: with oidc disabled, no impersonation middleware in the tenant group and no Eloquent listeners are registered', function (): void {
    expect(config('corex-tenancy.oidc.enabled'))->toBeFalse()
        ->and(config('corex-tenancy.oidc.impersonation.enabled'))->toBeFalse();

    $router = app()->make(Router::class);
    $tenantGroup = $router->getMiddlewareGroups()['tenant'] ?? [];

    expect($tenantGroup)->not->toContain(DenyImpersonatedWrites::class)
        ->and($tenantGroup)->not->toContain(ImpersonationSessionGuard::class);

    // Behavioral proof, not listener introspection (wildcard event-name
    // matching makes counting registered patterns unreliable): no
    // ImpersonatedModelWriteGuard was ever registered by the package's own
    // boot() on this (default, oidc-disabled) app instance, so a model
    // write succeeds even if a container consumer manually bound an
    // "active" ImpersonationService — nothing is listening to enforce it.
    app()->instance(ImpersonationService::class, new WriteGuardFakeImpersonationService(wgActiveState()));

    ImpersonationGuardWriteStub::create(['id' => (string) Str::uuid(), 'name' => 'boxed']);

    expect(ImpersonationGuardWriteStub::query()->count())->toBe(1);
});
