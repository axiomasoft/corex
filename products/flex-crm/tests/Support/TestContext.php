<?php

declare(strict_types=1);

namespace Flex\Crm\Tests\Support;

use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Tests\Support\WorkScopeFixture;
use Flex\Crm\Context\Contracts\CrmExecutionContext;
use Flex\Crm\Context\Data\Actor;
use Flex\Crm\Context\Data\ExecutionContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

final class TestContext implements AccountConnectionResolver, CrmExecutionContext, PhysicalBindingVerifier
{
    public ?ExecutionContext $context = null {
        set {
            if ($value !== null) {
                DB::setDefaultConnection($value->connectionName);
            }
            $this->context = $value;
        }
    }

    private function __construct(
        private readonly Connection $owner,
        private readonly WorkScopeFixture $authority,
        private readonly string $membershipId,
    ) {}

    public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
    {
        $this->authority->verifyTarget($binding, $context, $this->owner);

        if ($connection === $this->owner) {
            return;
        }

        // The competing PG session must see the original committed, randomly seeded owner marker.
        if (! $this->owner instanceof PostgresConnection || ! $connection instanceof PostgresConnection
            || $connection->scalar('select current_database()') !== $this->owner->getDatabaseName()
            || ! $connection->table('wsp_workspace_members')->where('id', $this->membershipId)->exists()) {
            throw new ScopeContextMismatch('The database session cannot verify this CRM fixture owner.');
        }
    }

    public function current(): ExecutionContext
    {
        return $this->context ?? throw new AuthorizationException('No authenticated CRM context.');
    }

    public function resolve(AccountRef $account): DatabaseConnectionConfig
    {
        $config = $this->owner->getConfig();

        return new DatabaseConnectionConfig(driver: $config['driver'], host: $config['host'] ?? '', port: (int) ($config['port'] ?? 0), database: $config['database'], username: $config['username'] ?? '');
    }

    public static function seed(string $connectionName): self
    {
        $db = DB::connection($connectionName);
        $user = (string) Str::uuid7();
        $workspace = (string) Str::uuid7();
        $db->table('users')->insert(['id' => $user, 'name' => 'Trusted user']);
        $db->table('wsp_workspaces')->insert(['id' => $workspace, 'slug' => 'main']);
        $membership = (string) Str::uuid7();
        $db->table('wsp_workspace_members')->insert(['id' => $membership, 'workspace_id' => $workspace, 'user_id' => $user, 'status' => 'active']);
        $authority = WorkScopeFixture::install(app(), principalId: $user);
        $self = new self($db, $authority, $membership);
        $self->context = new ExecutionContext(
            tenant: new TenantContext(new AccountRef(id: (string) Str::uuid7(), slug: 'account-a', status: 'active', features: [], limits: []), new WorkspaceRef(id: (string) $workspace, slug: 'main', type: 'main', path: '', parentId: null)),
            connectionName: $connectionName, actor: new Actor(source: 'user', id: (string) $user, name: 'Trusted user'), principalId: $user,
        );
        $self->bind();
        $authority->register($self->current()->tenant, $db);
        app()->instance(PhysicalBindingVerifier::class, $self);

        return $self;
    }

    public function bind(): void
    {
        app()->instance(CrmExecutionContext::class, $this);
        $self = $this;
        app()->instance(TenantContextResolver::class, new readonly class($self) implements TenantContextResolver
        {
            public function __construct(private TestContext $fixture) {}

            public function current(): TenantContext
            {
                return $this->fixture->current()->tenant;
            }
        });
        app()->instance(AccountConnectionResolver::class, $this);
        $modules = Mockery::mock(ModuleRegistry::class);
        $modules->shouldReceive('isActive')->with('flex/crm', Mockery::type(TenantContext::class))->andReturn(true);
        app()->instance(ModuleRegistry::class, $modules);
    }
}
