<?php

declare(strict_types=1);

use AzGuard\Models\RolePermission;
use CoreX\Auth\Database\Seeders\SystemRolesSeeder;
use CoreX\Auth\Enums\PrincipalType;
use CoreX\Auth\Matrix\AzGuardRoleModel;
use CoreX\Auth\Matrix\DatabaseRoleMatrixCompiler;
use CoreX\Auth\Matrix\MatrixPermissionCatalogBuilder;
use CoreX\Auth\Matrix\MatrixValidator;
use CoreX\Auth\Matrix\ScopeMapVersion;
use CoreX\Auth\Models\Role;
use CoreX\Auth\Models\RoleMatrixRow;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

uses()->group('pg');

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateAzGuard();
    $this->migrateAutSchema();
});

function makeCompiler(): DatabaseRoleMatrixCompiler
{
    return new DatabaseRoleMatrixCompiler(App::make(ScopeMapVersion::class));
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

    expect(fn () => (new DatabaseRoleMatrixCompiler($scopeMapVersion))->compile($role->id))
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

    $builder = new MatrixPermissionCatalogBuilder;

    expect($builder->supports('app'))->toBeTrue()
        ->and($builder->supports('admin'))->toBeFalse();

    $keys = array_map(static fn ($definition): string => $definition->key(), $builder->build('app'));

    expect($keys)->toContain('app.crm.deal.read')
        ->and($keys)->not->toContain('app.crm.deal.delete');
});
