<?php

declare(strict_types=1);

use CoreX\Access\Access\Internal\MatrixPermissions;
use CoreX\Access\Access\Laravel\PrincipalIdentity;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Exceptions\ExactVisibilityUnavailable;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\Access\Roles\DirectGrants;
use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\Internal\Models\RoleAssignment;
use CoreX\Access\Roles\Internal\Models\RoleMatrixRow;
use CoreX\Access\Roles\RoleManager;
use CoreX\Access\Tests\Authorization\ScopedProject;
use CoreX\Access\Tests\Stubs\Deal;
use CoreX\Access\Tests\Stubs\FakeModuleRegistry;
use CoreX\Context\Enums\PrincipalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Enums\Entities\Capability;
use Packman\Modules\Testing\ArrayEntityRegistry;

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
        ->and(resolve(MatrixPermissions::class)->scopeFor($principal, 'crm.deal', 'read'))->toBe(Scope::None)
        ->and($checker->canOnRecord($principal, 'read', $record))->toBeTrue()
        ->and(resolve(ScopeResolver::class)->apply(Deal::query(), $principal, 'crm.deal', 'read')->pluck('id')->all())->toBe([$record->id]);
});

it('does not broaden a model-defined tenant boundary into an unrestricted matrix query', function (): void {
    Schema::create('projects', static function (Blueprint $table): void {
        $table->id();
        $table->string('account_id');
        $table->timestamps();
    });
    resolve(ArrayEntityRegistry::class)->register(new EntityDefinition(
        'crm.project', ScopedProject::class, 'projects', 'corex/access-tests', 'Project', 'Projects', [], [], null,
    ));
    $user = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
    $resolver = resolve(ScopeResolver::class);
    expect($resolver->apply(ScopedProject::query(), $user, 'crm.project', 'read')->count())->toBe(0);
    resolve(DirectGrants::class)->grant($user, 'crm.project', 'read');
    $foreign = ScopedProject::query()->create(['account_id' => 'foreign']);
    $checker = resolve(AccessChecker::class);

    expect($checker->allowed($user, 'crm.project', 'read'))->toBeTrue()
        ->and($checker->canOnRecord($user, 'read', $foreign))->toBeFalse()
        ->and(fn () => $resolver->apply(ScopedProject::query(), $user, 'crm.project', 'read'))->toThrow(ExactVisibilityUnavailable::class);
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
        ->and(resolve(MatrixPermissions::class)->scopeFor($principal, $entityKey, 'read'))->toBe(Scope::None)
        ->and($checker->canOnRecord($principal, 'read', $recordForCheck))->toBeFalse()
        ->and(resolve(ScopeResolver::class)->apply(Deal::query(), $principal, $entityKey, 'read')->pluck('id')->all())->toBeEmpty();
})->with([
    'registered entity with disabled module' => [true],
    'unknown entity key' => [false],
]);

it('expires role assignments and direct grants at the supplied instant in a non-UTC tenant connection', function (): void {
    DB::statement("SET TIME ZONE 'Europe/Astrakhan'");

    try {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $past = $now->modify('-60 seconds');
        $future = $now->modify('+60 seconds');
        $user = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid7());
        $roles = resolve(RoleManager::class);
        $role = $roles->create('sales', 'Sales');
        $roles->setScope($role, 'crm.deal', 'view', Scope::Account);
        $access = resolve(AccessChecker::class);

        $roles->assign($role, $user, expiresAt: $past);
        expect($access->allowed($user, 'crm.deal', 'view'))->toBeFalse();
        $roles->assign($role, $user, expiresAt: $future);
        expect($access->allowed($user, 'crm.deal', 'view'))->toBeTrue();

        $grants = resolve(DirectGrants::class);
        $grants->grant($user, 'crm.deal', 'update', $past);
        expect($access->allowed($user, 'crm.deal', 'update'))->toBeFalse();
        $grants->grant($user, 'crm.deal', 'update', $future);
        expect($access->allowed($user, 'crm.deal', 'update'))->toBeTrue();
    } finally {
        DB::statement('RESET TIME ZONE');
    }
});
