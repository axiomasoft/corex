<?php

declare(strict_types=1);

use AzGuard\Models\RolePermission;
use CoreX\Access\Database\Seeders\SystemRolesSeeder;
use CoreX\Access\Enums\PrincipalType;
use CoreX\Access\Matrix\AzGuardRoleModel;
use CoreX\Access\Matrix\DatabaseRoleMatrixCompiler;
use CoreX\Access\Matrix\MatrixPermissionCatalogBuilder;
use CoreX\Access\Matrix\MatrixValidator;
use CoreX\Access\Matrix\ScopeMapVersion;
use CoreX\Access\Models\Role;
use CoreX\Access\Models\RoleMatrixRow;
use CoreX\Access\Support\AuthOptions;
use CoreX\Access\Tests\Stubs\Deal;
use CoreX\Access\Tests\Stubs\FakeModuleRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Registry\ArrayEntityRegistry;
use CoreX\Modules\Registry\EntityDefinition;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

it('invalidates matrix scope only after the outer commit and never after rollback', function (): void {
    $role = Role::query()->create(['slug' => 'outer-commit', 'name' => 'Outer', 'is_system' => false, 'assignable_to' => ['user']]);
    $version = app(ScopeMapVersion::class);
    $initial = $version->current($role->id);
    DB::beginTransaction();
    makeCompiler()->compile($role->id);
    expect($version->current($role->id))->toBe($initial);
    DB::rollBack();
    expect($version->current($role->id))->toBe($initial);
    DB::beginTransaction();
    makeCompiler()->compile($role->id);
    expect($version->current($role->id))->toBe($initial);
    DB::commit();
    expect($version->current($role->id))->toBe($initial + 1);
});

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateAzGuard();
    $this->migrateAutSchema();

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
    // pre-P2.21 compiled-key expectations below stay valid. AC-27 cases
    // below flip specific entities/modules back to inactive/unknown.
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(['corex/access-tests']));
});

function makeCompiler(): DatabaseRoleMatrixCompiler
{
    return new DatabaseRoleMatrixCompiler(App::make(ScopeMapVersion::class), App::make(AuthOptions::class));
}

// AC-22 — matrix row (scope ≠ none) compiles to exactly the D100 key grammar.
it('compiles matrix rows with scope != none into panel-prefixed AzGuard keys', function (): void {
    $role = Role::query()->create([
        'slug' => 'sales-manager-'.uniqid(),
        'name' => 'Sales manager',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'delete', 'scope' => 'none']);

    makeCompiler()->compile($role->id);

    $azguardRole = AzGuardRoleModel::query()->where('name', $role->slug)->firstOrFail();

    expect(RolePermission::query()->where('role_id', $azguardRole->id)->pluck('permission_key')->all())
        ->toBe(['app.crm.deal.read']);

    $role->refresh();
    expect($role->azguard_role_id)->toBe($azguardRole->id);
});

// B-11 §7.3 п.8 — same (entity_key, action) reached by two rows (base + context override) compiles to one key, not a unique-constraint failure.
it('deduplicates identical keys produced by a base row and its context override', function (): void {
    $role = Role::query()->create([
        'slug' => 'context-role-'.uniqid(),
        'name' => 'Context role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'dept', 'context_type' => 'stage', 'context_id' => (string) Str::uuid7()]);

    makeCompiler()->compile($role->id);

    $azguardRole = AzGuardRoleModel::query()->where('name', $role->slug)->firstOrFail();
    expect(RolePermission::query()->where('role_id', $azguardRole->id)->count())->toBe(1);
});

// AC-22 — a failure mid-compile leaves neither new keys nor a bumped version (B-11 §7.3 п.8).
it('leaves no new keys and no version bump when compilation fails mid-transaction', function (): void {
    $role = Role::query()->create([
        'slug' => 'boom-role-'.uniqid(),
        'name' => 'Boom role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'update', 'scope' => 'account']);

    $scopeMapVersion = App::make(ScopeMapVersion::class);
    $versionBefore = $scopeMapVersion->current($role->id);

    RolePermission::creating(static function (RolePermission $permission): void {
        if ($permission->permission_key === 'app.crm.deal.update') {
            throw new RuntimeException('injected mid-compile failure');
        }
    });

    expect(fn () => (new DatabaseRoleMatrixCompiler($scopeMapVersion, App::make(AuthOptions::class)))->compile($role->id))
        ->toThrow(RuntimeException::class, 'injected mid-compile failure');

    $azguardRole = AzGuardRoleModel::query()->where('name', $role->slug)->first();

    expect($azguardRole)->toBeNull()
        ->and($scopeMapVersion->current($role->id))->toBe($versionBefore);
});

// recompileAll() is idempotent — a second run does not duplicate rows (unique az_role_perm_unique).
it('recompileAll is idempotent across repeated runs', function (): void {
    Role::withSystemMutationAllowed(function (): void {
        (new SystemRolesSeeder)->run();
    });

    $owner = Role::query()->where('slug', 'owner')->firstOrFail();
    RoleMatrixRow::withSystemMutationAllowed(fn (): RoleMatrixRow => RoleMatrixRow::query()->create([
        'role_id' => $owner->id,
        'entity_key' => 'crm.deal',
        'action' => 'read',
        'scope' => 'account',
    ]));

    $compiler = makeCompiler();
    $compiler->recompileAll();
    $compiler->recompileAll();

    $azguardRole = AzGuardRoleModel::query()->where('name', 'owner')->firstOrFail();
    expect(RolePermission::query()->where('role_id', $azguardRole->id)->count())->toBe(1);
});

// B-11 §5.5 / D103 — a role assignable to agent/api_key with own/dept scope gets a validator warning, never an exception.
it('warns (does not throw) when a role assignable to agent uses own/dept scope', function (): void {
    $role = Role::query()->create([
        'slug' => 'agent-role-'.uniqid(),
        'name' => 'Agent role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value, PrincipalType::Agent->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'own']);

    $warnings = (new MatrixValidator)->validate($role);

    expect($warnings)->not->toBeEmpty()
        ->and($warnings[0])->toContain('agent');
});

it('returns no warning for a user-only role using own/dept scope', function (): void {
    $role = Role::query()->create([
        'slug' => 'user-only-role-'.uniqid(),
        'name' => 'User only role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'own']);

    expect((new MatrixValidator)->validate($role))->toBeEmpty();
});

// findings/P2.8-azguard-inventory-2026-07-26.md §7 — compiled keys are reachable through AzGuard's own catalog builder.
it('exposes compiled keys through MatrixPermissionCatalogBuilder for the configured panel', function (): void {
    $role = Role::query()->create([
        'slug' => 'catalog-role-'.uniqid(),
        'name' => 'Catalog role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'delete', 'scope' => 'none']);

    $builder = new MatrixPermissionCatalogBuilder(App::make(AuthOptions::class));

    expect($builder->supports('app'))->toBeTrue()
        ->and($builder->supports('admin'))->toBeFalse();

    $keys = array_map(static fn ($definition): string => $definition->key(), $builder->build('app'));

    expect($keys)->toContain('app.crm.deal.read')
        ->and($keys)->not->toContain('app.crm.deal.delete');
});

// AC-27, negative first (Code Guidance anti-self-deception): before ActiveEntityFilter
// existed, an unattributable entity_key would still compile into a live AzGuard key —
// demonstrate the row is dead weight on its own (no EntityRegistry registration at all),
// then assert the filter keeps it dead.
it('does not compile a key for an entity_key unknown to EntityRegistry', function (): void {
    $role = Role::query()->create([
        'slug' => 'unknown-entity-role-'.uniqid(),
        'name' => 'Unknown entity role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'ghost.entity', 'action' => 'read', 'scope' => 'account']);

    makeCompiler()->compile($role->id);

    $azguardRole = AzGuardRoleModel::query()->where('name', $role->slug)->firstOrFail();
    expect(RolePermission::query()->where('role_id', $azguardRole->id)->count())->toBe(0);
});

// AC-27 core: disable ⇒ ключей нет, строки aut_role_matrix целы, re-enable ⇒ ключи вернулись
// без правки матрицы (D112/D113) — exercised directly at the compiler seam (no lifecycle
// event dispatch — that path is PermissionsMergerTest.php).
it('excludes a matrix row whose owning module is inactive, keeping the row itself intact', function (): void {
    $role = Role::query()->create([
        'slug' => 'inactive-module-role-'.uniqid(),
        'name' => 'Inactive module role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);

    // No module reports active — the fixture's own module ('corex/access-tests') is disabled here.
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry([]));

    makeCompiler()->compile($role->id);

    $azguardRole = AzGuardRoleModel::query()->where('name', $role->slug)->firstOrFail();
    expect(RolePermission::query()->where('role_id', $azguardRole->id)->count())->toBe(0)
        ->and(RoleMatrixRow::query()->where('role_id', $role->id)->count())->toBe(1);
});

it('recompiles the key once the owning module becomes active again (re-enable)', function (): void {
    $role = Role::query()->create([
        'slug' => 'reenable-role-'.uniqid(),
        'name' => 'Re-enable role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);

    app()->instance(ModuleRegistry::class, new FakeModuleRegistry([]));
    makeCompiler()->compile($role->id);

    $azguardRole = AzGuardRoleModel::query()->where('name', $role->slug)->firstOrFail();
    expect(RolePermission::query()->where('role_id', $azguardRole->id)->count())->toBe(0);

    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(['corex/access-tests']));
    makeCompiler()->compile($role->id);

    expect(RolePermission::query()->where('role_id', $azguardRole->id)->pluck('permission_key')->all())
        ->toBe(['app.crm.deal.read'])
        ->and(RoleMatrixRow::query()->where('role_id', $role->id)->count())->toBe(1);
});

// Parity (Implementation Rules — ONE filter, both call sites): MatrixPermissionCatalogBuilder
// applies the exact same active-module exclusion as the compiler.
it('excludes catalog keys whose owning module is inactive', function (): void {
    $role = Role::query()->create([
        'slug' => 'inactive-catalog-role-'.uniqid(),
        'name' => 'Inactive catalog role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);

    app()->instance(ModuleRegistry::class, new FakeModuleRegistry([]));

    $keys = array_map(
        static fn ($definition): string => $definition->key(),
        (new MatrixPermissionCatalogBuilder(App::make(AuthOptions::class)))->build('app'),
    );

    expect($keys)->not->toContain('app.crm.deal.read');
});
