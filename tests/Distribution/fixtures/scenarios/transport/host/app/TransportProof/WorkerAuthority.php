<?php

declare(strict_types=1);

namespace App\TransportProof;

use App\Scenario\ScenarioPrincipalAuthority;
use Closure;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use Illuminate\Support\Facades\DB;

final class WorkerAuthority implements WorkPrincipalAuthority
{
    public const string SCHEMA = 'installed-transport-schema';

    public const string SEARCH = 'installed-transport-search';

    private ?string $service = null;

    public function __construct(private readonly ScenarioPrincipalAuthority $users) {}

    public function current(TenantContext $context): ScopePrincipal
    {
        if ($this->service === null) {
            return $this->users->current($context);
        }
        $this->assertGrant($context, $this->service);

        return new ScopePrincipal('service', $this->service);
    }

    public function run(DataScope $scope, TenantContext $context, bool $durable, Closure $work): mixed
    {
        if ($scope->principalKind === 'service') {
            if ((! $durable && $this->service !== $scope->principalId) || ! in_array($scope->principalId, [self::SCHEMA, self::SEARCH], true)) {
                throw new ScopeContextMismatch('This worker service has no current grant.');
            }

            return $this->runAs($context, $scope->principalId, $work);
        }

        $previous = $this->service;
        $this->service = null;

        try {
            return $this->users->run($scope, $context, $durable, $work);
        } finally {
            $this->service = $previous;
        }
    }

    public function runAs(TenantContext $context, string $service, Closure $work): mixed
    {
        $this->assertGrant($context, $service);
        $previous = $this->service;
        $this->service = $service;

        try {
            return $work();
        } finally {
            $this->service = $previous;
        }
    }

    private function assertGrant(TenantContext $context, string $service): void
    {
        if (! DB::table('installed_metadata_workers')->where([
            'account_id' => $context->id(), 'workspace_id' => $context->workspace?->id, 'service' => $service,
        ])->exists()) {
            throw new ScopeContextMismatch('The worker service is not installed in this scope.');
        }
    }
}
