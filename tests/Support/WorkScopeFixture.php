<?php

declare(strict_types=1);

namespace CoreX\Tests\Support;

use Closure;
use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
use CoreX\Context\Laravel\ScopeOptions;
use CoreX\Data\Data\DataScope;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;

/** Package test fixture. The control connection is independent of the suite's default database. */
final class WorkScopeFixture implements ControlStoreIsolation, WorkPrincipalAuthority
{
    public ScopePrincipal $principal;

    /** @var array<string, ConnectionInterface> */
    private array $owners = [];

    public function __construct(private readonly Container $app, private readonly ConnectionInterface $control, string $kind, string $id)
    {
        $this->principal = new ScopePrincipal($kind, $id);
    }

    public static function install(Container $app, string $principalId = 'actor-1', string $principalKind = 'user'): self
    {
        if ($app->bound(self::class)) {
            $host = $app->make(self::class);
            $host->principal = new ScopePrincipal($principalKind, $principalId);

            return $host;
        }

        $key = $app->make('config')->get('app.key');

        if ($key === null || $key === '') {
            $app->make('config')->set('app.key', 'base64:'.base64_encode(str_repeat('f', 32)));
        }
        $app->make('config')->set('database.connections.work-scope-control', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app->make('config')->set('corex.scope.deployment_id', 'test-installation');
        $app->make('config')->set('corex.scope.control_connection', 'work-scope-control');
        $app->forgetInstance(ScopeOptions::class);
        $control = $app->make(DatabaseManager::class)->connection('work-scope-control');
        ScopeControlSchema::install($control);
        $host = new self($app, $control, $principalKind, $principalId);
        $app->instance(self::class, $host);
        $app->instance(ControlStoreIsolation::class, $host);
        $app->scoped(WorkPrincipalAuthority::class, static fn (): self => $host);
        $app->scoped(PhysicalBindingVerifier::class, static fn (): PhysicalBindingVerifier => new readonly class($host) implements PhysicalBindingVerifier
        {
            public function __construct(private WorkScopeFixture $host) {}

            public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
            {
                $this->host->verifyTarget($binding, $context, $connection);
            }
        });

        return $host;
    }

    public function register(TenantContext $context, ?ConnectionInterface $connection = null, ?string $epoch = null): PhysicalBinding
    {
        $connection ??= $this->app->make(DatabaseManager::class)->connection();
        $control = $this->app->make(DatabaseScopeControl::class);
        $id = $context->account->id;

        if (isset($this->owners[$id])) {
            return $control->resolve($context->account);
        }
        $this->owners[$id] = $connection;
        $binding = new PhysicalBinding('test-installation', 'resource:'.hash('sha256', $id), 1, 'incarnation:'.hash('sha256', $id), $epoch ?? hash('sha256', 'scope-test-epoch'));
        $control->register($id, $binding);

        return $binding;
    }

    public function scope(?TenantContext $context = null, ?string $epoch = null): DataScope
    {
        $context ??= $this->app->make(TenantContextResolver::class)->current();
        $this->register($context, epoch: $epoch);

        return $this->app->make(DataScopeFactory::class)->forCurrentPrincipal($context);
    }

    public function verify(ConnectionInterface $control, string $deploymentId): void
    {
        if ($control !== $this->control || $deploymentId !== 'test-installation') {
            throw new ScopeContextMismatch('Unexpected fixture control database.');
        }
    }

    public function verifyTarget(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
    {
        if ($connection === $this->control || ($this->owners[$context->account->id] ?? null) !== $connection) {
            throw new ScopeContextMismatch('Unexpected fixture business connection.');
        }
    }

    public function current(TenantContext $context): ScopePrincipal
    {
        return $this->principal;
    }

    public function run(DataScope $scope, TenantContext $context, bool $durable, Closure $work): mixed
    {
        if ($this->principal->kind !== $scope->principalKind || $this->principal->id !== $scope->principalId) {
            throw new ScopeContextMismatch('Unregistered fixture work principal.');
        }

        return $work();
    }
}
