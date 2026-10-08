<?php

declare(strict_types=1);

use CoreX\Access\Access\Laravel\PrincipalIdentity;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\Internal\Models\RoleAssignment;
use CoreX\Access\Roles\Internal\Models\RoleMatrixRow;
use CoreX\Access\Tests\Stubs\Deal;
use CoreX\Access\Tests\Stubs\FakeModuleRegistry;
use CoreX\Context\Enums\PrincipalType;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\EntityDefinition;
use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Testing\ArrayEntityRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

beforeEach(function (): void {
    $this->resetPgSchema();
    $this->migrateAutSchema();
    $this->migrateFixtures();

    resolve(ArrayEntityRegistry::class)->register(new EntityDefinition(
        handle: 'crm.deal', model: Deal::class, table: 'deals', module: 'corex/access-tests',
        labelSingular: 'Deal', labelPlural: 'Deals',
        capabilities: [Capability::Ownable, Capability::WorkspaceScoped], fields: [], titleAttribute: null,
    ));
    app()->instance(ModuleRegistry::class, new FakeModuleRegistry(['corex/access-tests']));
});

function nativeGrant(PrincipalIdentity $principal, ?string $expiresAt = null, ?string $revokedAt = null, string $entityKey = 'crm.deal'): void
{
    DB::table('aut_direct_grants')->insert([
        'id' => (string) Str::uuid7(),
        'principal_type' => $principal->principalType()->value,
        'principal_id' => $principal->principalId(),
        'permission_key' => "app.{$entityKey}.read",
        'expires_at' => $expiresAt,
        'revoked_at' => $revokedAt,
    ]);
}

it('honours an active native direct grant on capability, record and builder paths without a role', function (): void {
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $record = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => (string) Str::uuid7()]);
    nativeGrant($principal);
    $checker = resolve(AccessChecker::class);

    expect($checker->allowed($principal, 'crm.deal', 'read'))->toBeTrue()
        ->and($checker->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::None)
        ->and($checker->canOnRecord($principal, 'read', $record))->toBeTrue()
        ->and(resolve(ScopeResolver::class)->apply(Deal::query(), $principal, 'crm.deal', 'read')->pluck('id')->all())->toBe([$record->id]);
});

it('denies expired and revoked native direct grants', function (string $expiresAt, string $revokedAt): void {
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $record = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $principal->principalId()]);
    nativeGrant($principal, $expiresAt ?: null, $revokedAt ?: null);
    $checker = resolve(AccessChecker::class);

    expect($checker->allowed($principal, 'crm.deal', 'read'))->toBeFalse()
        ->and($checker->canOnRecord($principal, 'read', $record))->toBeFalse()
        ->and(resolve(ScopeResolver::class)->apply(Deal::query(), $principal, 'crm.deal', 'read')->pluck('id')->all())->toBeEmpty();
})->with([
    'expired' => [now()->subMinute()->toDateTimeString(), ''],
    'revoked' => ['', now()->subMinute()->toDateTimeString()],
]);

it('denies all four access paths for an inactive entity even with a matrix row and direct grant', function (bool $registered): void {
    $modules = new FakeModuleRegistry($registered ? [] : ['corex/access-tests']);
    app()->instance(ModuleRegistry::class, $modules);
    $entityKey = $registered ? 'crm.deal' : 'ghost.deal';
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $role = Role::query()->create([
        'slug' => 'role-'.Str::uuid7(), 'name' => 'Role', 'is_system' => false,
        'assignable_to' => [PrincipalType::User->value],
    ]);
    RoleMatrixRow::query()->create(['role_id' => $role->id, 'entity_key' => $entityKey, 'action' => 'read', 'scope' => 'account']);
    RoleAssignment::query()->create(['role_id' => $role->id, 'principal_type' => 'user', 'principal_id' => $principal->principalId()]);
    $record = Deal::query()->create(['id' => (string) Str::uuid7(), 'owner_id' => $principal->principalId()]);
    nativeGrant($principal, entityKey: $entityKey);
    $checker = resolve(AccessChecker::class);

    $recordForCheck = $registered ? $record : new class extends Model {};

    expect(DB::table('aut_direct_grants')->where('permission_key', "app.{$entityKey}.read")->exists())->toBeTrue()
        ->and($checker->allowed($principal, $entityKey, 'read'))->toBeFalse()
        ->and($checker->scopeFor($principal, $entityKey, 'read'))->toBe(Scope::None)
        ->and($checker->canOnRecord($principal, 'read', $recordForCheck))->toBeFalse()
        ->and(resolve(ScopeResolver::class)->apply(Deal::query(), $principal, $entityKey, 'read')->pluck('id')->all())->toBeEmpty();
})->with([
    'registered entity with disabled module' => [true],
    'unknown entity key' => [false],
]);
