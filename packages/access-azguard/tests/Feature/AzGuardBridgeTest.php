<?php

declare(strict_types=1);

use AzGuard\Contracts\PermissionLayer;
use AzGuard\Contracts\PermissionResolverInterface;
use AzGuard\Models\RolePermission;
use AzGuard\Registry\Contracts\PermissionDefinition;
use CoreX\Access\Access\Laravel\PrincipalIdentity;
use CoreX\Access\Contracts\RoleMatrixCompiler;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Access\Matrix\Contracts\ScopeMapVersions;
use CoreX\Access\Matrix\Events\RoleMatrixCompiled;
use CoreX\Access\Matrix\Internal\DatabaseRoleMatrixCompiler;
use CoreX\AccessAzguard\Internal\AutMatrixGrantSource;
use CoreX\AccessAzguard\Internal\AzGuardRoleModel;
use CoreX\AccessAzguard\Internal\MatrixPermissionCatalogBuilder;
use CoreX\AccessAzguard\Internal\RoleLinkStore;
use CoreX\AccessAzguard\Internal\SyncAzGuardRolePermissions;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Enums\PrincipalType;
use CoreX\Context\Internal\NullTenancyManager;
use CoreX\Context\Internal\SingleAccountResolver;
use CoreX\Database\Contracts\SchemaMigrator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;

beforeEach(function (): void {
    Schema::create('aut_roles', static function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('slug');
        $table->string('name');
        $table->text('assignable_to');
        $table->boolean('is_system')->default(false);
        $table->softDeletes();
        $table->timestamps();
    });
    Schema::create('aut_role_matrix', static function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('role_id');
        $table->string('entity_key');
        $table->string('action');
        $table->string('scope');
        $table->timestamps();
    });
    resolve(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-access-azguard:tenant'], DB::getDefaultConnection());
    Schema::create('aut_role_assignments', static function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('role_id');
        $table->string('principal_type');
        $table->uuid('principal_id');
        $table->uuid('workspace_id')->nullable();
        $table->timestamp('expires_at')->nullable();
    });

    $this->tenantContext = new class implements TenantContextResolver
    {
        public string $accountId = 'bridge-tenant';

        public function current(): TenantContext
        {
            return new TenantContext(new AccountRef($this->accountId, 'test', 'active', [], []));
        }
    };
    app()->instance(TenantContextResolver::class, $this->tenantContext);
    config()->set('cache.default', 'array');

    app()->instance(EntityActivity::class, new class implements EntityActivity
    {
        public function isActive(string $entityKey): bool
        {
            return $entityKey === 'crm.deal';
        }
    });
});

function bridgeRole(): string
{
    $roleId = (string) Str::uuid();
    DB::table('aut_roles')->insert(['id' => $roleId, 'slug' => 'sales', 'name' => 'Sales', 'assignable_to' => '["user"]']);
    foreach ([['crm.deal', 'read', 'account'], ['crm.deal', 'delete', 'none'], ['inactive.deal', 'read', 'account']] as [$entity, $action, $scope]) {
        DB::table('aut_role_matrix')->insert([
            'id' => (string) Str::uuid(), 'role_id' => $roleId,
            'entity_key' => $entity, 'action' => $action, 'scope' => $scope,
        ]);
    }

    return $roleId;
}

it('compiles through the registered bridge on the same tenant transaction and clean role schema', function (): void {
    $roleId = bridgeRole();
    $connection = DB::connection();
    $versions = resolve(ScopeMapVersions::class);
    $initial = $versions->current($roleId);
    $writes = [];
    $seen = [];

    DB::listen(function (QueryExecuted $query) use (&$writes): void {
        if (preg_match('/^(insert|update|delete).*\b(aut_azguard_role_links|az_guard_roles|az_guard_role_permissions)\b/i', $query->sql) === 1) {
            $writes[] = [$query->connection, $query->connection->transactionLevel()];
        }
    });
    Event::listen(RoleMatrixCompiled::class, function (RoleMatrixCompiled $event) use (&$seen, $versions): void {
        $azguardRoleId = DB::table('aut_azguard_role_links')->where('role_id', $event->roleId)->value('azguard_role_id');
        $seen[] = [
            $event->roleId,
            DB::transactionLevel(),
            RolePermission::query()->where('role_id', $azguardRoleId)->pluck('permission_key')->all(),
            $versions->current($event->roleId),
        ];
    });

    $compiler = resolve(RoleMatrixCompiler::class);
    expect($compiler)->toBeInstanceOf(DatabaseRoleMatrixCompiler::class);
    $compiler->compile($roleId);

    $azguardRole = AzGuardRoleModel::query()->where('name', 'sales')->firstOrFail();
    expect(Schema::hasColumn('aut_roles', 'azguard_role_id'))->toBeFalse()
        ->and(DB::table('aut_azguard_role_links')->where('role_id', $roleId)->value('azguard_role_id'))->toBe($azguardRole->id)
        ->and(RolePermission::query()->where('role_id', $azguardRole->id)->pluck('permission_key')->all())->toBe(['app.crm.deal.read'])
        ->and($seen)->toBe([[$roleId, 1, ['app.crm.deal.read'], $initial]])
        ->and($versions->current($roleId))->toBe($initial + 1)
        ->and($writes)->not->toBeEmpty();
    foreach ($writes as [$writeConnection, $level]) {
        expect($writeConnection)->toBe($connection)
            ->and($level)->toBeGreaterThanOrEqual(2);
    }

    DB::table('aut_role_matrix')->where('role_id', $roleId)->delete();
    $compiler->compile($roleId);
    expect(RolePermission::query()->where('role_id', $azguardRole->id)->count())->toBe(0)
        ->and(DB::table('aut_azguard_role_links')->where('role_id', $roleId)->value('azguard_role_id'))->toBe($azguardRole->id)
        ->and($versions->current($roleId))->toBe($initial + 2);
});

it('propagates a missing role during compilation without invalidating scope', function (): void {
    $roleId = (string) Str::uuid();
    $versions = resolve(ScopeMapVersions::class);
    $initial = $versions->current($roleId);
    expect(fn () => resolve(RoleMatrixCompiler::class)->compile($roleId))->toThrow(ModelNotFoundException::class);
    expect($versions->current($roleId))->toBe($initial)
        ->and(DB::table('aut_azguard_role_links')->count())->toBe(0)
        ->and(AzGuardRoleModel::query()->count())->toBe(0);
});

it('rolls back native and bridge writes when the registered bridge listener fails', function (bool $existingMirror): void {
    $roleId = bridgeRole();
    $compiler = resolve(RoleMatrixCompiler::class);
    $versions = resolve(ScopeMapVersions::class);

    if ($existingMirror) {
        $compiler->compile($roleId);
    }
    $initial = $versions->current($roleId);
    $links = DB::table('aut_azguard_role_links')->get()->all();
    $roles = DB::table('az_guard_roles')->get()->all();
    $permissions = DB::table('az_guard_role_permissions')->get()->all();
    $matrix = DB::table('aut_role_matrix')->get()->all();
    $seen = [];

    app()->instance(EntityActivity::class, new class($roleId, $seen) implements EntityActivity
    {
        public function __construct(private readonly string $roleId, private array &$seen) {}

        public function isActive(string $entityKey): bool
        {
            $this->seen[] = [DB::transactionLevel(), DB::table('aut_azguard_role_links')->where('role_id', $this->roleId)->exists()];
            DB::table('aut_roles')->where('id', $this->roleId)->update(['name' => 'Uncommitted']);
            DB::table('aut_role_matrix')->where('role_id', $this->roleId)->update(['scope' => 'own']);

            throw new RuntimeException('bridge listener failure');
        }
    });

    expect(fn () => $compiler->compile($roleId))->toThrow(RuntimeException::class, 'bridge listener failure');
    expect($seen)->toBe([[2, true]])
        ->and(DB::transactionLevel())->toBe(0)
        ->and(DB::table('aut_roles')->where('id', $roleId)->value('name'))->toBe('Sales')
        ->and(DB::table('aut_role_matrix')->get()->all())->toEqual($matrix)
        ->and(DB::table('aut_azguard_role_links')->get()->all())->toEqual($links)
        ->and(DB::table('az_guard_roles')->get()->all())->toEqual($roles)
        ->and(DB::table('az_guard_role_permissions')->get()->all())->toEqual($permissions)
        ->and($versions->current($roleId))->toBe($initial);
})->with(['new mirror' => false, 'existing mirror' => true]);

it('defers scope invalidation to outer commit and drops it after rollback', function (): void {
    $roleId = bridgeRole();
    $compiler = resolve(RoleMatrixCompiler::class);
    $versions = resolve(ScopeMapVersions::class);
    $initial = $versions->current($roleId);

    DB::beginTransaction();
    DB::table('aut_roles')->where('id', $roleId)->update(['name' => 'Uncommitted']);
    $compiler->compile($roleId);
    expect(DB::table('aut_azguard_role_links')->where('role_id', $roleId)->exists())->toBeTrue()
        ->and(RolePermission::query()->pluck('permission_key')->all())->toBe(['app.crm.deal.read'])
        ->and($versions->current($roleId))->toBe($initial);
    DB::rollBack();
    expect(DB::table('aut_azguard_role_links')->where('role_id', $roleId)->exists())->toBeFalse()
        ->and(AzGuardRoleModel::query()->where('name', 'sales')->exists())->toBeFalse()
        ->and(RolePermission::query()->count())->toBe(0)
        ->and(DB::table('aut_roles')->where('id', $roleId)->value('name'))->toBe('Sales')
        ->and($versions->current($roleId))->toBe($initial);

    DB::beginTransaction();
    $compiler->compile($roleId);
    expect($versions->current($roleId))->toBe($initial);
    $this->tenantContext->accountId = 'other-tenant';
    DB::commit();
    expect($versions->current($roleId))->toBe(0);
    $this->tenantContext->accountId = 'bridge-tenant';
    expect($versions->current($roleId))->toBe($initial + 1)
        ->and(RolePermission::query()->pluck('permission_key')->all())->toBe(['app.crm.deal.read']);
});

it('reads compiled permissions through assignments and role links for each principal type', function (PrincipalType $type): void {
    $roleId = bridgeRole();
    $principal = new PrincipalIdentity($type, (string) Str::uuid());
    DB::table('aut_role_assignments')->insert([
        'id' => (string) Str::uuid(), 'role_id' => $roleId,
        'principal_type' => $type->value, 'principal_id' => $principal->principalId(),
    ]);
    $compiler = resolve(RoleMatrixCompiler::class);
    $source = resolve(AutMatrixGrantSource::class);
    expect($source->permissionsFor($principal, 'app')->keys())->toBe([]);

    $compiler->compile($roleId);
    expect($source->permissionsFor($principal, 'app')->keys())->toBe(['app.crm.deal.read'])
        ->and($source->permissionsFor($principal, 'admin')->keys())->toBe([]);

    DB::table('aut_role_matrix')->where('role_id', $roleId)->where('action', 'read')->where('entity_key', 'crm.deal')->update(['scope' => 'none']);
    $compiler->compile($roleId);
    expect($source->permissionsFor($principal, 'app')->keys())->toBe([]);
})->with(PrincipalType::cases());

it('resolves compiled AzGuard permissions without a workspace', function (string $contextType): void {
    if ($contextType === 'boxed') {
        app()->instance(TenancyManager::class, new NullTenancyManager(new SingleAccountResolver));
    } elseif ($contextType === 'root') {
        $manager = Mockery::mock(TenancyManager::class);
        $manager->shouldReceive('context')->andReturnNull();
        app()->instance(TenancyManager::class, $manager);
    }

    $roleId = bridgeRole();
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid());
    DB::table('aut_role_assignments')->insert([
        'id' => (string) Str::uuid(), 'role_id' => $roleId,
        'principal_type' => 'user', 'principal_id' => $principal->principalId(),
    ]);
    resolve(RoleMatrixCompiler::class)->compile($roleId);

    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    }, E_WARNING | E_NOTICE);

    try {
        expect(resolve(PermissionLayer::class)->cacheDiscriminator('app'))->toBe('')
            ->and(resolve(PermissionResolverInterface::class)->forUser($principal, 'app')->keys())->toBe(['app.crm.deal.read'])
            ->and(resolve(PermissionResolverInterface::class)->forUser($principal, 'admin')->keys())->toBe([]);
    } finally {
        restore_error_handler();
    }
})->with(['boxed', 'account', 'root']);

it('keeps AzGuard permission cache entries separate while switching and clearing workspaces', function (): void {
    $workspaceA = new WorkspaceRef((string) Str::uuid(), 'a', 'team', '/a', null);
    $workspaceB = new WorkspaceRef((string) Str::uuid(), 'b', 'team', '/b', null);
    $accountContext = $this->tenantContext->current();
    $context = $accountContext->forWorkspace($workspaceA);
    $manager = Mockery::mock(TenancyManager::class);
    $manager->shouldReceive('context')->andReturnUsing(static function () use (&$context): TenantContext {
        return $context;
    });
    $manager->shouldReceive('setWorkspace')->andReturnUsing(static function (?WorkspaceRef $workspace) use (&$context, $accountContext): void {
        $context = $workspace === null ? $accountContext : $accountContext->forWorkspace($workspace);
    });
    app()->instance(TenancyManager::class, $manager);

    $roleId = bridgeRole();
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid());
    DB::table('aut_role_assignments')->insert([
        'id' => (string) Str::uuid(), 'role_id' => $roleId,
        'principal_type' => 'user', 'principal_id' => $principal->principalId(),
        'workspace_id' => $workspaceA->id,
    ]);
    resolve(RoleMatrixCompiler::class)->compile($roleId);
    $resolver = resolve(PermissionResolverInterface::class);
    $layer = resolve(PermissionLayer::class);

    expect($layer->cacheDiscriminator('app'))->toBe($workspaceA->id)
        ->and($resolver->forUser($principal, 'app')->keys())->toBe(['app.crm.deal.read']);

    $manager->setWorkspace($workspaceB);
    expect($layer->cacheDiscriminator('app'))->toBe($workspaceB->id)
        ->and($resolver->forUser($principal, 'app')->keys())->toBe([]);

    $manager->setWorkspace(null);
    expect($layer->cacheDiscriminator('app'))->toBe('')
        ->and($resolver->forUser($principal, 'app')->keys())->toBe([]);

    $manager->setWorkspace($workspaceA);
    expect($resolver->forUser($principal, 'app')->keys())->toBe(['app.crm.deal.read']);
});

it('excludes ineligible assignments and roles from the compiled grant chain', function (string $exclusion): void {
    $roleId = bridgeRole();
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid());
    $assignmentId = (string) Str::uuid();
    DB::table('aut_role_assignments')->insert([
        'id' => $assignmentId, 'role_id' => $roleId,
        'principal_type' => 'user', 'principal_id' => $principal->principalId(),
    ]);
    resolve(RoleMatrixCompiler::class)->compile($roleId);
    $source = resolve(AutMatrixGrantSource::class);
    expect($source->permissionsFor($principal, 'app')->keys())->toBe(['app.crm.deal.read']);

    match ($exclusion) {
        'expired' => DB::table('aut_role_assignments')->where('id', $assignmentId)->update(['expires_at' => '2000-01-01 00:00:00']),
        'foreign workspace' => DB::table('aut_role_assignments')->where('id', $assignmentId)->update(['workspace_id' => (string) Str::uuid()]),
        'different principal' => DB::table('aut_role_assignments')->where('id', $assignmentId)->update(['principal_id' => (string) Str::uuid()]),
        'deleted role' => DB::table('aut_roles')->where('id', $roleId)->update(['deleted_at' => '2000-01-01 00:00:00']),
        'missing link' => DB::table('aut_azguard_role_links')->where('role_id', $roleId)->delete(),
    };

    expect($source->permissionsFor($principal, 'app')->keys())->toBe([]);
})->with(['expired', 'foreign workspace', 'different principal', 'deleted role', 'missing link']);

it('builds only active and scoped catalog definitions for the configured panel', function (): void {
    bridgeRole();
    $builder = resolve(MatrixPermissionCatalogBuilder::class);

    expect($builder->supports('app'))->toBeTrue()
        ->and($builder->supports('admin'))->toBeFalse()
        ->and($builder->build('admin'))->toBe([]);
    $definitions = $builder->build('app');
    expect(array_map(static fn (PermissionDefinition $definition): string => $definition->key(), $definitions))
        ->toBe(['app.crm.deal.read'])
        ->and($definitions[0]->panelId())->toBe('app');
});

it('accepts ordinary string and integer auth identifiers without weakening the grant priority', function (string|int $identifier): void {
    $roleId = bridgeRole();
    DB::table('aut_role_assignments')->insert([
        'id' => (string) Str::uuid(), 'role_id' => $roleId,
        'principal_type' => 'user', 'principal_id' => (string) $identifier,
    ]);
    resolve(RoleMatrixCompiler::class)->compile($roleId);
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn($identifier);
    $source = resolve(AutMatrixGrantSource::class);

    expect($source->permissionsFor($user, 'app')->keys())->toBe(['app.crm.deal.read'])
        ->and($source->priority())->toBe(85);
})->with(['string' => 'ordinary-user', 'integer' => 42]);

it('rejects malformed ordinary auth identifiers before issuing a grant query', function (mixed $identifier): void {
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn($identifier);

    expect(fn () => resolve(AutMatrixGrantSource::class)->permissionsFor($user, 'app'))
        ->toThrow(InvalidArgumentException::class, 'must be a string or integer');
})->with(['null' => null, 'boolean' => false, 'array' => [[]]]);

it('grants wildcard only for an active assignment of the system owner role', function (): void {
    $roleId = bridgeRole();
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid());
    DB::table('aut_roles')->where('id', $roleId)->update(['slug' => 'owner', 'is_system' => false]);
    $assignment = (string) Str::uuid();
    DB::table('aut_role_assignments')->insert([
        'id' => $assignment, 'role_id' => $roleId,
        'principal_type' => 'user', 'principal_id' => $principal->principalId(),
    ]);
    $source = resolve(AutMatrixGrantSource::class);
    expect($source->permissionsFor($principal, 'app')->keys())->toBe([]);

    DB::table('aut_roles')->where('id', $roleId)->update(['is_system' => true]);
    expect($source->permissionsFor($principal, 'app')->keys())->toBe(['*']);

    DB::table('aut_role_assignments')->where('id', $assignment)->update(['expires_at' => '2000-01-01 00:00:00']);
    expect($source->permissionsFor($principal, 'app')->keys())->toBe([]);
});

it('rejects a malformed permission value at the native grant boundary', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where', 'whereNull', 'join')->andReturnSelf();
    $query->shouldReceive('exists')->andReturnFalse();
    $query->shouldReceive('pluck')->with('azgrp.permission_key')->andReturn(collect([42]));
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('table')->with('aut_role_assignments')->andReturn($query);
    $connections = Mockery::mock(ConnectionResolverInterface::class);
    $connections->shouldReceive('connection')->andReturn($connection);
    $source = new AutMatrixGrantSource(
        tenancyManager: resolve(TenancyManager::class), connections: $connections, clock: resolve(ClockInterface::class),
    );
    $principal = new PrincipalIdentity(PrincipalType::User, (string) Str::uuid());

    expect(fn () => $source->permissionsFor($principal, 'app'))->toThrow(UnexpectedValueException::class, 'must be a string');
});

it('refuses a non-Laravel connection before starting bridge writes', function (): void {
    $roleId = bridgeRole();
    $connections = Mockery::mock(ConnectionResolverInterface::class);
    $connections->shouldReceive('connection')->once()->andReturn(Mockery::mock(ConnectionInterface::class));
    $sync = new SyncAzGuardRolePermissions(
        options: resolve(AuthOptions::class), activeEntities: resolve(EntityActivity::class),
        links: resolve(RoleLinkStore::class), connections: $connections,
    );

    expect(fn () => $sync->handle(new RoleMatrixCompiled(roleId: $roleId)))
        ->toThrow(LogicException::class, 'requires a Laravel database connection');
    expect(DB::table('aut_azguard_role_links')->count())->toBe(0);
});
