<?php

declare(strict_types=1);

use CoreX\Audit\Contracts\CurrentActor;
use CoreX\Audit\Enums\ActorType;
use CoreX\Audit\Internal\CurrentActor as InternalCurrentActor;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Data\ImpersonationState;
use CoreX\Context\Internal\NullImpersonationService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// P2.11 — root-harness coverage (D81: ядровые ACTOR/CHECK/boxed-дефолт cases
// live here, not in packages/tenancy/tests, since ImpersonationService/
// ActorType/CurrentActor are axiomasoft/corex, not axiomasoft/corex-tenancy). Cases а–бб per
// phases/P2.md P2.11 Validation; letters noted per test.
//
// Only the two CHECK-widening tests need PG (CHECK constraints are Postgres
// DDL, D12) — tagged individually with ->group('pg') rather than file-wide,
// so the boxed-default/CurrentActor cases stay sqlite-lane green (AC-28,
// case аа) and each grouped test resets its own schema inline (no shared
// beforeEach — a file-wide one would drag PG into the ungrouped tests too).

/**
 * Controllable double for CurrentActor's CONSUMPTION of the contract (D139)
 * — not the real DatabaseImpersonationService (PG/session-bound,
 * corex/tenancy-only, exercised by packages/tenancy/tests/Feature/
 * ImpersonationTest.php instead). This file tests axiomasoft/corex's own branch
 * logic in isolation, same spirit as AuditLogTest.php's inline fakes.
 */
final class FakeImpersonationService implements ImpersonationService
{
    public function __construct(
        private readonly ?ImpersonationState $fakeState = null,
    ) {}

    public function start(string $grantId, string $staffIdentityId): void
    {
        // not exercised here — DatabaseImpersonationService owns start()'s
        // real claim/mirror/session logic (packages/tenancy tests).
    }

    public function stop(): void
    {
        // not exercised here.
    }

    public function active(): ?string
    {
        return $this->fakeState?->grantId;
    }

    public function state(): ?ImpersonationState
    {
        return $this->fakeState;
    }
}

it('boxed default: ImpersonationService binds to NullImpersonationService — no grants exist (аа, AC-28)', function (): void {
    expect(app(ImpersonationService::class))->toBeInstanceOf(NullImpersonationService::class)
        ->and(app(ImpersonationService::class)->state())->toBeNull()
        ->and(app(ImpersonationService::class)->active())->toBeNull();
});

it('NullImpersonationService::start() throws — boxed installs have no grants (B-11 §6.1)', function (): void {
    (new NullImpersonationService)->start('grant-id', 'staff-id');
})->throws(LogicException::class);

it('CurrentActor reports Support + staff id + grant/target context while impersonation is active (у)', function (): void {
    $state = new ImpersonationState('grant-у', 'staff-у', 'target-у', new DateTimeImmutable('+1 hour'));
    app()->instance(ImpersonationService::class, new FakeImpersonationService($state));

    expect(CurrentActor::type())->toBe(ActorType::Support)
        ->and(CurrentActor::id())->toBe('staff-у')
        ->and(CurrentActor::context())->toHaveKey('impersonation')
        ->and(CurrentActor::context()['impersonation'])->toBe(['grant' => 'grant-у', 'target_user_id' => 'target-у', 'reason' => null]);
});

it('CurrentActor never reports actor_type=user under impersonation, even when authenticated (ф)', function (): void {
    Auth::setUser(new GenericUser(['id' => 'some-authenticated-user-id']));

    $state = new ImpersonationState('grant-ф', 'staff-ф', 'target-ф', new DateTimeImmutable('+1 hour'));
    app()->instance(ImpersonationService::class, new FakeImpersonationService($state));

    expect(CurrentActor::type())->toBe(ActorType::Support)
        ->and(CurrentActor::type())->not->toBe(ActorType::User)
        ->and(CurrentActor::id())->toBe('staff-ф');
});

it('CurrentActor falls back to the pre-impersonation behavior once state() is null (ц regression)', function (): void {
    app()->instance(ImpersonationService::class, new FakeImpersonationService(null));

    expect(CurrentActor::type())->toBe(ActorType::System)
        ->and(CurrentActor::id())->toBeNull();

    Auth::setUser(new GenericUser(['id' => 'user-abc']));

    expect(CurrentActor::type())->toBe(ActorType::User)
        ->and(CurrentActor::id())->toBe('user-abc');
});

it('sys_audit_actor_ck accepts support after the widening migration (D137)', function (): void {
    DB::connection('pgsql')->statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::connection('pgsql')->statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    test()->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(__DIR__.'/../../packages/core/database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    // Outside impersonation — regression (ц): the original vocabulary still writes.
    DB::connection('pgsql')->table('sys_audit_log')->insert([
        'id' => (string) Str::uuid7(),
        'occurred_at' => now(),
        'actor_type' => CurrentActor::type()->value,
        'action' => 'created',
        'context' => '{}',
        'created_at' => now(),
    ]);
    expect(DB::connection('pgsql')->table('sys_audit_log')->where('action', 'created')->first()->actor_type)->toBe('system');

    // Under impersonation — the widened CHECK must accept 'support' without violation (у).
    $staffId = (string) Str::uuid7();
    $state = new ImpersonationState((string) Str::uuid7(), $staffId, (string) Str::uuid7(), new DateTimeImmutable('+1 hour'));
    app()->instance(ImpersonationService::class, new FakeImpersonationService($state));
    app()->forgetInstance(InternalCurrentActor::class); // the scoped actor of this cycle captured the previous service

    DB::connection('pgsql')->table('sys_audit_log')->insert([
        'id' => (string) Str::uuid7(),
        'occurred_at' => now(),
        'actor_type' => CurrentActor::type()->value,
        'actor_id' => CurrentActor::id(),
        'action' => 'updated',
        'context' => json_encode(CurrentActor::context(), JSON_THROW_ON_ERROR),
        'created_at' => now(),
    ]);

    $row = DB::connection('pgsql')->table('sys_audit_log')->where('action', 'updated')->first();
    expect($row->actor_type)->toBe('support')
        ->and($row->actor_id)->toBe($staffId);
})->group('pg');

it('mod_ll_actor_ck accepts support after the widening migration — CurrentActor under impersonation writes without CHECK-violation (х, D138)', function (): void {
    DB::connection('pgsql')->statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::connection('pgsql')->statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    test()->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(__DIR__.'/../../packages/core/database/migrations'),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    // Outside impersonation — regression (ц): the original vocabulary still writes.
    DB::connection('pgsql')->table('mod_lifecycle_log')->insert([
        'id' => (string) Str::uuid7(),
        'module_name' => 'corex/stub',
        'operation' => 'enable',
        'from_state' => 'disabled',
        'to_state' => 'enabled',
        'actor_type' => CurrentActor::type()->value,
        'details' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    expect(DB::connection('pgsql')->table('mod_lifecycle_log')->where('operation', 'enable')->first()->actor_type)->toBe('system');

    // Under impersonation — DatabaseModuleLifecycle:798 writes
    // CurrentActor::type()->value into THIS table's own (independent, D138)
    // CHECK; without the widening migration this insert would violate it.
    $staffId = (string) Str::uuid7();
    $state = new ImpersonationState((string) Str::uuid7(), $staffId, (string) Str::uuid7(), new DateTimeImmutable('+1 hour'));
    app()->instance(ImpersonationService::class, new FakeImpersonationService($state));
    app()->forgetInstance(InternalCurrentActor::class); // the scoped actor of this cycle captured the previous service

    DB::connection('pgsql')->table('mod_lifecycle_log')->insert([
        'id' => (string) Str::uuid7(),
        'module_name' => 'corex/stub',
        'operation' => 'disable',
        'from_state' => 'enabled',
        'to_state' => 'disabled',
        'actor_type' => CurrentActor::type()->value,
        'actor_id' => CurrentActor::id(),
        'details' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::connection('pgsql')->table('mod_lifecycle_log')->where('operation', 'disable')->first();
    expect($row->actor_type)->toBe('support')
        ->and($row->actor_id)->toBe($staffId);
})->group('pg');
