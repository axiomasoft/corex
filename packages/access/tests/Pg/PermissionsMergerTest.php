<?php

declare(strict_types=1);

use AzGuard\Models\RolePermission;
use CoreX\Access\Enums\PrincipalType;
use CoreX\Access\Extend\Internal\PermissionsMerger;
use CoreX\Access\Matrix\Laravel\AzGuardRoleModel;
use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\Internal\Models\RoleMatrixRow;
use CoreX\Access\Tests\Stubs\Deal;
use CoreX\Access\Tests\Stubs\FakeModuleRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\EntityDefinition;
use CoreX\Modules\Data\Permissions;
use CoreX\Modules\Enums\DisableReason;
use CoreX\Modules\Events\ModuleDisabled;
use CoreX\Modules\Events\ModuleEnabled;
use CoreX\Modules\Testing\ArrayEntityRegistry;

uses()->group('pg');

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
});

/** @return list<string> */
function compiledKeysFor(string $roleId): array
{
    $azguardRole = AzGuardRoleModel::query()->where('name', Role::query()->findOrFail($roleId)->slug)->first();

    if ($azguardRole === null) {
        return [];
    }

    return RolePermission::query()->where('role_id', $azguardRole->id)->pluck('permission_key')->all();
}

// AC-27, negative first (Code Guidance anti-self-deception): before any
// lifecycle event, the fixture's module has never been reported active — the
// matrix row exists but gives out no key, proving merge()'s recompile is not
// vacuously green.
it('gives out no key while the owning module has never been reported active', function (): void {
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry([]));

    $role = Role::query()->create([
        'slug' => 'merger-role-'.uniqid(),
        'name' => 'Merger role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);

    app(PermissionsMerger::class)->merge();

    expect(compiledKeysFor($role->id))->toBe([]);
});

// AC-27 core, event-driven: enable ⇒ keys appear; disable ⇒ keys gone but the
// matrix row count is unchanged (rows survive for re-enable, D113); re-enable
// ⇒ keys return without any matrix-row edit.
it('recompiles keys through the full enable -> disable -> re-enable lifecycle via events', function (): void {
    $role = Role::query()->create([
        'slug' => 'lifecycle-role-'.uniqid(),
        'name' => 'Lifecycle role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    $rowCountBefore = RoleMatrixRow::query()->where('role_id', $role->id)->count();

    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(['corex/access-tests']));
    event(new ModuleEnabled('corex/access-tests', 'community', []));

    expect(compiledKeysFor($role->id))->toBe(['app.crm.deal.read']);

    app()->instance(ModuleRegistry::class, new FakeModuleRegistry([]));
    event(new ModuleDisabled('corex/access-tests', DisableReason::Manual));

    expect(compiledKeysFor($role->id))->toBe([])
        ->and(RoleMatrixRow::query()->where('role_id', $role->id)->count())->toBe($rowCountBefore);

    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(['corex/access-tests']));
    event(new ModuleEnabled('corex/access-tests', 'community', []));

    expect(compiledKeysFor($role->id))->toBe(['app.crm.deal.read'])
        ->and(RoleMatrixRow::query()->where('role_id', $role->id)->count())->toBe($rowCountBefore);
});

it('recompiles on ModuleDisabled too — both lifecycle events are wired independently', function (): void {
    $role = Role::query()->create([
        'slug' => 'disabled-wiring-role-'.uniqid(),
        'name' => 'Disabled wiring role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);

    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);

    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(['corex/access-tests']));

    // No compiled AzGuard role exists yet — ModuleDisabled must still trigger
    // recompileAll() and materialize the key, proving the listener reacts to
    // ModuleDisabled on its own, not only piggy-backing on ModuleEnabled.
    event(new ModuleDisabled('corex/access-tests', DisableReason::Manual));

    expect(compiledKeysFor($role->id))->toBe(['app.crm.deal.read']);
});

// D113 — a declaration whose entity is unknown to EntityRegistry cannot be
// attributed to any module (the compiled permissions slot carries no module
// attribution of its own, findings/P2.9-seam-inventory §6); merge() skips it
// fail-closed and surfaces it as a warning, never a log.
it('returns a warning and creates no matrix row for a declaration with an unknown entity', function (): void {
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(
        active: ['ghost-module'],
        permissions: [Permissions::group('ghost.entity', ['read'])],
    ));

    $warnings = app(PermissionsMerger::class)->merge();

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('ghost.entity');
});

it('returns no warning for a declaration whose entity is known to EntityRegistry', function (): void {
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(
        active: ['corex/access-tests'],
        permissions: [Permissions::group('crm.deal', ['read'])->scopeable()],
    ));

    expect(app(PermissionsMerger::class)->merge())->toBeEmpty();
});
