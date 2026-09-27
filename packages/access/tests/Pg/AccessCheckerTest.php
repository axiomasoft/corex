<?php

declare(strict_types=1);

use AzGuard\Contracts\AzGuardManagerInterface;
use AzGuard\Contracts\PermissionLayer;
use AzGuard\Panels\Panel;
use CoreX\Auth\Access\PrincipalIdentity;
use CoreX\Auth\Contracts\AccessChecker;
use CoreX\Auth\Contracts\ScopeResolver;
use CoreX\Auth\Database\Seeders\SystemRolesSeeder;
use CoreX\Auth\Enums\PrincipalType;
use CoreX\Auth\Enums\Scope;
use CoreX\Auth\Exceptions\ScopeCapabilityMismatch;
use CoreX\Auth\Matrix\DatabaseRoleMatrixCompiler;
use CoreX\Auth\Matrix\ScopeMapVersion;
use CoreX\Auth\Models\Role;
use CoreX\Auth\Models\RoleAssignment;
use CoreX\Auth\Models\RoleMatrixRow;
use CoreX\Auth\RecordContext;
use CoreX\Auth\Roles\RoleCloner;
use CoreX\Auth\Support\AuthOptions;
use CoreX\Auth\Tests\Stubs\Deal;
use CoreX\Auth\Tests\Stubs\FakeModuleRegistry;
use CoreX\Auth\Tests\Stubs\FakeTenancyManager;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Registry\ArrayEntityRegistry;
use CoreX\Modules\Registry\EntityDefinition;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\TenantContext;
use CoreX\Tenancy\WorkspaceRef;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

it('uses identical fail-closed capability normalization for record and list checks', function (): void {
    app()->forgetInstance(ArrayEntityRegistry::class);
    app()->forgetInstance(EntityRegistry::class);
    app(ArrayEntityRegistry::class)->register(new EntityDefinition(
        handle: 'crm.deal', model: Deal::class, table: 'deals', module: 'corex/access-tests',
        labelSingular: 'Deal', labelPlural: 'Deals', capabilities: [], fields: [], titleAttribute: null,
    ));
    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'own']);
    ($this->compileRole)($role->id);
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principal->principalId()]);
    $deal = new Deal(['owner_id' => $principal->principalId()]);
    expect(fn () => app(AccessChecker::class)->canOnRecord($principal, 'read', $deal))
        ->toThrow(ScopeCapabilityMismatch::class);
    expect(fn () => app(ScopeResolver::class)->apply(Deal::query(), $principal, 'crm.deal', 'read'))
        ->toThrow(ScopeCapabilityMismatch::class);
    app(AzGuardManagerInterface::class)->forUser($principal)->on('app')->grant('app.crm.deal.read');
    expect(app(AccessChecker::class)->canOnRecord($principal, 'read', $deal))->toBeTrue()
        ->and(app(ScopeResolver::class)->apply(Deal::query(), $principal, 'crm.deal', 'read')->toSql())->not->toContain('owner_id');
});

it('recomputes scope after replacing an assignment with an equally versioned role', function (): void {
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $broad = ($this->newRole)();
    $narrow = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $broad->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    RoleMatrixRow::query()->create(['role_id' => $narrow->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'own']);
    $assignment = RoleAssignment::query()->create(['role_id' => $broad->id, 'principal_type' => 'user', 'principal_id' => $principal->principalId()]);
    $checker = app(AccessChecker::class);
    expect($checker->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::Account);
    $assignment->update(['role_id' => $narrow->id]);
    expect($checker->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::Own);
});

it('does not reuse scope memo when a connection switch exposes identical role IDs in another tenant', function (): void {
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principal->principalId()]);
    $context = new TenantContext(new AccountRef('account-a', 'a', 'active', [], []), null);
    $manager = Mockery::mock(TenancyManager::class);
    $manager->shouldReceive('context')->andReturnUsing(function () use (&$context) {
        return $context;
    });
    app()->instance(TenancyManager::class, $manager);
    $checker = app(AccessChecker::class);
    expect($checker->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::Account);

    // Two independent schemas in corex_test stand in for two tenant DBs;
    // role IDs and version counters intentionally coincide.
    $schema = 'auth_scope_'.bin2hex(random_bytes(8));
    DB::statement('CREATE SCHEMA '.$schema);

    try {
        foreach (['aut_roles', 'aut_role_assignments', 'aut_role_matrix'] as $table) {
            DB::statement('CREATE TABLE '.$schema.'.'.$table.' (LIKE public.'.$table.' INCLUDING ALL)');
            DB::statement('INSERT INTO '.$schema.'.'.$table.' SELECT * FROM public.'.$table);
        }
        config(['database.connections.scope_other' => array_merge(config('database.connections.pgsql'), ['search_path' => $schema])]);
        DB::connection('scope_other')->table('aut_role_matrix')->update(['scope' => 'own']);
        $context = new TenantContext(new AccountRef('account-b', 'b', 'active', [], []), null);
        DB::setDefaultConnection('scope_other');
        expect($checker->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::Own);
    } finally {
        DB::setDefaultConnection('pgsql');
        DB::purge('scope_other');
        DB::statement('DROP SCHEMA '.$schema.' CASCADE');
    }
});

it('excludes deleted broad roles when a live narrow role still grants access', function (): void {
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    foreach (['account', 'own'] as $scope) {
        $role = ($this->newRole)();
        RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => $scope]);
        RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principal->principalId()]);
        ($this->compileRole)($role->id);

        if ($scope === 'account') {
            $role->delete();
        }
    }
    expect(app(AccessChecker::class)->allowed($principal, 'crm.deal', 'read'))->toBeTrue()
        ->and(app(AccessChecker::class)->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::Own);
});

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateAzGuard();
    $this->migrateAutSchema();
    $this->migrateFixtures();
    $this->migrateCoreDepartments();

    app(AzGuardManagerInterface::class)->registerPanel(Panel::make()->id('app'));

    app(ArrayEntityRegistry::class)->register(new EntityDefinition(
        handle: 'crm.deal',
        model: Deal::class,
        table: 'deals',
        module: 'corex/access-tests',
        labelSingular: 'Deal',
        labelPlural: 'Deals',
        capabilities: [Capability::Ownable, Capability::WorkspaceScoped],
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

    $this->newRole = function (array $overrides = []): Role {
        return Role::query()->create(array_merge([
            'slug' => 'role-'.Str::uuid7(),
            'name' => 'Role',
            'is_system' => false,
            'assignable_to' => [PrincipalType::User->value],
        ], $overrides));
    };

    $this->makeUser = function (?string $departmentId = null): string {
        $id = (string) Str::uuid7();
        DB::table('users')->insert(['id' => $id, 'department_id' => $departmentId]);

        return $id;
    };

    $this->makeDepartment = function (string $workspaceId, ?string $parentId, string $path): string {
        $id = (string) Str::uuid7();
        DB::table('sys_departments')->insert([
            'id' => $id,
            'workspace_id' => $workspaceId,
            'parent_id' => $parentId,
            'path' => $path,
            'name' => 'Department',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    };
});

// AC-22 — the compiled key round-trips through the REAL public AzGuard path
// (PrincipalIdentity::hasPermission), not a private lookup (premortem C1).
it('resolves allowed() through the real AzGuard round-trip for a scoped action, and denies a scope-none action', function (): void {
    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'delete', 'scope' => 'none']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $checker = app(AccessChecker::class);

    expect($checker->allowed($identity, 'crm.deal', 'read'))->toBeTrue()
        ->and($checker->allowed($identity, 'crm.deal', 'delete'))->toBeFalse()
        ->and($checker->allowed($identity, 'crm.deal', 'export'))->toBeFalse();
});

// AC-23 — scopeFor() resolves each of the six compiled scope values on its own.
it('resolves each of the six scope values from a single matching matrix row', function (Scope $scope): void {
    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => $scope->value]);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    expect(app(AccessChecker::class)->scopeFor($identity, 'crm.deal', 'read'))->toBe($scope);
})->with(fn () => Scope::cases());

// B-11 §5.5 — several roles resolve to the MAX weight of their base rows.
it('resolves the max weight across multiple roles held by the same principal', function (): void {
    $roleLow = ($this->newRole)();
    $roleHigh = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $roleLow->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'own']);
    RoleMatrixRow::query()->create(['role_id' => $roleHigh->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'workspace']);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $roleLow->id, 'principal_type' => 'user', 'principal_id' => $principalId]);
    RoleAssignment::query()->create(['role_id' => $roleHigh->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    expect(app(AccessChecker::class)->scopeFor($identity, 'crm.deal', 'read'))->toBe(Scope::Workspace);
});

// B-11 §5.5 — a context row REPLACES the base row for its own context, including narrowing it to none.
it('lets a context row override, and narrow, the base scope for its own context only', function (): void {
    $role = ($this->newRole)();
    $stageId = (string) Str::uuid7();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'none', 'context_type' => 'stage', 'context_id' => $stageId]);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $checker = app(AccessChecker::class);

    expect($checker->scopeFor($identity, 'crm.deal', 'read'))->toBe(Scope::Account)
        ->and($checker->scopeFor($identity, 'crm.deal', 'read', new RecordContext('stage', $stageId)))->toBe(Scope::None)
        ->and($checker->scopeFor($identity, 'crm.deal', 'read', new RecordContext('stage', (string) Str::uuid7())))->toBe(Scope::Account);
});

it('grants canOnRecord for own scope only when the record belongs to the principal', function (): void {
    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'own']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $checker = app(AccessChecker::class);

    $ownDeal = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $principalId]);
    $otherDeal = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]);

    expect($checker->canOnRecord($identity, 'read', $ownDeal))->toBeTrue()
        ->and($checker->canOnRecord($identity, 'read', $otherDeal))->toBeFalse();
});

it('grants canOnRecord for account scope regardless of ownership', function (): void {
    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $deal = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]);

    expect(app(AccessChecker::class)->canOnRecord($identity, 'read', $deal))->toBeTrue();
});

it('matches workspace scope against the active tenancy context, and passes a workspace-less record', function (): void {
    $workspaceId = (string) Str::uuid7();
    $otherWorkspaceId = (string) Str::uuid7();
    app()->instance(TenancyManager::class, new FakeTenancyManager(new WorkspaceRef($workspaceId, 'w', 'team', $workspaceId, null)));

    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'workspace']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $checker = app(AccessChecker::class);

    $matching = Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => $workspaceId]);
    $other = Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => $otherWorkspaceId]);
    $accountWide = Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => null]);

    expect($checker->canOnRecord($identity, 'read', $matching))->toBeTrue()
        ->and($checker->canOnRecord($identity, 'read', $other))->toBeFalse()
        ->and($checker->canOnRecord($identity, 'read', $accountWide))->toBeTrue();
});

it('grants department scopes only for real same-department and descendant record owners', function (Scope $scope, array $expectedLabels): void {
    $workspaceId = (string) Str::uuid7();
    $parentDepartmentId = ($this->makeDepartment)($workspaceId, null, 'record_scope');
    $childDepartmentId = ($this->makeDepartment)($workspaceId, $parentDepartmentId, 'record_scope.child');
    $siblingDepartmentId = ($this->makeDepartment)($workspaceId, null, 'other_scope');

    $principalId = ($this->makeUser)($parentDepartmentId);
    $sameDepartmentOwnerId = ($this->makeUser)($parentDepartmentId);
    $childDepartmentOwnerId = ($this->makeUser)($childDepartmentId);
    $siblingDepartmentOwnerId = ($this->makeUser)($siblingDepartmentId);
    $ownerWithoutDepartmentId = ($this->makeUser)();

    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => $scope->value]);
    ($this->compileRole)($role->id);
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => PrincipalType::User->value, 'principal_id' => $principalId]);

    $records = [
        'same' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $sameDepartmentOwnerId]),
        'child' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $childDepartmentOwnerId]),
        'sibling' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $siblingDepartmentOwnerId]),
        'owner_without_department' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $ownerWithoutDepartmentId]),
        'missing_owner' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]),
        'null_owner' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => null]),
    ];

    $checker = app(AccessChecker::class);
    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $grantedLabels = collect($records)
        ->filter(fn (Deal $record): bool => $checker->canOnRecord($identity, 'read', $record))
        ->keys()
        ->all();

    expect($checker->scopeFor($identity, 'crm.deal', 'read'))->toBe($scope)
        ->and($grantedLabels)->toBe($expectedLabels)
        ->and($checker->canOnRecord($identity, 'read', $records['sibling']))->toBeFalse()
        ->and($checker->canOnRecord($identity, 'read', $records['null_owner']))->toBeFalse();
})->with([
    'dept: same department only' => [Scope::Dept, ['same']],
    'dept_tree: same department and descendant' => [Scope::DeptTree, ['same', 'child']],
]);

it('denies every department scope to non-user principals through AccessChecker', function (PrincipalType $type, Scope $scope): void {
    $workspaceId = (string) Str::uuid7();
    $departmentId = ($this->makeDepartment)($workspaceId, null, 'record_scope.non_user');
    $ownerId = ($this->makeUser)($departmentId);
    $principalId = (string) Str::uuid7();
    DB::table('users')->insert(['id' => $principalId, 'department_id' => $departmentId]);

    $role = ($this->newRole)(['assignable_to' => [$type->value]]);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => $scope->value]);
    ($this->compileRole)($role->id);
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => $type->value, 'principal_id' => $principalId]);

    $record = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $ownerId]);

    expect(app(AccessChecker::class)->canOnRecord(new PrincipalIdentity($type, $principalId), 'read', $record))->toBeFalse();
})->with(fn (): array => collect([PrincipalType::Agent, PrincipalType::ApiKey])->crossJoin([Scope::Dept, Scope::DeptTree])->all());

it('denies department scopes through AccessChecker when department tables are absent', function (Scope $scope): void {
    $workspaceId = (string) Str::uuid7();
    $departmentId = ($this->makeDepartment)($workspaceId, null, 'record_scope.missing_table');
    $principalId = ($this->makeUser)($departmentId);
    $ownerId = ($this->makeUser)($departmentId);

    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => $scope->value]);
    ($this->compileRole)($role->id);
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => PrincipalType::User->value, 'principal_id' => $principalId]);

    $record = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $ownerId]);
    DB::statement('DROP TABLE sys_departments CASCADE');

    expect(app(AccessChecker::class)->canOnRecord(new PrincipalIdentity(PrincipalType::User, $principalId), 'read', $record))->toBeFalse();
})->with([Scope::Dept, Scope::DeptTree]);

// Code Guidance — canOnRecord grants via an active direct_grant even with no matching role at all.
it('grants canOnRecord via an active AzGuard direct grant with no matching role', function (): void {
    $principalId = (string) Str::uuid7();
    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    app(AzGuardManagerInterface::class)->forUser($identity)->on('app')->grant('app.crm.deal.read');

    $deal = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]);

    expect(app(AccessChecker::class)->canOnRecord($identity, 'read', $deal))->toBeTrue();
});

// Implementation Rules — an unresolvable entity_key is fail-closed false, never "the whole scope".
it('denies canOnRecord for a record whose model is not registered in EntityRegistry', function (): void {
    $identity = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $unregistered = new class extends Model {};

    expect(app(AccessChecker::class)->canOnRecord($identity, 'read', $unregistered))->toBeFalse();
});

// D103 — an active assignment of the system role `owner` passes any panel capability check.
it('lets a principal with the active system owner role pass any allowed() check on the panel', function (): void {
    Role::withSystemMutationAllowed(fn () => (new SystemRolesSeeder)->run());
    $owner = Role::query()->where('slug', 'owner')->firstOrFail();

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $owner->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $checker = app(AccessChecker::class);

    expect($checker->allowed($identity, 'crm.deal', 'read'))->toBeTrue()
        ->and($checker->allowed($identity, 'tsk.task', 'delete'))->toBeTrue();
});

// D103 — a clone of `owner` is not system-flagged and does NOT inherit the wildcard.
it('does not extend owner-wildcard capability to a non-system clone of owner', function (): void {
    Role::withSystemMutationAllowed(fn () => (new SystemRolesSeeder)->run());
    $owner = Role::query()->where('slug', 'owner')->firstOrFail();
    $clone = (new RoleCloner)->clone($owner, 'owner-custom-'.Str::uuid7());

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $clone->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    expect(app(AccessChecker::class)->allowed($identity, 'crm.deal', 'read'))->toBeFalse();
});

// D101 — a role assigned to one workspace grants ONLY inside that workspace at the source level.
it('grants allowed() inside the workspace the role was assigned to', function (): void {
    $workspace = new WorkspaceRef((string) Str::uuid7(), 'a', 'team', 'a', null);
    app()->instance(TenancyManager::class, new FakeTenancyManager($workspace));

    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId, 'workspace_id' => $workspace->id]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    expect(app(AccessChecker::class)->allowed($identity, 'crm.deal', 'read'))->toBeTrue();
});

it('denies allowed() outside the workspace the role was assigned to', function (): void {
    $assignedWorkspace = (string) Str::uuid7();
    $otherWorkspace = new WorkspaceRef((string) Str::uuid7(), 'b', 'team', 'b', null);
    app()->instance(TenancyManager::class, new FakeTenancyManager($otherWorkspace));

    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId, 'workspace_id' => $assignedWorkspace]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    expect(app(AccessChecker::class)->allowed($identity, 'crm.deal', 'read'))->toBeFalse();
});

// D101 risk note — anti-self-deception negative proof (class P3.7/D85): show RED first.
// Without WorkspacePermissionLayer, the resolver's per-request cache has no
// workspace-discriminated key — a set resolved for workspace A is served,
// stale, to a lookup made after switching to workspace B.
it('without WorkspacePermissionLayer, a cached grant leaks across a workspace switch (negative proof)', function (): void {
    // The config switch only decides CoreAuthServiceProvider::register()'s
    // binding at boot, already past by the time a test body runs — unbind
    // the container entry directly to simulate "no layer bound" here.
    unset($this->app[PermissionLayer::class]);

    $workspaceA = new WorkspaceRef((string) Str::uuid7(), 'a', 'team', 'a', null);
    $workspaceB = new WorkspaceRef((string) Str::uuid7(), 'b', 'team', 'b', null);
    $fakeTenancy = new FakeTenancyManager($workspaceA);
    app()->instance(TenancyManager::class, $fakeTenancy);

    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId, 'workspace_id' => $workspaceA->id]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    expect($identity->hasPermission('app.crm.deal.read', 'app'))->toBeTrue();

    $fakeTenancy->setWorkspace($workspaceB);

    // BUG proof, not the desired behaviour: workspace B has no assignment at
    // all, yet the stale request-cache entry (discriminator '' either way) is
    // served unchanged.
    expect($identity->hasPermission('app.crm.deal.read', 'app'))->toBeTrue();
});

// The isolation half of the same proof: WorkspacePermissionLayer bound (default) fixes it.
it('with WorkspacePermissionLayer, a workspace switch gets a fresh, correctly-scoped resolution', function (): void {
    $workspaceA = new WorkspaceRef((string) Str::uuid7(), 'a', 'team', 'a', null);
    $workspaceB = new WorkspaceRef((string) Str::uuid7(), 'b', 'team', 'b', null);
    $fakeTenancy = new FakeTenancyManager($workspaceA);
    app()->instance(TenancyManager::class, $fakeTenancy);

    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId, 'workspace_id' => $workspaceA->id]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    expect($identity->hasPermission('app.crm.deal.read', 'app'))->toBeTrue();

    $fakeTenancy->setWorkspace($workspaceB);

    expect($identity->hasPermission('app.crm.deal.read', 'app'))->toBeFalse();
});
