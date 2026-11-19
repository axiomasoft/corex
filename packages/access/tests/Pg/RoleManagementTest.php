<?php

declare(strict_types=1);

use CoreX\Access\Access\Laravel\PrincipalIdentity;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\Access\Matrix\Events\RoleMatrixCompiled;
use CoreX\Access\Roles\DirectGrants;
use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\RoleManager;
use CoreX\Access\Tests\Stubs\Deal;
use CoreX\Access\Tests\Stubs\FakeModuleRegistry;
use CoreX\Context\Enums\PrincipalType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Enums\Entities\Capability;
use Packman\Modules\Testing\ArrayEntityRegistry;

uses()->group('pg');

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateAutSchema();
    $this->migrateFixtures();

    resolve(ArrayEntityRegistry::class)->register(new EntityDefinition(
        handle: 'crm.deal', model: Deal::class, table: 'deals', module: 'corex/access-tests',
        labelSingular: 'Deal', labelPlural: 'Deals', capabilities: [Capability::Ownable], fields: [], titleAttribute: null,
    ));
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(['corex/access-tests']));
});

it('sets exactly the access that the public role and direct grant operations describe', function (): void {
    $roles = resolve(RoleManager::class);
    $grants = resolve(DirectGrants::class);
    $access = resolve(AccessChecker::class);
    $user = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $own = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $user->principalId()]);
    $other = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]);

    $role = $roles->create('editor', 'Editor');
    expect($roles->create('editor', 'Editor'))->toBe($role)
        ->and(fn () => $roles->create('editor', 'Manager'))->toThrow(DomainException::class);

    $roles->setScope($role, 'crm.deal', 'read', Scope::Own);
    $roles->setScope($role, 'crm.deal', 'read', Scope::Own);
    $roles->assign($role, $user);
    $roles->assign($role, $user);
    expect($access->canOnRecord($user, 'read', $own))->toBeTrue()
        ->and($access->canOnRecord($user, 'read', $other))->toBeFalse()
        ->and(resolve(ScopeResolver::class)->apply(Deal::query(), $user, 'crm.deal', 'read')->pluck('id')->all())->toBe([$own->id]);

    $grants->grant($user, 'crm.deal', 'read');
    $grants->grant($user, 'crm.deal', 'read');
    expect($access->canOnRecord($user, 'read', $other))->toBeTrue()
        ->and(DB::table('aut_direct_grants')->count())->toBe(1);

    $grants->revoke($user, 'crm.deal', 'read');
    $grants->revoke($user, 'crm.deal', 'read');
    expect($access->canOnRecord($user, 'read', $other))->toBeFalse();

    $roles->unassign($role, $user);
    $roles->unassign($role, $user);
    $grants->grant($user, 'crm.deal', 'read', new DateTimeImmutable('2000-01-01T00:00:00Z'));
    expect($access->canOnRecord($user, 'read', $own))->toBeFalse()
        ->and(DB::table('aut_role_assignments')->count())->toBe(0);
});

it('keeps assignment boundaries and rolls back a scope change if compilation is rejected', function (): void {
    $roles = resolve(RoleManager::class);
    $role = $roles->create('editor', 'Editor');
    $user = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $agent = new PrincipalIdentity(PrincipalType::Agent, $user->principalId());
    expect(fn () => $roles->assign($role, $agent))->toThrow(DomainException::class);

    $workspace = (string) Str::uuid7();
    $expiry = new DateTimeImmutable('2099-05-17T11:16:22.123456+04:00');
    $roles->assign($role, $user, $workspace, $expiry);
    $storedExpiry = DB::table('aut_role_assignments')->where('workspace_id', $workspace)->value('expires_at');
    expect((new DateTimeImmutable($storedExpiry))->format('U.u'))->toBe($expiry->format('U.u'));
    $roles->assign($role, $user);
    $roles->unassign($role, $user, $workspace);
    expect(DB::table('aut_role_assignments')->pluck('workspace_id')->all())->toBe([null]);

    $roles->setScope($role, 'crm.deal', 'read', Scope::Own);
    Event::listen(RoleMatrixCompiled::class, static fn () => throw new RuntimeException('veto'));
    expect(fn () => $roles->setScope($role, 'crm.deal', 'read', Scope::Account))->toThrow(RuntimeException::class, 'veto')
        ->and(DB::table('aut_role_matrix')->pluck('scope')->all())->toBe(['own']);
});

it('preserves the system role mutation guard on public scope writes', function (): void {
    $role = Role::withSystemMutationAllowed(static fn (): Role => Role::query()->create([
        'slug' => 'admin', 'name' => 'Admin', 'is_system' => true, 'assignable_to' => ['user'],
    ]));
    expect(fn () => resolve(RoleManager::class)->setScope($role->id, 'crm.deal', 'read', Scope::Account))
        ->toThrow(AuthorizationException::class)
        ->and(DB::table('aut_role_matrix')->count())->toBe(0);
});
