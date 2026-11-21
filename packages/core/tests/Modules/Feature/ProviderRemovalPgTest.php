<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\DeploymentOwners;
use CoreX\Foundation\Contracts\ProviderRemovalGate;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\PlanDigest;
use CoreX\Foundation\Data\RemovalApproval;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

uses()->group('pg');

it('requires the current operator and every deployment owner before promoting a provider-only removal', function (): void {
    $prior = DB::getDefaultConnection();
    DB::setDefaultConnection('pgsql');

    try {
        $contexts = LocalWorkContexts::install($this->app);
        $contexts->initialize(new AccountRef('fleet', 'fleet', 'active', [], []));
        $scope = WorkScopeFixture::install($this->app, principalId: 'fleet-operator')->scope();
        $gate = resolve(ProviderRemovalGate::class);
        $provider = new ModuleRef('axiomasoft/corex-search-custom-fields');
        $installed = new PlanDigest(hash('sha256', 'installed'));
        $proposed = new PlanDigest(hash('sha256', 'proposed'));
        $granted = true;
        Gate::define('corex.lifecycle.remove-provider', static function (ScopePrincipal $principal, DataScope $current, ModuleRef $target, mixed $ref, PlanDigest $digest) use (&$granted, $scope, $proposed, $provider): bool {
            return $granted && $principal->id === $scope->principalId && get_object_vars($current) === get_object_vars($scope)
                && $target->package === $provider->package && $digest->value === $proposed->value;
        });
        expect(fn () => $gate->prepare($provider, $installed, $proposed))->toThrow(LifecycleBlocked::class);
        $host = Mockery::mock(DeploymentOwners::class);
        $this->app->instance(DeploymentOwners::class, $host);
        $host->shouldReceive('freeze')->twice()->with($provider, $installed, $proposed)->andReturn('membership-1', 'membership-2');
        $host->shouldReceive('owners')->once()->with('membership-1')->andReturn(['A', 'A']);
        expect(fn () => $gate->prepare($provider, $installed, $proposed))->toThrow(LifecycleBlocked::class);
        $contexts->setWorkspace(new WorkspaceRef('W1', 'one', 'workspace', '/', null));
        expect(fn () => $gate->prepare($provider, $installed, $proposed))->toThrow(AuthorizationException::class);
        $contexts->setWorkspace(null);
        $host->shouldReceive('owners')->once()->with('membership-2')->andReturn(['B', 'A']);
        $host->shouldReceive('certify')->once()->with('membership-2', 'A', $provider)->andReturn('receipt-A');
        $host->shouldReceive('certify')->once()->with('membership-2', 'B', $provider)->andReturn('receipt-B');
        $approval = new RemovalApproval($provider, $installed, $proposed, 'promotion-receipt');
        $host->shouldReceive('seal')->once()->with('membership-2', $provider, $installed, $proposed, ['A' => 'receipt-A', 'B' => 'receipt-B'])->andReturn($approval);
        expect($gate->prepare($provider, $installed, $proposed))->toBe($approval)
            ->and(fn () => $gate->consume($approval, $proposed))->toThrow(LifecycleBlocked::class);
        $granted = false;
        expect(fn () => $gate->consume($approval, $installed))->toThrow(AuthorizationException::class);
        $granted = true;
        $host->shouldReceive('promote')->once()->with($approval, $installed);
        $gate->consume($approval, $installed);
    } finally {
        DB::setDefaultConnection($prior);
    }
});
