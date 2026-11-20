<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\ScopeAuthority;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper;
use Illuminate\Database\ConnectionInterface;
use Tenant\Contracts\Context\ContextResolver;
use Tenant\Contracts\Verification\BindingVerifier;
use Tenant\Data\Context\ContextState;
use Tenant\Data\Context\ExtensionRef;
use Tenant\Data\Context\FacetBindings;
use Tenant\Data\Context\IsolationContext;
use Tenant\Data\Context\IsolationIdentity;
use Tenant\Data\Context\ProfileRef;
use Tenant\Data\Context\Purpose;
use Tenant\Data\Context\ResolvedIsolation;
use Tenant\Data\Context\Selection;
use Tenant\Data\Verification\BindingExpectation;
use Tenant\Data\Verification\BindingVerificationInput;
use Tenant\Data\Verification\VerifiedBinding;
use Tenant\Exceptions\BindingMismatch;
use Tenant\Laravel\Data\ResourceDescriptor;

final class CloudTopology implements AccountIsolationMapper, BindingVerifier, ContextResolver
{
    private array $accounts = [];

    private array $workspaces = [];

    public function __construct(private readonly ScopeAuthority $authority) {}

    public function selection(AccountRef $account, ?WorkspaceRef $workspace = null): Selection
    {
        $binding = $this->authority->resolve($account);
        $this->accounts[$account->id] = $account;

        if ($workspace !== null) {
            $this->workspaces[$account->id.'|'.$workspace->id] = $workspace;
        }

        return Selection::tenant(
            new IsolationIdentity('corex-account', $account->id),
            new ProfileRef('laravel-pg-database', 1),
            $workspace === null ? [] : [new ExtensionRef('corex:workspace', 1, false, ['id' => $workspace->id])],
            $this->expectation($binding),
        );
    }

    public function context(ContextState $state): ?TenantContext
    {
        if ($state->kind !== 'active') {
            return null;
        }
        $accountId = $state->context->identity->id;
        $workspaceId = $state->context->extensions[0]->refs['id'] ?? null;

        return new TenantContext(
            $this->accounts[$accountId] ?? throw new ScopeContextMismatch('Missing host account projection.'),
            $workspaceId === null ? null : ($this->workspaces[$accountId.'|'.$workspaceId]
                ?? throw new ScopeContextMismatch('Missing host workspace projection.')),
        );
    }

    public function resolve(Selection $selection, Purpose $purpose): ResolvedIsolation
    {
        if ($selection->mode === 'central') {
            return new ResolvedIsolation(ContextState::central(), new FacetBindings([]));
        }
        $account = $this->accounts[$selection->identity?->id] ?? throw new ScopeContextMismatch('Missing host account selection.');
        $binding = $this->authority->resolve($account);
        $role = $this->location($binding);
        $descriptor = new ResourceDescriptor(
            route: config('database.connections.scenario_'.$role),
            prefix: 'scenario:'.$account->id.':',
            durableRoot: storage_path('app/scenario/'.$role.'/durable'),
            ephemeralRoot: storage_path('app/scenario/'.$role.'/ephemeral'),
            sessionRoot: storage_path('app/scenario/'.$role.'/sessions'),
        );

        return new ResolvedIsolation(
            ContextState::active(new IsolationContext('tenant', $selection->identity, $selection->expectedProfile, $this->expectation($binding), $selection->extensions)),
            new FacetBindings(['tenant:database' => $descriptor, 'tenant:binding-verification' => $descriptor, 'tenant:resource' => $descriptor]),
        );
    }

    public function verify(BindingVerificationInput $input): VerifiedBinding
    {
        if (! $input->access instanceof ResourceDescriptor) {
            throw new BindingMismatch;
        }
        $location = Scenario::connection('control')->table('scenario_binding_locations')
            ->where('binding_id', $input->expected->resourceRef)->first();
        $account = $this->accounts[$location->account_id ?? ''] ?? throw new BindingMismatch;
        $binding = $this->authority->resolve($account);
        $role = $this->location($binding);

        if ($input->expected != $this->expectation($binding) || $input->access->route !== config('database.connections.scenario_'.$role)) {
            throw new BindingMismatch;
        }
        PhysicalMarker::assert($binding, Scenario::connection($role), $input->access->route['database']);

        return new VerifiedBinding($input->expected, 'scenario:postgres-owner', $binding->restoreEpoch);
    }

    public function validate(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
    {
        PhysicalMarker::assert($binding, $connection, config('database.connections.scenario_'.$this->location($binding).'.database'));
    }

    public function location(PhysicalBinding $binding): string
    {
        $role = Scenario::connection('control')->table('scenario_binding_locations')->where('binding_id', $binding->bindingId)->value('role');

        return in_array($role, ['a', 'b'], true) ? $role : throw new ScopeContextMismatch('The control owner has no verified route.');
    }

    private function expectation(PhysicalBinding $binding): BindingExpectation
    {
        return new BindingExpectation($binding->bindingId, $binding->accountIncarnation, 'generation:'.$binding->bindingGeneration);
    }
}
