<?php

declare(strict_types=1);

use AzGuard\Exceptions\Authorization\VisibilityNotSupportedException;
use CoreX\Access\Access\Internal\CoreXAccessContextMapper;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Data\RecordContext;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\Access\Tests\Authorization\Project;
use CoreX\Access\Tests\Authorization\ProjectPolicy;
use CoreX\Access\Tests\Authorization\ScopedProject;
use CoreX\Access\Tests\Authorization\User;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Testing\ArrayEntityRegistry;

it('uses one AzGuard policy for record checks and SQL visibility for a permission owned by the host panel', function (): void {
    $user = User::query()->create();
    $own = Project::query()->create(['owner_id' => $user->getKey()]);
    $other = Project::query()->create(['owner_id' => 999]);
    $checker = resolve(AccessChecker::class);
    expect($checker->allowed($user, 'test.project', 'view'))->toBeTrue()
        ->and($checker->canOnRecord($user, 'view', $own))->toBeTrue()
        ->and($checker->canOnRecord($user, 'view', $other))->toBeFalse();
    $query = Project::query();
    expect(resolve(ScopeResolver::class)->apply($query, $user, 'test.project', 'view'))->toBe($query)
        ->and($query->pluck('id')->all())->toBe([$own->getKey()]);
});

it('preserves caller constraints and rejects entity mismatches', function (): void {
    $user = User::query()->create();
    Project::query()->create(['owner_id' => $user->getKey()]);
    $query = Project::query()->where('id', 999);
    expect(resolve(ScopeResolver::class)->apply($query, $user, 'test.project', 'view')->count())->toBe(0)
        ->and(resolve(ScopeResolver::class)->apply(Project::query(), $user, 'wrong.entity', 'view')->count())->toBe(0);
});

it('denies missing trusted context instead of falling back to global access', function (): void {
    $tenancy = Mockery::mock(TenancyManager::class);
    $tenancy->shouldReceive('context')->andReturnNull();
    app()->instance(TenancyManager::class, $tenancy);
    $user = User::query()->create();
    Project::query()->create(['owner_id' => $user->getKey()]);
    expect(resolve(AccessChecker::class)->allowed($user, 'test.project', 'view'))->toBeFalse()
        ->and(resolve(ScopeResolver::class)->apply(Project::query(), $user, 'test.project', 'view')->count())->toBe(0);
});

it('maps account workspace principal and explicit record contexts without caching the account', function (): void {
    $account = new AccountRef('account-1', 'one', 'active', [], []);
    $workspace = new WorkspaceRef('workspace-1', 'one', 'team', '/one', null);
    $tenancy = Mockery::mock(TenancyManager::class);
    $tenancy->shouldReceive('context')->andReturn(new TenantContext($account, $workspace), new TenantContext($account, $workspace), null);
    $mapper = new CoreXAccessContextMapper($tenancy);
    $scope = $mapper->scope();
    expect($scope->tenant->key())->toBe('account:account-1')
        ->and($scope->context->key())->toBe('workspace:workspace-1')
        ->and($mapper->scope(new RecordContext('stage', 'stage-2'))->context->key())->toBe('stage:stage-2')
        ->and($mapper->scope())->toBeNull();
    $user = new User(['id' => 42]);
    expect($mapper->subject($user)->key())->toBe('user:42');
});

it('retains tenant and workspace boundaries even when the policy allows every record', function (): void {
    $user = User::query()->create();
    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        'test.project', ScopedProject::class, 'projects', 'test', 'Project', 'Projects', [], [], null,
    ));
    app()->instance(EntityRegistry::class, $registry);
    ProjectPolicy::$allowEverything = true;

    try {
        $checker = resolve(AccessChecker::class);
        expect($checker->canOnRecord($user, 'view', new ScopedProject))->toBeTrue()
            ->and($checker->canOnRecord($user, 'view', new ScopedProject(['account_id' => 'foreign'])))->toBeFalse()
            ->and($checker->canOnRecord($user, 'view', new ScopedProject(['workspace_id' => 'foreign'])))->toBeFalse();
        // A model without an exact tenant query mapping must never get an unfiltered list.
        expect(fn () => resolve(ScopeResolver::class)->apply(ScopedProject::query(), $user, 'test.project', 'view'))
            ->toThrow(VisibilityNotSupportedException::class);
    } finally {
        ProjectPolicy::$allowEverything = false;
    }
});
