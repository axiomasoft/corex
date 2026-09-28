<?php

declare(strict_types=1);

use AzGuard\Contracts\AzGuardManagerInterface;
use AzGuard\Support\Panel;
use CoreX\Auth\Access\PrincipalIdentity;
use CoreX\Auth\Contracts\AccessChecker;
use CoreX\Auth\Enums\PrincipalType;
use CoreX\Auth\Enums\Scope;
use CoreX\Auth\Matrix\DatabaseRoleMatrixCompiler;
use CoreX\Auth\Matrix\ScopeMapVersion;
use CoreX\Auth\Models\Role;
use CoreX\Auth\Models\RoleAssignment;
use CoreX\Auth\Models\RoleMatrixRow;
use CoreX\Auth\Support\AuthOptions;
use CoreX\Auth\Tests\Stubs\Deal;
use CoreX\Auth\Tests\Stubs\FakeModuleRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Registry\ArrayEntityRegistry;
use CoreX\Modules\Registry\EntityDefinition;
use Illuminate\Support\Str;

uses()->group('pg');

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateAzGuard();
    $this->migrateAutSchema();

    app(AzGuardManagerInterface::class)->registerPanel(Panel::make()->id('app'));

    app(ArrayEntityRegistry::class)->register(new EntityDefinition(
        handle: 'crm.deal',
        model: Deal::class,
        table: 'deals',
        module: 'corex/access-tests',
        labelSingular: 'Deal',
        labelPlural: 'Deals',
        capabilities: [],
        fields: [],
        titleAttribute: null,
    ));

    // P2.21: the real ModuleRegistry (no compiled file in Testbench) would
    // report every module inactive and hit tables never migrated here
    // (mod_modules) — this fixture's module is active by fiat so the
    // pre-P2.21 compiled-key expectations below stay valid.
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(['corex/access-tests']));

    $this->compileRole = function (string $roleId): void {
        (new DatabaseRoleMatrixCompiler(app(ScopeMapVersion::class), app(AuthOptions::class)))->compile($roleId);
    };

    $this->newRole = function (PrincipalType ...$assignableTo): Role {
        return Role::query()->create([
            'slug' => 'role-'.Str::uuid7(),
            'name' => 'Role',
            'is_system' => false,
            'assignable_to' => array_map(static fn (PrincipalType $t): string => $t->value, $assignableTo),
        ]);
    };
});

// AC-25 — agent/api_key work with no `users` row at all: assignment, allowed() and
// scopeFor() all resolve purely off aut_role_assignments.principal_id.
it('resolves allowed()/scopeFor() for an agent principal with no users row', function (): void {
    $role = ($this->newRole)(PrincipalType::Agent);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'workspace']);
    ($this->compileRole)($role->id);

    $agentId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => PrincipalType::Agent, 'principal_id' => $agentId]);

    $identity = new PrincipalIdentity(PrincipalType::Agent, $agentId);
    $checker = app(AccessChecker::class);

    expect($checker->allowed($identity, 'crm.deal', 'read'))->toBeTrue()
        ->and($checker->scopeFor($identity, 'crm.deal', 'read'))->toBe(Scope::Workspace);
});

it('resolves allowed() for an api_key principal with no users row', function (): void {
    $role = ($this->newRole)(PrincipalType::ApiKey);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    ($this->compileRole)($role->id);

    $apiKeyId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => PrincipalType::ApiKey, 'principal_id' => $apiKeyId]);

    $identity = new PrincipalIdentity(PrincipalType::ApiKey, $apiKeyId);

    expect(app(AccessChecker::class)->allowed($identity, 'crm.deal', 'read'))->toBeTrue();
});

// B-11 §5.5 — own/dept/dept_tree degrade to none for agent/api_key (ENFORCE); workspace/account do not.
it('degrades own/dept/dept_tree to none for an agent principal, but leaves workspace/account untouched', function (Scope $degraded): void {
    $role = ($this->newRole)(PrincipalType::Agent);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => $degraded->value]);

    $agentId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => PrincipalType::Agent, 'principal_id' => $agentId]);

    $identity = new PrincipalIdentity(PrincipalType::Agent, $agentId);

    expect(app(AccessChecker::class)->scopeFor($identity, 'crm.deal', 'read'))->toBe(Scope::None);
})->with([Scope::Own, Scope::Dept, Scope::DeptTree]);

it('leaves workspace and account scope untouched for an agent principal', function (Scope $undegraded): void {
    $role = ($this->newRole)(PrincipalType::Agent);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => $undegraded->value]);

    $agentId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => PrincipalType::Agent, 'principal_id' => $agentId]);

    $identity = new PrincipalIdentity(PrincipalType::Agent, $agentId);

    expect(app(AccessChecker::class)->scopeFor($identity, 'crm.deal', 'read'))->toBe($undegraded);
})->with([Scope::Workspace, Scope::Account]);

// AC-26 — an expired assignment is excluded from both capability and scope without any manual pruning.
it('excludes an expired assignment from allowed() and scopeFor()', function (): void {
    $role = ($this->newRole)(PrincipalType::User);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'principal_type' => PrincipalType::User,
        'principal_id' => $principalId,
        'expires_at' => now()->subDay(),
    ]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $checker = app(AccessChecker::class);

    expect($checker->allowed($identity, 'crm.deal', 'read'))->toBeFalse()
        ->and($checker->scopeFor($identity, 'crm.deal', 'read'))->toBe(Scope::None);
});

it('honours a not-yet-expired assignment in allowed() and scopeFor()', function (): void {
    $role = ($this->newRole)(PrincipalType::User);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create([
        'role_id' => $role->id,
        'principal_type' => PrincipalType::User,
        'principal_id' => $principalId,
        'expires_at' => now()->addDay(),
    ]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $checker = app(AccessChecker::class);

    expect($checker->allowed($identity, 'crm.deal', 'read'))->toBeTrue()
        ->and($checker->scopeFor($identity, 'crm.deal', 'read'))->toBe(Scope::Account);
});
