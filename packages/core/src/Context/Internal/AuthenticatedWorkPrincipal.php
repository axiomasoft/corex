<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

use Closure;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use Illuminate\Contracts\Auth\Factory;
use Packman\Attributes\Internal;

#[Internal]
final readonly class AuthenticatedWorkPrincipal implements WorkPrincipalAuthority
{
    public function __construct(private Factory $auth) {}

    public function current(TenantContext $context): ScopePrincipal
    {
        $user = $this->auth->guard()->user();

        if (! $user instanceof Principal) {
            throw new ScopeContextMismatch('An authenticated work principal is required.');
        }

        return new ScopePrincipal($user->principalType()->value, $user->principalId());
    }

    public function run(DataScope $scope, TenantContext $context, bool $durable, Closure $work): mixed
    {
        if ($durable) {
            throw new ScopeContextMismatch('The host must register authority to resume this principal from durable work.');
        }
        $principal = $this->current($context);

        if ($principal->kind !== $scope->principalKind || $principal->id !== $scope->principalId) {
            throw new ScopeContextMismatch('The work principal does not match the authenticated principal.');
        }

        return $work();
    }
}
