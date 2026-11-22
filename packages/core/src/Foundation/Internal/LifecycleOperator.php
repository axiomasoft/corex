<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\PlanDigest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Packman\Attributes\Internal;

#[Internal]
final readonly class LifecycleOperator
{
    public function __construct(private DataScopeFactory $scopes, private CurrentWorkScope $current, private TenantContextResolver $contexts, private Gate $gate) {}

    public function current(): DataScope
    {
        return $this->scopes->forCurrentPrincipal($this->contexts->current());
    }

    public function authorize(DataScope $scope, ModuleRef $target, string $ability, ?LifecyclePlanRef $ref = null, ?PlanDigest $digest = null): void
    {
        $this->current->assert($scope);
        $name = 'corex.lifecycle.'.$ability;

        if (($scope->workspaceId !== null && ! in_array($ability, ['enable', 'inspect'], true)) || ! $this->gate->has($name)) {
            throw new AuthorizationException('Current account lifecycle grant required.');
        }
        $this->gate->forUser(new ScopePrincipal(kind: $scope->principalKind, id: $scope->principalId))->authorize($name, [$scope, $target, $ref, $digest]);
    }
}
