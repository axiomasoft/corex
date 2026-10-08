<?php

declare(strict_types=1);

use CoreX\Access\Access\Laravel\PrincipalIdentity;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Extend\Internal\PermissionsMerger;
use CoreX\Access\Matrix\Events\RoleMatrixCompiled;
use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\Internal\Models\RoleAssignment;
use CoreX\Access\Roles\Internal\Models\RoleMatrixRow;
use CoreX\Access\Tests\Stubs\Deal;
use CoreX\Access\Tests\Stubs\FakeModuleRegistry;
use CoreX\Context\Enums\PrincipalType;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\EntityDefinition;
use CoreX\Modules\Data\Permissions;
use CoreX\Modules\Enums\DisableReason;
use CoreX\Modules\Events\ModuleDisabled;
use CoreX\Modules\Events\ModuleEnabled;
use CoreX\Modules\Testing\ArrayEntityRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses()->group('pg');

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateAutSchema();

    resolve(ArrayEntityRegistry::class)->register(new EntityDefinition(
        handle: 'crm.deal', model: Deal::class, table: 'deals', module: 'corex/access-tests',
        labelSingular: 'Deal', labelPlural: 'Deals', capabilities: [], fields: [], titleAttribute: null,
    ));
});

function mergerRole(): array
{
    $role = Role::query()->create([
        'slug' => 'role-'.Str::uuid7(), 'name' => 'Role', 'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principal->principalId()]);

    return [$role, $principal];
}

it('keeps a native matrix row while inactive and denies its capability', function (): void {
    $modules = new FakeModuleRegistry([]);
    app()->instance(ModuleRegistry::class, $modules);
    [$role, $principal] = mergerRole();

    resolve(PermissionsMerger::class)->merge();

    expect(RoleMatrixRow::query()->where('role_id', $role->id)->count())->toBe(1)
        ->and(resolve(AccessChecker::class)->allowed($principal, 'crm.deal', 'read'))->toBeFalse();
});

it('reacts to enable, disable and re-enable events while preserving native matrix rows', function (): void {
    $modules = new FakeModuleRegistry([]);
    app()->instance(ModuleRegistry::class, $modules);
    [$role, $principal] = mergerRole();
    $compiled = [];
    Event::listen(RoleMatrixCompiled::class, function (RoleMatrixCompiled $event) use (&$compiled): void {
        $compiled[] = $event->roleId;
    });
    $checker = resolve(AccessChecker::class);

    expect($checker->allowed($principal, 'crm.deal', 'read'))->toBeFalse();

    $modules->setActive(['corex/access-tests']);
    event(new ModuleEnabled('corex/access-tests', 'community', []));
    expect($checker->allowed($principal, 'crm.deal', 'read'))->toBeTrue();

    $modules->setActive([]);
    event(new ModuleDisabled('corex/access-tests', DisableReason::Manual));
    expect($checker->allowed($principal, 'crm.deal', 'read'))->toBeFalse();

    $modules->setActive(['corex/access-tests']);
    event(new ModuleEnabled('corex/access-tests', 'community', []));
    expect($checker->allowed($principal, 'crm.deal', 'read'))->toBeTrue()
        ->and($compiled)->toBe([$role->id, $role->id, $role->id])
        ->and(RoleMatrixRow::query()->where('role_id', $role->id)->count())->toBe(1);
});

it('recompiles on ModuleDisabled without a prior enable event', function (): void {
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(['corex/access-tests']));
    [$role] = mergerRole();
    $compiled = [];
    Event::listen(RoleMatrixCompiled::class, function (RoleMatrixCompiled $event) use (&$compiled): void {
        $compiled[] = $event->roleId;
    });

    event(new ModuleDisabled('corex/access-tests', DisableReason::Manual));

    expect($compiled)->toBe([$role->id]);
});

it('warns and creates no matrix row for a declaration with an unknown entity', function (): void {
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(
        active: ['ghost-module'],
        permissions: [Permissions::group('ghost.entity', ['read'])],
    ));

    $warnings = resolve(PermissionsMerger::class)->merge();

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('ghost.entity')
        ->and(RoleMatrixRow::query()->count())->toBe(0);
});

it('returns no warning for a declaration whose entity is registered', function (): void {
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(
        active: ['corex/access-tests'],
        permissions: [Permissions::group('crm.deal', ['read'])->scopeable()],
    ));

    expect(resolve(PermissionsMerger::class)->merge())->toBeEmpty();
});
