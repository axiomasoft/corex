<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use Closure;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\WorkAuthentication;
use CoreX\Data\Data\DataScope;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Identity\Internal\Models\User;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Workspace\Internal\Models\WorkspaceMember;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\RequestGuard;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Packman\Attributes\Internal;

#[Internal]
final readonly class MembershipWorkPrincipal implements WorkPrincipalAuthority
{
    public function __construct(private AuthManager $auth, private WorkAuthentication $frame, private AccountRead $accounts, private TenancyOptions $options) {}

    public function current(TenantContext $context): ScopePrincipal
    {
        $principal = $this->auth->guard($this->options->membershipGuard)->user();

        if (! $principal instanceof Authenticatable || ($principal instanceof Principal && $principal->principalType()->value !== 'user')) {
            throw new ScopeContextMismatch('A verified account user or an explicit host service grant is required.');
        }
        $id = $principal->getAuthIdentifier();

        if (! is_string($id) || $id === '') {
            throw new ScopeContextMismatch('The authenticated principal has no stable identity.');
        }
        $this->check($context, $id);

        return new ScopePrincipal('user', $id);
    }

    public function run(DataScope $scope, TenantContext $context, bool $durable, Closure $work): mixed
    {
        if ($scope->principalKind !== 'user') {
            throw new ScopeContextMismatch('Named service and non-user grants require a host authority.');
        }

        if (! $durable) {
            if ($this->current($context)->id !== $scope->principalId) {
                throw new ScopeContextMismatch('Work principal is not authenticated.');
            }
        }
        $this->check($context, $scope->principalId);
        $guard = $this->auth->guard($this->options->membershipGuard);
        $provider = $guard instanceof SessionGuard || $guard instanceof RequestGuard ? $guard->getProvider() : null;
        $user = $provider?->retrieveById($scope->principalId);

        if (! $user instanceof Authenticatable || $user->getAuthIdentifier() !== $scope->principalId) {
            throw new ScopeContextMismatch('The current membership identity cannot be restored.');
        }

        return $this->frame->run($user, $work, $provider);
    }

    private function check(TenantContext $context, string $id): void
    {
        if (! User::query()->whereKey($id)->where('is_active', true)->toBase()->exists()) {
            throw new ScopeContextMismatch('The user is no longer active.');
        }
        $granted = $context->workspace === null
            ? $this->accounts->ownerIdentityId($context->account->id, $this->options->centralConnection) === $id
            : WorkspaceMember::query()->where('workspace_id', $context->workspace->id)->where('user_id', $id)->where('status', 'active')->toBase()->exists();

        if (! $granted) {
            throw new ScopeContextMismatch('No active grant for this logical scope.');
        }
    }
}
