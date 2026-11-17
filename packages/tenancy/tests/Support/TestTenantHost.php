<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper;
use Illuminate\Database\DatabaseManager;
use RuntimeException;
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
use Tenant\Laravel\Data\ResourceDescriptor;

/** Test host authority; Tenant's real Laravel facilities own all mutations/restoration. */
final class TestTenantHost implements AccountIsolationMapper, BindingVerifier, ContextResolver
{
    /** @var array<string, AccountRef> */
    private array $accounts = [];

    /** @var array<string, WorkspaceRef> */
    private array $workspaces = [];

    public function __construct(private readonly DatabaseManager $database, private readonly string $root) {}

    public function selection(AccountRef $account, ?WorkspaceRef $workspace = null): Selection
    {
        $this->route($account->id);
        $this->accounts[$account->id] = $account;

        if ($workspace !== null) {
            $this->workspaces[$account->id.':'.$workspace->id] = $workspace;
        }

        return Selection::tenant(
            new IsolationIdentity('corex-account', $account->id),
            new ProfileRef('laravel-pg-database', 1),
            $workspace === null ? [] : [new ExtensionRef('corex:workspace', 1, false, ['id' => $workspace->id])],
            $this->binding($account->id),
        );
    }

    public function context(ContextState $state): ?TenantContext
    {
        if ($state->kind !== 'active') {
            return null;
        }
        $id = $state->context->identity->id;
        $workspace = $state->context->extensions[0]->refs['id'] ?? null;

        return new TenantContext(
            $this->accounts[$id] ?? throw new RuntimeException('Account was not admitted by the test host.'),
            $workspace === null ? null : ($this->workspaces[$id.':'.$workspace] ?? throw new RuntimeException('Workspace was not admitted by the test host.')),
        );
    }

    public function resolve(Selection $selection, Purpose $purpose): ResolvedIsolation
    {
        if ($selection->mode === 'central') {
            return new ResolvedIsolation(ContextState::central(), new FacetBindings([]));
        }
        $id = $selection->identity?->id ?? throw new RuntimeException('Account identity is required.');
        $descriptor = new ResourceDescriptor(
            route: $this->route($id), prefix: 'test:'.$id.':',
            durableRoot: $this->root.'/'.$id.'/durable', ephemeralRoot: $this->root.'/'.$id.'/ephemeral', sessionRoot: $this->sessionRoot($id),
        );

        return new ResolvedIsolation(
            ContextState::active(new IsolationContext('tenant', $selection->identity, $selection->expectedProfile, $this->binding($id), $selection->extensions)),
            new FacetBindings(['tenant:database' => $descriptor, 'tenant:binding-verification' => $descriptor, 'tenant:resource' => $descriptor]),
        );
    }

    public function verify(BindingVerificationInput $input): VerifiedBinding
    {
        $id = substr($input->expected->resourceRef, strlen('test-account:'));

        if (! $input->access instanceof ResourceDescriptor || $input->access->route !== $this->route($id)) {
            throw new RuntimeException('Test resource route does not match its authoritative account binding.');
        }

        return new VerifiedBinding($this->binding($id), 'test:corex-host', '1');
    }

    public function sessionRoot(string $accountId): string
    {
        return $this->root.'/'.$accountId.'/sessions';
    }

    private function binding(string $id): BindingExpectation
    {
        return new BindingExpectation('test-account:'.$id, '1', '1');
    }

    /** @return array<string, mixed> */
    private function route(string $id): array
    {
        $row = $this->database->connection('pgsql')->table('root_accounts')->where('id', $id)->first();

        if ($row === null || ! is_string($row->db_name) || $row->db_name === '') {
            throw new RuntimeException('Untrusted account route.');
        }
        $base = $this->database->connection('pgsql')->getConfig();

        return [
            'driver' => 'pgsql', 'host' => $base['host'], 'port' => $base['port'], 'database' => $row->db_name,
            'username' => $base['username'], 'password' => $base['password'], 'prefix' => '', 'schema' => 'public',
        ];
    }
}
