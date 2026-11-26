<?php

declare(strict_types=1);

namespace CoreX\Tests\Support;

use Closure;
use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Internal\LocalWorkTransactions;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
use CoreX\Context\Laravel\ScopeOptions;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Time\Laravel\SystemClock;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;

/** In-process scope for caller tests that do not exercise owner-side admission. */
final readonly class CallerWorkScopeFixture implements CurrentWorkScope, DataScopeFactory, StoredWorkIssuer, StoredWorkRunner, WorkScopeRunner
{
    /** @param ?Closure(): ConnectionInterface $connection */
    public function __construct(
        private TenantContextResolver|TenantContext $contexts,
        private ?Closure $connection = null,
        private ?DataScope $expected = null,
    ) {
        $app = Container::getInstance();

        if ($expected !== null && $app instanceof Application && $app->bound(CallerReferenceAuthority::class)) {
            $app->make(CallerReferenceAuthority::class)->restoreFixture($expected);
        }
    }

    public static function scope(string $accountId, ?string $workspaceId, string $principalId, string $restoreEpoch, ?string $bindingId = null): DataScope
    {
        return new DataScope(
            deploymentId: 'caller-test-deployment',
            accountId: $accountId,
            workspaceId: $workspaceId,
            bindingId: $bindingId ?? 'test-binding-'.$accountId,
            bindingGeneration: 1,
            accountIncarnation: 'test-incarnation-'.$accountId,
            restoreEpoch: $restoreEpoch,
            principalKind: 'user',
            principalId: $principalId,
        );
    }

    public static function forContext(TenantContext $context, string $principalId, string $restoreEpoch, ?string $bindingId = null): DataScope
    {
        return self::scope($context->account->id, $context->workspace?->id, $principalId, $restoreEpoch, $bindingId);
    }

    public static function bind(Application $app, ?DataScope $expected = null): void
    {
        $fixture = new self(
            new readonly class($app) implements TenantContextResolver
            {
                public function __construct(private Application $app) {}

                public function current(): TenantContext
                {
                    return $this->app->make(TenantContextResolver::class)->current();
                }
            },
            static fn (): ConnectionInterface => $app->make(DatabaseManager::class)->connection(),
            $expected,
        );

        $authority = CallerReferenceAuthority::install($app);

        if ($expected !== null) {
            $authority->restoreFixture($expected);
        }
        $app->scoped(CurrentWorkScope::class, static fn (): CurrentWorkScope => $fixture);
        $app->scoped(DataScopeFactory::class, static fn (): DataScopeFactory => $fixture);
        $app->scoped(WorkScopeRunner::class, static fn (): WorkScopeRunner => $fixture);
        $app->scoped(StoredWorkRunner::class, static fn (): StoredWorkRunner => $fixture);
        $app->scoped(StoredWorkIssuer::class, static fn (): StoredWorkIssuer => $fixture);
    }

    public function forCurrentPrincipal(TenantContext $context): DataScope
    {
        $app = Container::getInstance();
        $active = $app instanceof Application && $app->bound(CallerReferenceAuthority::class) && $this->connection !== null
            ? $app->make(CallerReferenceAuthority::class)->transactionScope($context, ($this->connection)()) : null;
        $scope = $this->expected ?? $active ?? self::forContext($context, 'operator', str_repeat('a', 64));
        $this->assert($scope);

        return $scope;
    }

    public function assert(DataScope $scope, ?ConnectionInterface $connection = null): TenantContext
    {
        $context = $this->contexts instanceof TenantContext ? $this->contexts : $this->contexts->current();

        if ($scope->accountId !== $context->account->id) {
            throw new ScopeContextMismatch('Work scope account differs from the active test account.');
        }

        if ($scope->workspaceId !== $context->workspace?->id) {
            throw new ScopeContextMismatch('Work scope workspace differs from the active test workspace.');
        }

        if (($this->expected !== null && get_object_vars($this->expected) !== get_object_vars($scope))
            || ($connection !== null && $this->connection !== null && ($this->connection)() !== $connection)) {
            throw new ScopeContextMismatch('Work scope differs from the test owner connection.');
        }

        $app = Container::getInstance();

        if ($app instanceof Application && ($app->bound(CallerReferenceAuthority::class) || $scope->deploymentId === 'caller-test-deployment')) {
            CallerReferenceAuthority::install($app)->witness($this, $scope);
        }

        return $context;
    }

    public function issue(DataScope $scope, string $owner, string $reference): StoredWorkProof
    {
        $this->assert($scope);

        return new StoredWorkProof($owner, $reference, $this->seal($scope, $owner, $reference));
    }

    public function runStored(DataScope $scope, StoredWorkProof $proof, Closure $work, ?OperationKey $attempt = null): mixed
    {
        if (! hash_equals($this->seal($scope, $proof->owner, $proof->reference), $proof->seal)) {
            throw new ScopeContextMismatch('Unexpected stored work proof in caller fixture.');
        }

        return $this->run($scope, $work);
    }

    private function seal(DataScope $scope, string $owner, string $reference): string
    {
        return hash_hmac('sha256', json_encode([$scope, $owner, $reference], JSON_THROW_ON_ERROR), 'caller-test-key');
    }

    public function run(DataScope $scope, Closure $work): mixed
    {
        return $work($this->assert($scope));
    }
}

/** Single-process caller harness. Private SQLite control records survive business rollbacks.
 * The lifecycle release proofs use separate PostgreSQL processes and native owner adapters.
 */
final class CallerReferenceAuthority implements ControlStoreIsolation, ReferenceAdmission
{
    /** @var array<string,CallerWorkScopeFixture> */
    private array $witnesses = [];

    /** @var array<string, DatabaseScopeControl> */
    private array $controls = [];

    /** @var array<string, DataScope> */
    private array $enlisted = [];

    private readonly LocalWorkTransactions $transactions;

    private readonly Connection $connection;

    private function __construct(private readonly Application $app)
    {
        $app->make('config')->set('database.connections.caller-reference-control', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->connection = $app->make(DatabaseManager::class)->connection('caller-reference-control');
        ScopeControlSchema::install($this->connection);
        $this->transactions = new LocalWorkTransactions;
        $app->make('events')->listen([TransactionCommitted::class, TransactionRolledBack::class], $this->transactions->completed(...));
    }

    public static function install(Application $app): self
    {
        if (! $app->bound(self::class)) {
            $authority = new self($app);
            $app->instance(self::class, $authority);
            $app->singleton(ReferenceAdmission::class, static fn (): self => $authority);
        }

        return $app->make(self::class);
    }

    /** Explicit rebind by a test host models a restore, preserving the old control tickets. */
    public function restoreFixture(DataScope $scope): void
    {
        $control = $this->controls[$scope->deploymentId] ?? null;

        if ($control === null || ! $this->connection->table('corex_scope_routes')->where(['deployment_id' => $scope->deploymentId, 'account_id' => $scope->accountId])->exists()) {
            return;
        }
        $binding = $control->resolve(new AccountRef($scope->accountId, $scope->accountId, 'active', [], []));

        if ($binding->restoreEpoch === $scope->restoreEpoch) {
            return;
        }
        $closed = $control->drain($scope->accountId, $control->close($scope->accountId, $binding->controlRevision));
        $next = new PhysicalBinding($scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->accountIncarnation, $scope->restoreEpoch);
        $control->reopen($scope->accountId, $closed, $next, static fn (PhysicalBinding $candidate): bool => $candidate === $next);
    }

    public function verify(ConnectionInterface $control, string $deploymentId): void
    {
        if ($control !== $this->connection || ! isset($this->controls[$deploymentId])) {
            throw new ScopeContextMismatch('Foreign caller-fixture control owner.');
        }
    }

    public function witness(CallerWorkScopeFixture $current, DataScope $scope): void
    {
        $this->witnesses[$this->identity($scope)] = $current;
    }

    public function transactionScope(TenantContext $context, ConnectionInterface $connection): ?DataScope
    {
        foreach ($this->enlisted as $scope) {
            if ($context->account->id === $scope->accountId && $context->workspace?->id === $scope->workspaceId
                && $this->transactions->localTicket($scope, $connection) !== null) {
                return $scope;
            }
        }

        return null;
    }

    private function control(DataScope $scope): DatabaseScopeControl
    {
        $control = $this->controls[$scope->deploymentId] ??= new DatabaseScopeControl(
            $this->app->make(DatabaseManager::class), new ScopeOptions($scope->deploymentId, 'caller-reference-control', [], []),
            new ArrayDataScopeCodec, new SystemClock, $this,
        );

        if (! $this->connection->table('corex_scope_routes')->where(['deployment_id' => $scope->deploymentId, 'account_id' => $scope->accountId])->exists()) {
            $control->register($scope->accountId, new PhysicalBinding($scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->accountIncarnation, $scope->restoreEpoch));
        }
        $control->resolve(new AccountRef($scope->accountId, $scope->accountId, 'active', [], []))->assertScope($scope);

        return $control;
    }

    public function enlist(DataScope $scope, ConnectionInterface $connection, ModuleRef $target, ModuleRef ...$also): void
    {
        $current = $this->witnesses[$this->identity($scope)] ?? $this->app->make(CurrentWorkScope::class);
        $current->assert($scope, $connection);

        if ($connection->transactionLevel() === 0) {
            throw new LifecycleBlocked('Caller must enlist inside its owner transaction.');
        }
        $control = $this->control($scope);
        $this->enlisted[$this->identity($scope)] = $scope;
        $this->transactions->pin($control, $scope, $connection);
        $ticket = $this->transactions->localTicket($scope, $connection) ?? throw new LifecycleBlocked('No caller ticket.');
        $control->enlistTargets($ticket, array_map(static fn (ModuleRef $ref): string => $ref->package, [$target, ...array_values($also)]));
    }

    public function reserve(DataScope $scope, ProviderAttempt $attempt, ModuleRef $target, ModuleRef ...$also): AdmissionTicket
    {
        $current = $this->witnesses[$this->identity($scope)] ?? $this->app->make(CurrentWorkScope::class);
        $current->assert($scope);
        $targets = array_map(static fn (ModuleRef $ref): string => $ref->package, [$target, ...array_values($also)]);

        return $this->app->make(ProviderEffectLedger::class)->reserveTargets($scope, $attempt, $targets);
    }

    private function identity(DataScope $scope): string
    {
        return hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR));
    }
}
