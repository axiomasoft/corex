<?php

declare(strict_types=1);

use AzGuard\Contracts\AzGuardManagerInterface;
use AzGuard\Support\Panel;
use CoreX\Auth\Access\PrincipalIdentity;
use CoreX\Auth\Access\ScopePredicate;
use CoreX\Auth\Contracts\AccessChecker;
use CoreX\Auth\Contracts\ScopeResolver;
use CoreX\Auth\Enums\PrincipalType;
use CoreX\Auth\Enums\Scope;
use CoreX\Auth\Matrix\DatabaseRoleMatrixCompiler;
use CoreX\Auth\Matrix\MatrixValidator;
use CoreX\Auth\Matrix\ScopeMapVersion;
use CoreX\Auth\Models\Role;
use CoreX\Auth\Models\RoleAssignment;
use CoreX\Auth\Models\RoleMatrixRow;
use CoreX\Auth\RecordContext;
use CoreX\Auth\Support\AuthOptions;
use CoreX\Auth\Tests\Stubs\Deal;
use CoreX\Auth\Tests\Stubs\FakeModuleRegistry;
use CoreX\Auth\Tests\Stubs\FakeTenancyManager;
use CoreX\Auth\Tests\Stubs\FakeWorkspaceScope;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Registry\ArrayEntityRegistry;
use CoreX\Modules\Registry\EntityDefinition;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\WorkspaceRef;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses()->group('pg');

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

    $this->grantScope = function (Role $role, Scope $scope, ?string $contextType = null, ?string $contextId = null): void {
        RoleMatrixRow::query()->create([
            'role_id' => $role->id,
            'entity_key' => 'crm.deal',
            'action' => 'read',
            'scope' => $scope->value,
            'context_type' => $contextType,
            'context_id' => $contextId,
        ]);
    };

    $this->assignRole = function (Role $role, string $principalId, PrincipalType $type = PrincipalType::User, ?string $workspaceId = null): void {
        RoleAssignment::query()->create([
            'role_id' => $role->id,
            'principal_type' => $type->value,
            'principal_id' => $principalId,
            'workspace_id' => $workspaceId,
        ]);
    };

    $this->makeUser = function (?string $departmentId = null): string {
        $id = (string) Str::uuid7();
        DB::table('users')->insert(['id' => $id, 'department_id' => $departmentId]);

        return $id;
    };

    $this->makeDepartment = function (string $workspaceId, ?string $parentId, string $path, string $name = 'Dept'): string {
        $id = (string) Str::uuid7();
        DB::table('sys_departments')->insert([
            'id' => $id,
            'workspace_id' => $workspaceId,
            'parent_id' => $parentId,
            'path' => $path,
            'name' => $name,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    };

    $this->makeWorkspace = function (string $path, array $settings = []): string {
        $id = (string) Str::uuid7();
        DB::table('wsp_workspaces')->insert([
            'id' => $id,
            'path' => $path,
            'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
        ]);

        return $id;
    };
});

// AC-23 — own.
it('own scope on the builder narrows to only the rows the principal owns', function (): void {
    $role = ($this->newRole)();
    ($this->grantScope)($role, Scope::Own);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    ($this->assignRole)($role, $principalId);

    $mine = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $principalId]);
    Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $ids = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->all();

    expect($ids)->toBe([$mine->id]);
});

// AC-23 — dept, against a real users/sys_departments fixture.
it("dept scope on the builder narrows to rows owned by users in the principal's own department", function (): void {
    $workspaceId = ($this->makeWorkspace)('wsDept');
    $deptA = ($this->makeDepartment)($workspaceId, null, 'deptA');
    $deptB = ($this->makeDepartment)($workspaceId, null, 'deptB');

    $principalId = ($this->makeUser)($deptA);
    $ownerSameDept = ($this->makeUser)($deptA);
    $ownerOtherDept = ($this->makeUser)($deptB);

    $role = ($this->newRole)();
    ($this->grantScope)($role, Scope::Dept);
    ($this->compileRole)($role->id);
    ($this->assignRole)($role, $principalId);

    $inDept = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $ownerSameDept]);
    Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $ownerOtherDept]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $ids = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->all();

    expect($ids)->toBe([$inDept->id]);
});

// AC-23 — dept_tree, ONE `path <@` subquery, no recursion (premortem A6).
it('dept_tree scope on the builder narrows via the department subtree in a single path <@ predicate', function (): void {
    $workspaceId = ($this->makeWorkspace)('wsTree');
    $parentDept = ($this->makeDepartment)($workspaceId, null, 'deptRoot');
    $childDept = ($this->makeDepartment)($workspaceId, $parentDept, 'deptRoot.child');
    $unrelatedDept = ($this->makeDepartment)($workspaceId, null, 'deptOther');

    $principalId = ($this->makeUser)($parentDept);
    $ownerInSubtree = ($this->makeUser)($childDept);
    $ownerOutsideSubtree = ($this->makeUser)($unrelatedDept);

    $role = ($this->newRole)();
    ($this->grantScope)($role, Scope::DeptTree);
    ($this->compileRole)($role->id);
    ($this->assignRole)($role, $principalId);

    $inSubtree = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $ownerInSubtree]);
    Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $ownerOutsideSubtree]);

    DB::enableQueryLog();
    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $ids = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->all();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($ids)->toBe([$inSubtree->id])
        ->and(collect($queries)->filter(fn (array $q): bool => str_contains((string) $q['query'], '<@'))->count())->toBe(1);
});

// AC-23/AC-20 — workspace, plus D107 no-context narrowing.
it("workspace scope on the builder shows only the context workspace's rows plus account-wide rows", function (): void {
    $workspaceId = ($this->makeWorkspace)('wsA');
    $otherWorkspaceId = ($this->makeWorkspace)('wsB');

    app()->instance(TenancyManager::class, new FakeTenancyManager(new WorkspaceRef($workspaceId, 'a', 'team', 'wsA', null)));

    $role = ($this->newRole)();
    ($this->grantScope)($role, Scope::Workspace);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    ($this->assignRole)($role, $principalId);

    $matching = Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => $workspaceId]);
    $accountWide = Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => null]);
    Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => $otherWorkspaceId]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $ids = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing([$matching->id, $accountWide->id]);
});

it('workspace scope shows only account-wide rows when no workspace is narrowed in context (D107)', function (): void {
    app()->instance(TenancyManager::class, new FakeTenancyManager(null));

    $role = ($this->newRole)();
    ($this->grantScope)($role, Scope::Workspace);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    ($this->assignRole)($role, $principalId);

    $accountWide = Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => null]);
    Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => (string) Str::uuid7()]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $ids = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->all();

    expect($ids)->toBe([$accountWide->id]);
});

// D117 — cascade_view, negative before positive (Code Guidance).
it('cascade_view off hides child-workspace rows; on shows them', function (): void {
    $parentWs = ($this->makeWorkspace)('wsParent');
    $childWs = ($this->makeWorkspace)('wsParent.child');

    app()->instance(TenancyManager::class, new FakeTenancyManager(new WorkspaceRef($parentWs, 'p', 'team', 'wsParent', null)));

    $role = ($this->newRole)();
    ($this->grantScope)($role, Scope::Workspace);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    ($this->assignRole)($role, $principalId);

    $childRow = Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => $childWs]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    $idsOff = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->all();
    expect($idsOff)->not->toContain($childRow->id);

    DB::table('wsp_workspaces')->where('id', $parentWs)->update(['settings' => json_encode(['cascade_view' => true], JSON_THROW_ON_ERROR)]);

    $idsOn = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->all();
    expect($idsOn)->toContain($childRow->id);
});

// D109 — account lifts EXACTLY the pinned scope class, never an unrelated one.
it('account scope lifts exactly the pinned workspace scope class, never an unrelated global scope', function (): void {
    config(['corex-auth.workspace_scope_class' => FakeWorkspaceScope::class]);
    app()->instance(
        AuthOptions::class,
        AuthOptions::fromArray((array) config('corex-auth')),
    );

    $workspaceId = ($this->makeWorkspace)('wsAccount');
    $otherWorkspaceId = ($this->makeWorkspace)('wsAccountOther');
    app()->instance(TenancyManager::class, new FakeTenancyManager(new WorkspaceRef($workspaceId, 'a', 'team', 'wsAccount', null)));

    Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => $workspaceId]);
    $hiddenByWorkspace = Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => $otherWorkspaceId]);
    $hiddenBySentinel = Deal::query()->create(['id' => (string) Str::uuid7(), 'workspace_id' => $workspaceId]);

    $query = Deal::query()
        ->withGlobalScope(FakeWorkspaceScope::class, new FakeWorkspaceScope)
        ->withGlobalScope('sentinel-guard', function (Builder $builder) use ($hiddenBySentinel): void {
            $builder->where($builder->getModel()->qualifyColumn('id'), '!=', $hiddenBySentinel->id);
        });

    // Sanity: with both scopes active and no scope=account lift yet, the
    // other-workspace row is hidden by FakeWorkspaceScope.
    expect($query->clone()->pluck('id')->all())->not->toContain($hiddenByWorkspace->id);

    $identity = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $result = app(ScopePredicate::class)->applyTo($query, Scope::Account, $identity)->pluck('id')->all();

    expect($result)->toContain($hiddenByWorkspace->id)
        ->and($result)->not->toContain($hiddenBySentinel->id);
});

// AC-23 — max-scope-wins across multiple roles, mirrored on the builder path.
it('resolves the max weight across multiple roles held by the same principal', function (): void {
    $roleLow = ($this->newRole)();
    $roleHigh = ($this->newRole)();
    ($this->grantScope)($roleLow, Scope::Own);
    ($this->grantScope)($roleHigh, Scope::Account);
    ($this->compileRole)($roleLow->id);
    ($this->compileRole)($roleHigh->id);

    $principalId = (string) Str::uuid7();
    ($this->assignRole)($roleLow, $principalId);
    ($this->assignRole)($roleHigh, $principalId);

    $mine = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $principalId]);
    $other = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $ids = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing([$mine->id, $other->id]);
});

// AC-24 — a context row narrows (to none) the builder scope for its own context only.
it('a context row narrows the builder scope for its own context only', function (): void {
    $role = ($this->newRole)();
    $stageId = (string) Str::uuid7();
    ($this->grantScope)($role, Scope::Account);
    ($this->grantScope)($role, Scope::None, 'stage', $stageId);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    ($this->assignRole)($role, $principalId);

    Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);

    $baseIds = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->all();
    $narrowedIds = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read', new RecordContext('stage', $stageId))->pluck('id')->all();

    expect($baseIds)->toHaveCount(1)
        ->and($narrowedIds)->toBeEmpty();
});

// AC-25 — agent principals degrade own/dept/dept_tree to none on the builder path; account does not degrade.
it('agent principals degrade own/dept/dept_tree to none on the builder path, but not account', function (Scope $scope, bool $expectEmpty): void {
    $role = ($this->newRole)(['assignable_to' => [PrincipalType::User->value, PrincipalType::Agent->value]]);
    ($this->grantScope)($role, $scope);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    ($this->assignRole)($role, $principalId, PrincipalType::Agent);

    Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $principalId]);
    Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]);

    $identity = new PrincipalIdentity(PrincipalType::Agent, $principalId);
    $ids = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->all();

    expect($ids)->toHaveCount($expectEmpty ? 0 : 2);
})->with([
    'own degrades' => [Scope::Own, true],
    'dept degrades' => [Scope::Dept, true],
    'dept_tree degrades' => [Scope::DeptTree, true],
    'account does not degrade' => [Scope::Account, false],
]);

// D110 — parity: apply() and canOnRecord() agree for every scope × principal type.
it('apply() and canOnRecord() agree for every scope across every principal type', function (Scope $scope, PrincipalType $type): void {
    $role = ($this->newRole)(['assignable_to' => [PrincipalType::User->value, PrincipalType::Agent->value, PrincipalType::ApiKey->value]]);
    ($this->grantScope)($role, $scope);
    ($this->compileRole)($role->id);

    $principalId = (string) Str::uuid7();
    ($this->assignRole)($role, $principalId, $type);

    $mine = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $principalId, 'workspace_id' => null]);
    $other = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7(), 'workspace_id' => null]);

    $identity = new PrincipalIdentity($type, $principalId);
    $checker = app(AccessChecker::class);

    $builderIds = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->sort()->values()->all();
    $recordIds = collect([$mine, $other])
        ->filter(fn (Deal $deal): bool => $checker->canOnRecord($identity, 'read', $deal))
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($builderIds)->toBe($recordIds);
})->with(fn (): array => collect(Scope::cases())->crossJoin(PrincipalType::cases())->all());

it('matches real department records to the same IDs through ScopeResolver and AccessChecker', function (Scope $scope, array $expectedLabels): void {
    $workspaceId = ($this->makeWorkspace)('recordParity');
    $parentDepartmentId = ($this->makeDepartment)($workspaceId, null, 'recordParity.parent');
    $childDepartmentId = ($this->makeDepartment)($workspaceId, $parentDepartmentId, 'recordParity.parent.child');
    $siblingDepartmentId = ($this->makeDepartment)($workspaceId, null, 'recordParitySibling');

    $principalId = ($this->makeUser)($parentDepartmentId);
    $sameDepartmentOwnerId = ($this->makeUser)($parentDepartmentId);
    $childDepartmentOwnerId = ($this->makeUser)($childDepartmentId);
    $siblingDepartmentOwnerId = ($this->makeUser)($siblingDepartmentId);
    $ownerWithoutDepartmentId = ($this->makeUser)();

    $role = ($this->newRole)();
    ($this->grantScope)($role, $scope);
    ($this->compileRole)($role->id);
    ($this->assignRole)($role, $principalId);

    $records = [
        'same' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $sameDepartmentOwnerId]),
        'child' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $childDepartmentOwnerId]),
        'sibling' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $siblingDepartmentOwnerId]),
        'owner_without_department' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $ownerWithoutDepartmentId]),
        'missing_owner' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]),
        'null_owner' => Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => null]),
    ];

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $builderIds = app(ScopeResolver::class)->apply(Deal::query(), $identity, 'crm.deal', 'read')->pluck('id')->sort()->values()->all();
    $recordIds = collect($records)
        ->filter(fn (Deal $record): bool => app(AccessChecker::class)->canOnRecord($identity, 'read', $record))
        ->pluck('id')
        ->sort()
        ->values()
        ->all();
    $expectedIds = collect($expectedLabels)
        ->map(fn (string $label): string => $records[$label]->id)
        ->sort()
        ->values()
        ->all();

    expect($builderIds)->toBe($expectedIds)
        ->and($recordIds)->toBe($expectedIds);
})->with([
    'dept: same department only' => [Scope::Dept, ['same']],
    'dept_tree: same department and descendant' => [Scope::DeptTree, ['same', 'child']],
]);

// Monotonicity holds ONLY on a fixture with the NAMED invariants below (premortem A7) — asserted here, not assumed globally.
it('own ⊆ dept ⊆ dept_tree ⊆ workspace ⊆ account on a fixture where every owner is in-subtree and in-workspace', function (): void {
    $workspaceId = ($this->makeWorkspace)('wsMono');
    $parentDept = ($this->makeDepartment)($workspaceId, null, 'deptMono');
    $childDept = ($this->makeDepartment)($workspaceId, $parentDept, 'deptMono.child');

    $principalId = ($this->makeUser)($parentDept);
    $ownerSameDept = ($this->makeUser)($parentDept);
    $ownerSubtree = ($this->makeUser)($childDept);

    $ownRecord = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $principalId, 'workspace_id' => $workspaceId]);
    $deptRecord = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $ownerSameDept, 'workspace_id' => $workspaceId]);
    $subtreeRecord = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $ownerSubtree, 'workspace_id' => $workspaceId]);

    app()->instance(TenancyManager::class, new FakeTenancyManager(new WorkspaceRef($workspaceId, 'm', 'team', 'wsMono', null)));

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $predicate = app(ScopePredicate::class);
    $rowsFor = fn (Scope $scope): array => $predicate->applyTo(Deal::query(), $scope, $identity)->pluck('id')->all();

    $own = $rowsFor(Scope::Own);
    $dept = $rowsFor(Scope::Dept);
    $deptTree = $rowsFor(Scope::DeptTree);
    $workspace = $rowsFor(Scope::Workspace);
    $account = $rowsFor(Scope::Account);

    expect($own)->toBe([$ownRecord->id])
        ->and(collect($own)->diff($dept))->toBeEmpty()
        ->and(collect($dept)->diff($deptTree))->toBeEmpty()
        ->and(collect($deptTree)->diff($workspace))->toBeEmpty()
        ->and(collect($workspace)->diff($account))->toBeEmpty()
        ->and($deptTree)->toEqualCanonicalizing([$ownRecord->id, $deptRecord->id, $subtreeRecord->id]);
});

// The counter-example this plan's Code Guidance requires: monotonicity is NOT a general property.
it('is NOT monotone in general — dept_tree can include a record outside the visible workspace', function (): void {
    $workspaceId = ($this->makeWorkspace)('wsCounter');
    $otherWorkspaceId = ($this->makeWorkspace)('wsCounterOther');
    $dept = ($this->makeDepartment)($workspaceId, null, 'deptCounter');

    $principalId = ($this->makeUser)($dept);
    $ownerInSubtreeDifferentWorkspace = ($this->makeUser)($dept);

    $crossWorkspaceRecord = Deal::query()->create([
        'id' => (string) Str::uuid7(),
        'owner_id' => $ownerInSubtreeDifferentWorkspace,
        'workspace_id' => $otherWorkspaceId,
    ]);

    app()->instance(TenancyManager::class, new FakeTenancyManager(new WorkspaceRef($workspaceId, 'c', 'team', 'wsCounter', null)));

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $predicate = app(ScopePredicate::class);

    $deptTreeIds = $predicate->applyTo(Deal::query(), Scope::DeptTree, $identity)->pluck('id')->all();
    $workspaceIds = $predicate->applyTo(Deal::query(), Scope::Workspace, $identity)->pluck('id')->all();

    expect($deptTreeIds)->toContain($crossWorkspaceRecord->id)
        ->and($workspaceIds)->not->toContain($crossWorkspaceRecord->id);
});

// department_id = NULL denies dept/dept_tree — never "the whole scope".
it('denies dept/dept_tree on the builder when the principal has no department', function (Scope $scope): void {
    $principalId = ($this->makeUser)(null);
    $owner = ($this->makeUser)(null);
    Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $owner]);

    $identity = new PrincipalIdentity(PrincipalType::User, $principalId);
    $ids = app(ScopePredicate::class)->applyTo(Deal::query(), $scope, $identity)->pluck('id')->all();

    expect($ids)->toBeEmpty();
})->with(fn (): array => [Scope::Dept, Scope::DeptTree]);

// Fail-closed when users/sys_departments do not exist at all (boxed profile, AC-28) — independent of driver.
it('denies dept/dept_tree on the builder when users/sys_departments tables do not exist', function (Scope $scope): void {
    $this->resetPgSchema();
    $this->migrateAzGuard();
    $this->migrateAutSchema();

    Schema::create('deals', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('owner_id')->nullable();
        $table->uuid('workspace_id')->nullable();
    });

    Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]);

    $identity = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $ids = app(ScopePredicate::class)->applyTo(Deal::query(), $scope, $identity)->pluck('id')->all();

    expect($ids)->toBeEmpty();
})->with(fn (): array => [Scope::Dept, Scope::DeptTree]);

// D114 — MatrixValidator's second rule: declaration drift produces a warning, never an exception, at matrix-authoring time.
it('MatrixValidator warns when a matrix row names a scope the entity is missing the capability for', function (): void {
    app(ArrayEntityRegistry::class)->register(new EntityDefinition(
        handle: 'crm.drift',
        model: Deal::class,
        table: 'deals',
        module: 'corex/access-tests',
        labelSingular: 'Drift',
        labelPlural: 'Drifts',
        capabilities: [], // Ownable NOT declared, though `deals.owner_id` exists
        fields: [],
        titleAttribute: null,
    ));

    $role = ($this->newRole)();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.drift', 'action' => 'read', 'scope' => 'own']);

    $warnings = (new MatrixValidator(app(EntityRegistry::class)))->validate($role);

    expect($warnings)->not->toBeEmpty()
        ->and($warnings[0])->toContain('crm.drift')
        ->and($warnings[0])->toContain('ownable');
});
