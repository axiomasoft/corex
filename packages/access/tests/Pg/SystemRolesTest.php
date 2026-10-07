<?php

declare(strict_types=1);

use CoreX\Access\Database\Seeders\SystemRolesSeeder;
use CoreX\Access\Enums\PrincipalType;
use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\Internal\Models\RoleMatrixRow;
use CoreX\Access\Roles\Internal\RoleCloner;
use Illuminate\Auth\Access\AuthorizationException;

uses()->group('pg');

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateAutSchema();
});

// AC-21 — seeds exactly the 3 system roles, each is_system with an empty matrix.
it('seeds exactly the 3 system roles with an empty matrix', function (): void {
    (new SystemRolesSeeder)->run();

    $roles = Role::query()->orderBy('slug')->get();

    expect($roles->pluck('slug')->all())->toBe(['admin', 'employee', 'owner'])
        ->and($roles->every(fn (Role $role): bool => $role->is_system))->toBeTrue()
        ->and($roles->every(fn (Role $role): bool => $role->assignable_to === [PrincipalType::User->value]))->toBeTrue();

    foreach ($roles as $role) {
        expect(RoleMatrixRow::query()->where('role_id', $role->id)->count())->toBe(0);
    }
});

it('is idempotent — running the seeder twice does not duplicate roles', function (): void {
    (new SystemRolesSeeder)->run();
    (new SystemRolesSeeder)->run();

    expect(Role::query()->count())->toBe(3);
});

// AC-21 — editing a system role outside the seeder/release path throws.
it('throws when saving an existing system role through the normal path', function (): void {
    (new SystemRolesSeeder)->run();
    $owner = Role::query()->where('slug', 'owner')->firstOrFail();

    expect(function () use ($owner): void {
        $owner->name = 'Renamed owner';
        $owner->save();
    })->toThrow(AuthorizationException::class);
});

it('throws when deleting a system role', function (): void {
    (new SystemRolesSeeder)->run();
    $admin = Role::query()->where('slug', 'admin')->firstOrFail();

    expect(fn () => $admin->delete())->toThrow(AuthorizationException::class);
});

it('cannot clear system status to bypass role and related matrix guards', function (): void {
    (new SystemRolesSeeder)->run();
    $role = Role::query()->where('slug', 'owner')->firstOrFail();
    $role->is_system = false;
    expect(fn () => $role->save())->toThrow(AuthorizationException::class)
        ->and(fn () => $role->delete())->toThrow(AuthorizationException::class);
    $matrix = new RoleMatrixRow(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    $matrix->setRelation('role', $role);
    expect(fn () => $matrix->save())->toThrow(AuthorizationException::class);
    expect($role->fresh()->is_system)->toBeTrue();
});

it('throws when saving a matrix row that belongs to a system role, unless flagged', function (): void {
    (new SystemRolesSeeder)->run();
    $employee = Role::query()->where('slug', 'employee')->firstOrFail();

    expect(fn () => RoleMatrixRow::query()->create(['role_id' => $employee->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']))
        ->toThrow(AuthorizationException::class);

    $row = RoleMatrixRow::withSystemMutationAllowed(fn (): RoleMatrixRow => RoleMatrixRow::query()->create([
        'role_id' => $employee->id,
        'entity_key' => 'crm.deal',
        'action' => 'read',
        'scope' => 'account',
    ]));

    expect($row->exists)->toBeTrue();
});

// AC-21 — allows saving/deleting the system role itself through the flagged path (release/seeder).
it('allows editing a system role through the flagged path', function (): void {
    (new SystemRolesSeeder)->run();
    $owner = Role::query()->where('slug', 'owner')->firstOrFail();

    Role::withSystemMutationAllowed(function () use ($owner): void {
        $owner->description = 'Updated by a release migration';
        $owner->save();
    });

    expect($owner->refresh()->description)->toBe('Updated by a release migration');
});

// AC-21 — cloning creates an editable, non-system copy carrying cloned_from_id and the copied matrix.
it('clones a system role into an editable copy with cloned_from_id and a copied matrix', function (): void {
    (new SystemRolesSeeder)->run();
    $admin = Role::query()->where('slug', 'admin')->firstOrFail();

    RoleMatrixRow::withSystemMutationAllowed(fn (): RoleMatrixRow => RoleMatrixRow::query()->create([
        'role_id' => $admin->id,
        'entity_key' => 'crm.deal',
        'action' => 'read',
        'scope' => 'account',
    ]));

    $clone = (resolve(RoleCloner::class))->clone($admin, 'admin-custom');

    expect($clone->is_system)->toBeFalse()
        ->and($clone->cloned_from_id)->toBe($admin->id)
        ->and(RoleMatrixRow::query()->where('role_id', $clone->id)->count())->toBe(1);

    // The clone is NOT system — the normal path may edit it freely.
    $clone->description = 'Customized';
    $clone->save();
    expect($clone->refresh()->description)->toBe('Customized');
});

// D103 — cloning 'owner' does not carry any stored wildcard; the clone starts with the same empty/copied matrix as any other clone.
it('does not grant owner-wildcard capability to a clone of owner', function (): void {
    (new SystemRolesSeeder)->run();
    $owner = Role::query()->where('slug', 'owner')->firstOrFail();

    $clone = (resolve(RoleCloner::class))->clone($owner, 'owner-custom');

    expect($clone->slug)->not->toBe('owner')
        ->and(RoleMatrixRow::query()->where('role_id', $clone->id)->count())->toBe(0);
});
