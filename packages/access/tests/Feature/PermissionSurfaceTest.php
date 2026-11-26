<?php

declare(strict_types=1);

use CoreX\Access\Access\Internal\PermissionSurface;
use CoreX\Access\Access\Internal\ScopeAvailability;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Matrix\Internal\ActiveEntityFilter;
use CoreX\Access\Tests\Stubs\FakeModuleRegistry;
use CoreX\Access\Tests\Stubs\Project;
use CoreX\Context\Internal\SingleAccountResolver;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Data\Extenders\Permissions;
use Packman\Modules\Enums\Entities\Capability;
use Packman\Modules\Testing\ArrayEntityRegistry;

function makeSurface(ArrayEntityRegistry $registry, FakeModuleRegistry $modules): PermissionSurface
{
    $activeEntities = new ActiveEntityFilter($registry, $modules, new SingleAccountResolver);

    return new PermissionSurface($modules, $activeEntities, new ScopeAvailability($registry, resolve(ConnectionResolverInterface::class)));
}

// isScopeable() ⇒ every declared action row, scopes normalized per-entity capability.
// 'projects' (tests/database/migrations) has no owner_id/workspace_id, so own/dept/
// dept_tree/workspace collapse to account — only none/account survive dedup.
it('lists every declared action with the entity scope set for an active scopeable declaration', function (): void {
    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'surface.deal',
        model: Project::class,
        table: 'projects',
        module: 'crm',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [],
        fields: [],
        titleAttribute: null,
    ));

    $modules = new FakeModuleRegistry(
        active: ['crm'],
        permissions: [Permissions::group('surface.deal', ['read', 'update'])->scopeable()],
    );

    $rows = makeSurface($registry, $modules)->forActiveModules();

    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'action'))->toBe(['read', 'update'])
        ->and($rows[0]['entity_key'])->toBe('surface.deal')
        ->and($rows[0]['scopes'])->toBe([Scope::None, Scope::Account]);
});

// isScopeable() === false ⇒ only account, regardless of the entity's own capabilities.
it('returns only account for a non-scopeable declaration', function (): void {
    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'surface.readonly',
        model: Project::class,
        table: 'projects',
        module: 'crm',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [Capability::Ownable],
        fields: [],
        titleAttribute: null,
    ));

    $modules = new FakeModuleRegistry(
        active: ['crm'],
        permissions: [Permissions::group('surface.readonly', ['read'])],
    );

    $rows = makeSurface($registry, $modules)->forActiveModules();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['scopes'])->toBe([Scope::Account]);
});

// Every distinct Scope survives when the entity declares both capabilities and
// the matching physical columns exist — nothing degrades, nothing collapses.
it('includes every distinct scope when the entity declares both ownable and workspace_scoped', function (): void {
    Schema::create('surface_full_probe', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('owner_id')->nullable();
        $table->string('workspace_id')->nullable();
    });

    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'surface.full',
        model: Project::class,
        table: 'surface_full_probe',
        module: 'crm',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [Capability::Ownable, Capability::WorkspaceScoped],
        fields: [],
        titleAttribute: null,
    ));

    $modules = new FakeModuleRegistry(
        active: ['crm'],
        permissions: [Permissions::group('surface.full', ['read'])->scopeable()],
    );

    $rows = makeSurface($registry, $modules)->forActiveModules();

    expect($rows[0]['scopes'])->toBe([Scope::None, Scope::Own, Scope::Dept, Scope::DeptTree, Scope::Workspace, Scope::Account]);
});

// parity with the compiler/catalog filter — an inactive module's
// declarations never reach the UI-matrix surface either.
it('excludes a declaration whose owning module is inactive', function (): void {
    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'surface.inactive',
        model: Project::class,
        table: 'projects',
        module: 'crm',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [],
        fields: [],
        titleAttribute: null,
    ));

    $modules = new FakeModuleRegistry(
        active: [],
        permissions: [Permissions::group('surface.inactive', ['read'])->scopeable()],
    );

    expect(makeSurface($registry, $modules)->forActiveModules())->toBeEmpty();
});

// a declaration whose entity is unknown to EntityRegistry cannot be
// attributed to any module; fail-closed, same as PermissionsMerger.
it('excludes a declaration whose entity is unknown to EntityRegistry', function (): void {
    $registry = new ArrayEntityRegistry;

    $modules = new FakeModuleRegistry(
        active: ['crm'],
        permissions: [Permissions::group('surface.unknown', ['read'])->scopeable()],
    );

    expect(makeSurface($registry, $modules)->forActiveModules())->toBeEmpty();
});
