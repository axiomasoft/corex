<?php

declare(strict_types=1);

use CoreX\Access\Access\Internal\MatrixPermissions;
use CoreX\Access\Access\Laravel\PrincipalIdentity;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Data\RecordContext;
use CoreX\Access\Database\Seeders\SystemRolesSeeder;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Exceptions\ScopeCapabilityMismatch;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\Access\Matrix\Internal\DatabaseRoleMatrixCompiler;
use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\Internal\Models\RoleAssignment;
use CoreX\Access\Roles\Internal\Models\RoleMatrixRow;
use CoreX\Access\Roles\Internal\RoleCloner;
use CoreX\Access\Tests\Stubs\Deal;
use CoreX\Access\Tests\Stubs\FakeModuleRegistry;
use CoreX\Access\Tests\Stubs\FakeTenancyManager;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Enums\PrincipalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Enums\Entities\Capability;
use Packman\Modules\Testing\ArrayEntityRegistry;

uses()->group('pg');

it('uses identical fail-closed capability normalization for record and list checks', function (): void {
    app()->forgetInstance(ArrayEntityRegistry::class);
    app()->forgetInstance(EntityRegistry::class);
    resolve(ArrayEntityRegistry::class)->register(new EntityDefinition(
        handle: 'crm.deal', model: Deal::class, table: 'deals', module: 'corex/access-tests',
        labelSingular: 'Deal', labelPlural: 'Deals', capabilities: [], fields: [], titleAttribute: null,
    ));
    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'own']);
    ($this->compileRole)($role->id);
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principal->principalId()]);
    $deal = new Deal(['owner_id' => $principal->principalId()]);
    expect(fn () => resolve(AccessChecker::class)->canOnRecord($principal, 'read', $deal))
        ->toThrow(ScopeCapabilityMismatch::class);
    expect(fn () => resolve(ScopeResolver::class)->apply(Deal::query(), $principal, 'crm.deal', 'read'))
        ->toThrow(ScopeCapabilityMismatch::class);
    DB::table('aut_direct_grants')->insert(['id' => (string) Str::uuid7(), 'principal_type' => 'user', 'principal_id' => $principal->principalId(), 'permission_key' => 'app.crm.deal.read']);
    expect(resolve(AccessChecker::class)->canOnRecord($principal, 'read', $deal))->toBeTrue()
        ->and(resolve(ScopeResolver::class)->apply(Deal::query(), $principal, 'crm.deal', 'read')->toSql())->not->toContain('owner_id');
});

it('recomputes scope after replacing an assignment with an equally versioned role', function (): void {
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $broad = ($this->newRole)();
    $narrow = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $broad->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    RoleMatrixRow::query()->create(['role_id' => $narrow->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'own']);
    $assignment = RoleAssignment::query()->create(['role_id' => $broad->id, 'principal_type' => 'user', 'principal_id' => $principal->principalId()]);
    $checker = resolve(AccessChecker::class);
    expect(resolve(MatrixPermissions::class)->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::Account);
    $assignment->update(['role_id' => $narrow->id]);
    expect(resolve(MatrixPermissions::class)->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::Own);
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
    $checker = resolve(AccessChecker::class);
    expect(resolve(MatrixPermissions::class)->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::Account);

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
        expect(resolve(MatrixPermissions::class)->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::Own);
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
    expect(resolve(AccessChecker::class)->allowed($principal, 'crm.deal', 'read'))->toBeTrue()
        ->and(resolve(MatrixPermissions::class)->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::Own);
});

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateAutSchema();
    $this->migrateFixtures();
    $this->migrateCoreDepartments();

    resolve(ArrayEntityRegistry::class)->register(new EntityDefinition(
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

    // Keep the fixture module active without cross-package module migrations.
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(['corex/access-tests']));

    $this->compileRole = function (string $roleId): void {
        resolve(DatabaseRoleMatrixCompiler::class)->compile($roleId);
    };

    $this->newRole = (fn (array $overrides = []): Role => Role::query()->create(array_merge([
        'slug' => 'role-'.Str::uuid7(),
        'name' => 'Role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ], $overrides)));

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

// Native matrix rows are the capability source; scope none does not grant.
it('resolves allowed() from a native matrix row and denies scope none', function (): void {
    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'delete', 'scope' => 'none']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $checker = resolve(AccessChecker::class);

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

    expect(resolve(MatrixPermissions::class)->scopeFor($identity, 'crm.deal', 'read'))->toBe($scope);
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

    expect(resolve(MatrixPermissions::class)->scopeFor($identity, 'crm.deal', 'read'))->toBe(Scope::Workspace);
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
    $checker = resolve(AccessChecker::class);

    expect(resolve(MatrixPermissions::class)->scopeFor($identity, 'crm.deal', 'read'))->toBe(Scope::Account)
        ->and(resolve(MatrixPermissions::class)->scopeFor($identity, 'crm.deal', 'read', new RecordContext('stage', $stageId)))->toBe(Scope::None)
        ->and(resolve(MatrixPermissions::class)->scopeFor($identity, 'crm.deal', 'read', new RecordContext('stage', (string) Str::uuid7())))->toBe(Scope::Account);
});

it('grants canOnRecord for own scope only when the record belongs to the principal', function (): void {
    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'own']);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $checker = resolve(AccessChecker::class);

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

    expect(resolve(AccessChecker::class)->canOnRecord($identity, 'read', $deal))->toBeTrue();
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
    $checker = resolve(AccessChecker::class);

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

    $checker = resolve(AccessChecker::class);
    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $grantedLabels = collect($records)
        ->filter(fn (Deal $record): bool => $checker->canOnRecord($identity, 'read', $record))
        ->keys()
        ->all();

    expect(resolve(MatrixPermissions::class)->scopeFor($identity, 'crm.deal', 'read'))->toBe($scope)
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

    expect(resolve(AccessChecker::class)->canOnRecord(new PrincipalIdentity($type, $principalId), 'read', $record))->toBeFalse();
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

    expect(resolve(AccessChecker::class)->canOnRecord(new PrincipalIdentity(PrincipalType::User, $principalId), 'read', $record))->toBeFalse();
})->with([Scope::Dept, Scope::DeptTree]);

// Code Guidance — canOnRecord grants via an active direct_grant even with no matching role at all.
it('grants canOnRecord via an active native direct grant with no matching role', function (): void {
    $principalId = (string) Str::uuid7();
    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    DB::table('aut_direct_grants')->insert(['id' => (string) Str::uuid7(), 'principal_type' => 'user', 'principal_id' => $principalId, 'permission_key' => 'app.crm.deal.read']);

    $deal = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]);

    expect(resolve(AccessChecker::class)->canOnRecord($identity, 'read', $deal))->toBeTrue();
});

// Implementation Rules — an unresolvable entity_key is fail-closed false, never "the whole scope".
it('denies canOnRecord for a record whose model is not registered in EntityRegistry', function (): void {
    $identity = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $unregistered = new class extends Model {};

    expect(resolve(AccessChecker::class)->canOnRecord($identity, 'read', $unregistered))->toBeFalse();
});

// A native role grants only capabilities present in its matrix, including system roles.
it('does not grant a system owner role with an empty matrix', function (): void {
    Role::withSystemMutationAllowed(fn () => (new SystemRolesSeeder)->run());
    $owner = Role::query()->where('slug', 'owner')->firstOrFail();
    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $owner->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    expect(resolve(AccessChecker::class)->allowed(new PrincipalIdentity(PrincipalType::User, $principalId), 'crm.deal', 'read'))->toBeFalse();
});

// D103 — a clone of `owner` is not system-flagged and does NOT inherit the wildcard.
it('does not extend owner-wildcard capability to a non-system clone of owner', function (): void {
    Role::withSystemMutationAllowed(fn () => (new SystemRolesSeeder)->run());
    $owner = Role::query()->where('slug', 'owner')->firstOrFail();
    $clone = (resolve(RoleCloner::class))->clone($owner, 'owner-custom-'.Str::uuid7());

    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $clone->id, 'principal_type' => 'user', 'principal_id' => $principalId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    expect(resolve(AccessChecker::class)->allowed($identity, 'crm.deal', 'read'))->toBeFalse();
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

    expect(resolve(AccessChecker::class)->allowed($identity, 'crm.deal', 'read'))->toBeTrue();
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

    expect(resolve(AccessChecker::class)->allowed($identity, 'crm.deal', 'read'))->toBeFalse();
});

// Workspace assignment lookup is live against the active native tenancy context.
it('rechecks native workspace assignments after a workspace switch', function (): void {
    $workspaceA = new WorkspaceRef((string) Str::uuid7(), 'a', 'team', 'a', null);
    $workspaceB = new WorkspaceRef((string) Str::uuid7(), 'b', 'team', 'b', null);
    $fakeTenancy = new FakeTenancyManager($workspaceA);
    app()->instance(TenancyManager::class, $fakeTenancy);

    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    $principalId = (string) Str::uuid7();
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principalId, 'workspace_id' => $workspaceA->id]);
    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $checker = resolve(AccessChecker::class);

    expect($checker->allowed($identity, 'crm.deal', 'read'))->toBeTrue();
    $fakeTenancy->setWorkspace($workspaceB);
    expect($checker->allowed($identity, 'crm.deal', 'read'))->toBeFalse();
});
