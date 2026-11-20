<?php

declare(strict_types=1);

namespace App\Scenario;

use Closure;
use CoreX\Context\Contracts\ProviderRecoveryAuthority;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;

final class FixtureProviderRecoveryAuthority implements ProviderRecoveryAuthority
{
    private ?string $operator = null;

    public function __construct(private readonly TenantContextResolver $contexts, private readonly WorkPrincipalAuthority $principals) {}

    public function asOperator(Closure $work): mixed
    {
        $actor = $this->principals->current($this->contexts->current());

        if ($actor->kind !== 'user' || $actor->id !== Scenario::USER_B) {
            throw new ScopeContextMismatch('This host principal has no operator credential.');
        }
        $previous = $this->operator;
        $this->operator = $actor->id;

        try {
            return $work();
        } finally {
            $this->operator = $previous;
        }
    }

    public function authorize(ProviderEffectRef $effect): void
    {
        $context = $this->contexts->current();
        $actor = $this->principals->current($context);
        $control = Scenario::connection('control');
        $route = $control->table('corex_scope_routes')->where('deployment_id', Scenario::DEPLOYMENT)
            ->where('account_id', $context->id())->first();

        if ($route === null || $effect->accountId !== $context->id() || $effect->workspaceId !== $context->workspace?->id
            || $effect->accountIncarnation !== $route->account_incarnation
            || ! in_array($effect->owner, ['notifications.delivery', 'webhooks.delivery', 'corex.workflow'], true)) {
            throw new ScopeContextMismatch('The provider effect is outside the authenticated tenant scope.');
        }

        if ($route->mode === 'open' && $actor->kind === 'user' && $actor->id === Scenario::USER_A) {
            return;
        }

        if ($this->operator !== null && $control->table('scenario_provider_operator_grants')->where([
            'operator_id' => $this->operator,
            'account_id' => $effect->accountId,
            'workspace_id' => $effect->workspaceId,
            'owner' => $effect->owner,
            'provider_namespace' => $effect->providerNamespace,
            'effect_key' => $effect->effectKey,
        ])->exists()) {
            return;
        }

        throw new ScopeContextMismatch('No current credential for the requested provider recovery.');
    }
}
