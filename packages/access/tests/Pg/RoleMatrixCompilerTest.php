<?php

declare(strict_types=1);

use CoreX\Access\Enums\PrincipalType;
use CoreX\Access\Matrix\Events\RoleMatrixCompiled;
use CoreX\Access\Matrix\Internal\DatabaseRoleMatrixCompiler;
use CoreX\Access\Matrix\Internal\MatrixValidator;
use CoreX\Access\Matrix\Internal\ScopeMapVersion;
use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\Internal\Models\RoleMatrixRow;
use CoreX\Access\Roles\Laravel\RoleMatrixData;
use CoreX\Access\Tests\Stubs\Deal;
use CoreX\Access\Tests\Stubs\FakeModuleRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\EntityDefinition;
use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Testing\ArrayEntityRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses()->group('pg');

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateAutSchema();

    app(ArrayEntityRegistry::class)->register(new EntityDefinition(
        handle: 'crm.deal', model: Deal::class, table: 'deals', module: 'corex/access-tests',
        labelSingular: 'Deal', labelPlural: 'Deals', capabilities: [Capability::Ownable], fields: [], titleAttribute: null,
    ));
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(['corex/access-tests']));
});

function nativeRole(): Role
{
    return Role::query()->create([
        'slug' => 'role-'.Str::uuid7(),
        'name' => 'Role',
        'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);
}

it('dispatches the native compilation event synchronously inside the tenant transaction', function (): void {
    $role = nativeRole();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    $seen = [];

    Event::listen(RoleMatrixCompiled::class, function (RoleMatrixCompiled $event) use (&$seen): void {
        $seen[] = [
            $event->roleId,
            DB::transactionLevel(),
            DB::table('aut_role_matrix')->where('role_id', $event->roleId)->count(),
        ];
    });

    app(DatabaseRoleMatrixCompiler::class)->compile($role->id);

    expect($seen)->toBe([[$role->id, 1, 1]])
        ->and(RoleMatrixRow::query()->where('role_id', $role->id)->count())->toBe(1);
});

it('rolls back a listener write and leaves the scope version unchanged when a listener fails', function (): void {
    $role = nativeRole();
    $versions = app(ScopeMapVersion::class);
    $initial = $versions->current($role->id);

    Event::listen(RoleMatrixCompiled::class, function (RoleMatrixCompiled $event): void {
        DB::table('aut_roles')->where('id', $event->roleId)->update(['name' => 'Uncommitted']);

        throw new RuntimeException('listener failure');
    });

    expect(fn () => app(DatabaseRoleMatrixCompiler::class)->compile($role->id))
        ->toThrow(RuntimeException::class, 'listener failure');
    expect($role->fresh()->name)->toBe('Role')
        ->and($versions->current($role->id))->toBe($initial);
});

it('invalidates scope only after outer commit and never after rollback', function (): void {
    $role = nativeRole();
    $versions = app(ScopeMapVersion::class);
    $initial = $versions->current($role->id);

    DB::beginTransaction();
    app(DatabaseRoleMatrixCompiler::class)->compile($role->id);
    expect($versions->current($role->id))->toBe($initial);
    DB::rollBack();
    expect($versions->current($role->id))->toBe($initial);

    DB::beginTransaction();
    app(DatabaseRoleMatrixCompiler::class)->compile($role->id);
    expect($versions->current($role->id))->toBe($initial);
    DB::commit();
    expect($versions->current($role->id))->toBe($initial + 1);
});

it('recompileAll emits once for every role and retains native matrix rows', function (): void {
    $first = nativeRole();
    $second = nativeRole();
    RoleMatrixRow::query()->create(['role_id' => $first->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'account']);
    $events = [];

    Event::listen(RoleMatrixCompiled::class, function (RoleMatrixCompiled $event) use (&$events): void {
        $events[] = $event->roleId;
    });
    app(DatabaseRoleMatrixCompiler::class)->recompileAll();

    expect($events)->toEqualCanonicalizing([$first->id, $second->id])
        ->and(RoleMatrixRow::query()->where('role_id', $first->id)->count())->toBe(1);
});

it('warns when a role assignable to agent uses own scope', function (): void {
    $role = nativeRole();
    $role->assignable_to = [PrincipalType::User->value, PrincipalType::Agent->value];
    $role->save();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'own']);

    $warnings = app(MatrixValidator::class)->validate(RoleMatrixData::role($role->id));

    expect($warnings)->not->toBeEmpty()
        ->and($warnings[0])->toContain('agent');
});

it('returns no agent-scope warning for a user-only role', function (): void {
    $role = nativeRole();
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => 'crm.deal', 'action' => 'read', 'scope' => 'own']);

    expect(app(MatrixValidator::class)->validate(RoleMatrixData::role($role->id)))->toBeEmpty();
});
