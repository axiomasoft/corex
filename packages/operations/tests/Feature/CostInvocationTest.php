<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Cost\Contracts\BudgetReservation;
use CoreX\Operations\Cost\Contracts\OperationBudget;
use CoreX\Operations\Cost\Data\ActualCost;
use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\CostInput;
use CoreX\Operations\Cost\Data\EstimatedCost;
use CoreX\Operations\Cost\Exceptions\BudgetStoreUnavailable;
use CoreX\Operations\Cost\Internal\BudgetAdmission;
use CoreX\Operations\Cost\Internal\DeclaredCostEstimator;
use CoreX\Operations\Cost\Internal\SqlOperationBudget;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Errors\Internal\ErrorMapper;
use CoreX\Operations\Idempotency\Contracts\IdempotencyStore;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Idempotency\Internal\IdempotentOperationInvoker;
use CoreX\Operations\Idempotency\Internal\SqlIdempotencyStore;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Data\OperationResult;
use CoreX\Operations\Invocation\Internal\DefaultOperationInvoker;
use CoreX\Operations\Invocation\Internal\OperationDispatcher;
use CoreX\Operations\Invocation\Laravel\ResolvedContexts;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Tests\Support\IdempotencyWorkScope;
use CoreX\Operations\Tests\TestCase;
use CoreX\Time\Testing\FrozenClock;
use DateTimeImmutable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Packman\Config\Data\SecretRef;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSets;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class CostInvocationTest extends TestCase
{
    private ContextFixture $tenants;

    private IdentityFixture $principal;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app)];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->principal = new IdentityFixture;
        Schema::dropIfExists('sys_api_idempotency');
        $this->migrateSchemaSets(['axiomasoft/corex-operations:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sys_api_idempotency');
        DB::purge('api_budget_admission');
        parent::tearDown();
    }

    public function test_budget_denial_rolls_back_unstarted_idempotency_and_replay_charges_base_only(): void
    {
        $budget = new RecordingOperationBudget;
        $admission = $this->admission($budget);
        $modules = $this->modules();
        $connection = DB::connection('sqlite');
        $inner = $this->defaultInvoker($modules, $admission);
        $work = new IdempotencyWorkScope($this->tenants, $connection);
        $store = new SqlIdempotencyStore(
            connection: $connection,
            current: $work,
            clock: new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00+00:00')),
            keyHmacSecret: new SecretRef('idempotency-test-secret'),
            leaseSeconds: 30,
            retentionSeconds: 300,
        );
        $invoker = new IdempotentOperationInvoker(
            inner: $inner,
            store: static fn (): IdempotencyStore => $store,
            scopes: $work,
            current: $work,
            clientScopes: new AllowClientScopeAuthorizer,
            fields: new AllowFieldAuthorizer,
            connection: $connection,
            modules: $modules,
            authorizer: $this->operationAuthorizer(),
            replayAuthorizer: new AllowReplayAuthorizer,
            errors: resolve(ErrorMapper::class),
            problems: resolve(ProblemRegistry::class),
            budget: $admission,
        );
        $invocation = $this->invocation($this->operation(idempotency: IdempotencyRequirement::Required), 'shared-key');
        CostInvocationHandler::$calls = 0;
        $budget->deny = true;

        try {
            $invoker->invoke($invocation);
            self::fail('Budget denial should stop before the handler.');
        } catch (TooManyRequestsHttpException $error) {
            self::assertSame(7, $error->getHeaders()['Retry-After']);
        }

        self::assertSame(0, CostInvocationHandler::$calls);
        self::assertSame(0, $connection->table('sys_api_idempotency')->count());

        $budget->deny = false;
        $fresh = $invoker->invoke($invocation);
        $replay = $invoker->invoke($invocation);

        self::assertSame(1, CostInvocationHandler::$calls);
        self::assertFalse($fresh->replayed);
        self::assertTrue($replay->replayed);
        self::assertSame([5, 1], array_map(static fn (EstimatedCost $cost): int => $cost->units, $budget->costs));
        self::assertSame(1, JsonDocuments::map($replay->metadata)['cost']['requested']);
        self::assertSame(1, JsonDocuments::map($replay->metadata)['cost']['actual']);
        self::assertSame(1, $connection->table('sys_api_idempotency')->count());
    }

    public function test_post_admission_unknown_keeps_estimate_and_store_failure_is_not_rate_limited(): void
    {
        $budget = new RecordingOperationBudget;
        $invoker = $this->defaultInvoker($this->modules(), $this->admission($budget));
        CostInvocationHandler::$calls = 0;
        CostInvocationHandler::$throw = true;

        try {
            $invoker->invoke($this->invocation($this->operation()));
            self::fail('The handler failure should escape the shared invoker.');
        } catch (RuntimeException $error) {
            self::assertSame('unknown handler outcome', $error->getMessage());
        } finally {
            CostInvocationHandler::$throw = false;
        }

        self::assertSame(1, CostInvocationHandler::$calls);
        self::assertSame([5], $budget->settled);
        self::assertSame(0, $budget->rejected);

        $budget->storeFailure = true;

        try {
            $invoker->invoke($this->invocation($this->operation()));
            self::fail('A missing budget authority must fail closed.');
        } catch (BudgetStoreUnavailable $error) {
            $mapped = (resolve(ErrorMapper::class))->map($error, reportUnexpected: false);
            self::assertSame('operations.budget_store_unavailable', $mapped->errorCode);
            self::assertSame(500, $mapped->status);
        }

        $preHandlerBudget = new RecordingOperationBudget;
        $preHandler = $this->defaultInvoker($this->modules(), $this->admission($preHandlerBudget));

        try {
            $preHandler->invoke($this->invocation($this->operation(handler: NonOperationHandler::class)));
            self::fail('An invalid handler should fail after reserve but before admission.');
        } catch (ApiError $error) {
            self::assertSame('core.internal_error', $error->errorCode);
        }

        self::assertSame(1, $preHandlerBudget->rejected);
        self::assertSame([], $preHandlerBudget->settled);
    }

    public function test_http_rate_limit_keeps_retry_after_and_store_failure_has_no_retry_header(): void
    {
        $schema = $this->schema();
        $registry = resolve(ModuleCompilation::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::operation('notes.read', 'GET', '/notes', CostInvocationHandler::class, 'notes.read', $schema, $schema),
            ]),
        ], installedPackages: []);
        $modules = new ModuleFixture($registry);
        $this->app->instance(ModuleRegistry::class, $modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        $this->app->instance(PrincipalResolver::class, $this->principal);
        $this->app->instance(OperationAuthorizer::class, $this->operationAuthorizer());
        $request = static fn (): Request => Request::create('/api/v1/notes', 'GET');

        $this->app->instance(OperationInvoker::class, new ThrowingOperationInvoker(
            new TooManyRequestsHttpException(7, 'Budget exhausted.'),
        ));
        $limited = $this->app->make(OperationDispatcher::class)->dispatch($request(), 'v1', 'notes');

        $this->app->instance(OperationInvoker::class, new ThrowingOperationInvoker(
            new BudgetStoreUnavailable('Budget store unavailable.'),
        ));
        $unavailable = $this->app->make(OperationDispatcher::class)->dispatch($request(), 'v1', 'notes');

        self::assertSame(429, $limited->getStatusCode());
        self::assertSame('operations.rate_limited', $limited->getData(true)['code']);
        self::assertSame('7', $limited->headers->get('Retry-After'));
        self::assertSame(500, $unavailable->getStatusCode());
        self::assertSame('operations.budget_store_unavailable', $unavailable->getData(true)['code']);
        self::assertNull($unavailable->headers->get('Retry-After'));
    }

    #[Group('pg')]
    public function test_committed_tenant_receipt_recovers_a_lost_dedicated_budget_settlement_once(): void
    {
        $tenantName = getenv('PGSQL_DATABASE') ?: '';
        $budgetName = getenv('PGSQL_BUDGET_DATABASE') ?: '';
        self::assertStringEndsWith('_test', $tenantName);
        self::assertStringEndsWith('_test', $budgetName);
        self::assertNotSame($tenantName, $budgetName);
        $connectionConfig = [
            'driver' => 'pgsql', 'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434', 'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex', 'charset' => 'utf8', 'timezone' => 'UTC', 'prefix' => '',
            'search_path' => 'corex_cost_receipt_test',
        ];
        config([
            'database.connections.cost_receipt_tenant' => $connectionConfig + ['database' => $tenantName],
            'database.connections.cost_receipt_retry' => $connectionConfig + ['database' => $tenantName],
            'database.connections.cost_receipt_budget' => $connectionConfig + ['database' => $budgetName],
            'database.connections.cost_receipt_observer' => $connectionConfig + ['database' => $budgetName],
            'corex-operations.schema' => [
                'budget' => ['connection' => 'cost_receipt_budget', 'driver' => 'pgsql', 'database' => $budgetName],
                'tenant' => ['connection' => 'cost_receipt_tenant', 'driver' => 'pgsql', 'database' => $tenantName],
            ],
        ]);
        $this->app->forgetInstance(ApiSchemaConnections::class);
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
        $tenant = DB::connection('cost_receipt_tenant');
        $retry = DB::connection('cost_receipt_retry');
        $budget = DB::connection('cost_receipt_budget');
        $observer = DB::connection('cost_receipt_observer');

        foreach ([$tenant, $budget] as $connection) {
            $connection->statement('DROP SCHEMA IF EXISTS corex_cost_receipt_test CASCADE');
            $connection->statement('CREATE SCHEMA corex_cost_receipt_test');
        }

        try {
            $this->migrateSchemaSets(['axiomasoft/corex-operations:tenant'], 'cost_receipt_tenant');
            $this->migrateSchemaSets(['axiomasoft/corex-operations:budget'], null);
            $tenant->getSchemaBuilder()->create('cost_receipt_effects', static fn (Blueprint $table) => $table->string('name'));
            $observer->unprepared(<<<'SQL'
                CREATE FUNCTION fail_settlement_once() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF OLD.state = 'held' AND NEW.state = 'settled' THEN
                        RAISE EXCEPTION 'fixture drops settlement';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER cost_settlement_failure BEFORE UPDATE ON sys_api_budget_reservations
                    FOR EACH ROW EXECUTE FUNCTION fail_settlement_once();
                SQL);
            $clock = new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00Z'));
            $admission = $this->admission(new SqlOperationBudget($budget, $clock, new SecretRef('fixture-budget')));
            $this->app->instance(ReceiptCostHandler::class, new ReceiptCostHandler($tenant));
            $operation = $this->operation(handler: ReceiptCostHandler::class, idempotency: IdempotencyRequirement::Required);
            $invocation = $this->invocation($operation, 'cost-receipt');

            $fresh = $this->transactionalCostInvoker($tenant, $admission, $clock)->invoke($invocation);
            $journal = $retry->table('sys_api_idempotency')->value('outcome_json');
            $receipt = StoredOperationOutcome::fromCanonicalJson($journal, resolve(ProblemRegistry::class))->budgetReceipt;
            self::assertNotNull($receipt);
            self::assertFalse($fresh->replayed);
            self::assertSame(5, $receipt->actualUnits);
            self::assertSame(1, $retry->table('cost_receipt_effects')->count());
            self::assertSame('held', $observer->table('sys_api_budget_reservations')->where('id', $receipt->reservationId)->value('state'));

            $observer->statement('DROP TRIGGER cost_settlement_failure ON sys_api_budget_reservations');
            $second = $this->transactionalCostInvoker($retry, $admission, $clock);
            self::assertTrue($second->invoke($invocation)->replayed);
            self::assertSame('settled', $observer->table('sys_api_budget_reservations')->where('id', $receipt->reservationId)->value('state'));
            self::assertSame(2, $observer->table('sys_api_budget_reservations')->count());
            self::assertSame(1, $retry->table('cost_receipt_effects')->count());

            $tenant->unprepared(<<<'SQL'
                CREATE FUNCTION fail_owner_receipt_once() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF NEW.state = 'completed' THEN
                        RAISE EXCEPTION 'fixture rolls back the tenant receipt';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER cost_owner_receipt_failure BEFORE UPDATE ON sys_api_idempotency
                    FOR EACH ROW EXECUTE FUNCTION fail_owner_receipt_once();
                SQL);
            $uncommitted = new OperationInvocation($operation, $invocation->context, JsonDocuments::capture(['name' => 'retry']), [], 'retry-cost');

            try {
                $this->transactionalCostInvoker($tenant, $admission, $clock)->invoke($uncommitted);
                self::fail('The injected tenant receipt failure did not abort the transaction.');
            } catch (ApiError $error) {
                self::assertSame('core.internal_error', $error->errorCode);
            }
            self::assertSame(1, $retry->table('sys_api_idempotency')->count());
            self::assertSame(0, $retry->table('cost_receipt_effects')->where('name', 'retry')->count());
            $retained = $observer->table('sys_api_budget_reservations')->where('state', 'held')->first();
            self::assertNotNull($retained);
            $balances = $observer->table('sys_api_budget_buckets')->orderBy('key')->pluck('available')->all();
            $tenant->statement('DROP TRIGGER cost_owner_receipt_failure ON sys_api_idempotency');
            $this->app->instance(ReceiptCostHandler::class, new ReceiptCostHandler($retry));
            self::assertFalse($second->invoke($uncommitted)->replayed);
            self::assertSame(1, $retry->table('cost_receipt_effects')->where('name', 'retry')->count());
            self::assertSame('settled', $observer->table('sys_api_budget_reservations')->where('id', $retained->id)->value('state'));
            self::assertSame(3, $observer->table('sys_api_budget_reservations')->count());
            self::assertSame($balances, $observer->table('sys_api_budget_buckets')->orderBy('key')->pluck('available')->all());

            $failed = new OperationInvocation($operation, $invocation->context, JsonDocuments::capture(['name' => 'rollback']), [], 'rollback-cost');
            $this->app->instance(ReceiptCostHandler::class, new ReceiptCostHandler($retry));

            try {
                $second->invoke($failed);
                self::fail('The injected handler failure did not abort the transaction.');
            } catch (ApiError $error) {
                self::assertSame('core.internal_error', $error->errorCode);
            }
            self::assertSame(0, $tenant->table('cost_receipt_effects')->where('name', 'rollback')->count());
            self::assertSame(1, $observer->table('sys_api_budget_reservations')->where('state', 'held')->count());
        } finally {
            $tenant->statement('DROP SCHEMA IF EXISTS corex_cost_receipt_test CASCADE');
            $budget->statement('DROP SCHEMA IF EXISTS corex_cost_receipt_test CASCADE');
            foreach (['cost_receipt_tenant', 'cost_receipt_retry', 'cost_receipt_budget', 'cost_receipt_observer'] as $name) {
                DB::purge($name);
            }
        }
    }

    private function transactionalCostInvoker(Connection $connection, BudgetAdmission $budget, FrozenClock $clock): IdempotentOperationInvoker
    {
        $work = new IdempotencyWorkScope($this->tenants, $connection);
        $store = new SqlIdempotencyStore($connection, $work, $clock, new SecretRef('fixture-owner'), 30, 300);
        $modules = $this->modules();

        return new IdempotentOperationInvoker(
            inner: $this->defaultInvoker($modules, $budget),
            store: static fn (): IdempotencyStore => $store,
            connection: $connection,
            modules: $modules,
            scopes: $work,
            current: $work,
            clientScopes: new AllowClientScopeAuthorizer,
            fields: new AllowFieldAuthorizer,
            authorizer: $this->operationAuthorizer(),
            replayAuthorizer: new AllowReplayAuthorizer,
            errors: resolve(ErrorMapper::class),
            problems: resolve(ProblemRegistry::class),
            budget: $budget,
        );
    }

    private function admission(OperationBudget $budget): BudgetAdmission
    {
        return new BudgetAdmission(
            budget: $budget,
            estimator: new DeclaredCostEstimator,
            installationId: 'installation-a',
            exceptions: resolve(ExceptionHandler::class),
            costInput: static fn (): CostInput => new CostInput(0, 0, 0, 0, false, 0, 0),
            clientId: static fn (): string => 'client-a',
        );
    }

    private function defaultInvoker(ModuleFixture $modules, BudgetAdmission $budget): DefaultOperationInvoker
    {
        return new DefaultOperationInvoker(
            modules: $modules,
            clientScopes: new AllowClientScopeAuthorizer,
            authorizer: $this->operationAuthorizer(),
            fields: new AllowFieldAuthorizer,
            container: $this->app,
            budget: $budget,
        );
    }

    /** @param class-string $handler */
    private function operation(
        IdempotencyRequirement $idempotency = IdempotencyRequirement::Forbidden,
        string $handler = CostInvocationHandler::class,
    ): Operation {
        return new Operation(
            module: 'example/notes',
            id: 'notes.create',
            version: 'v1',
            method: 'POST',
            path: '/notes',
            ability: 'notes.write',
            handler: $handler,
            requestSchema: JsonDocuments::capture([]),
            responseSchema: JsonDocuments::capture([]),
            inputRules: [],
            successStatus: 200,
            mode: OperationMode::Command,
            idempotency: $idempotency,
            readFields: ['/name'],
            writeFields: ['/name'],
            contractHash: hash('sha256', 'notes.create/v1'),
            effectMode: $idempotency === IdempotencyRequirement::Required ? 'tenant_transactional' : null,
            cost: ['base' => 5],
        );
    }

    private function invocation(Operation $operation, ?string $key = null): OperationInvocation
    {
        return new OperationInvocation(
            operation: $operation,
            context: $this->context(),
            input: JsonDocuments::capture(['name' => 'alpha']),
            parameters: [],
            idempotencyKey: $key,
        );
    }

    private function context(): RequestContext
    {
        return ResolvedContexts::make(
            $this->tenants->current(),
            $this->principal,
            new InvocationMetadata('request-1', 'v1', 'notes.create', 'POST'),
        );
    }

    private function modules(): ModuleFixture
    {
        return new ModuleFixture(resolve(ModuleCompilation::class)->compile([], installedPackages: []));
    }

    private function operationAuthorizer(): OperationAuthorizer
    {
        return new class implements OperationAuthorizer
        {
            public function allows(Operation $operation, RequestContext $context): bool
            {
                return true;
            }
        };
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => ['name' => ['type' => 'string']],
        ];
    }
}

final class RecordingOperationBudget implements OperationBudget
{
    public bool $deny = false;

    public bool $storeFailure = false;

    /** @var list<EstimatedCost> */
    public array $costs = [];

    /** @var list<int> */
    public array $settled = [];

    public int $rejected = 0;

    public function reserve(BudgetScope $scope, EstimatedCost $cost): BudgetReservation
    {
        if ($this->storeFailure) {
            throw new RuntimeException('database credentials must not escape');
        }

        if ($this->deny) {
            throw new TooManyRequestsHttpException(7, 'Budget exhausted.');
        }

        $this->costs[] = $cost;

        return new RecordingBudgetReservation($this);
    }
}

final readonly class RecordingBudgetReservation implements BudgetReservation
{
    public function __construct(private RecordingOperationBudget $budget) {}

    public function settle(ActualCost $cost): void
    {
        $this->budget->settled[] = $cost->units;
    }

    public function reject(): void
    {
        $this->budget->rejected++;
    }
}

final class CostInvocationHandler implements OperationHandler
{
    public static int $calls = 0;

    public static bool $throw = false;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        if (self::$throw) {
            throw new RuntimeException('unknown handler outcome');
        }

        return ['name' => $input['name'] ?? 'read'];
    }
}

final readonly class ReceiptCostHandler implements OperationHandler
{
    public function __construct(private Connection $connection) {}

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $name = $input['name'] ?? 'alpha';
        $this->connection->table('cost_receipt_effects')->insert(['name' => $name]);

        if ($name === 'rollback') {
            throw new RuntimeException('fixture rolls back the owner transaction');
        }

        return ['name' => $name];
    }
}

final class NonOperationHandler {}

final class AllowClientScopeAuthorizer implements ClientScopeAuthorizer
{
    public function allows(Operation $operation, RequestContext $context): bool
    {
        return true;
    }
}

final class AllowFieldAuthorizer implements OperationFieldAuthorizer
{
    public function allowsRead(Operation $operation, RequestContext $context, string $path): bool
    {
        return true;
    }

    public function allowsWrite(Operation $operation, RequestContext $context, string $path): bool
    {
        return true;
    }
}

final class AllowReplayAuthorizer implements ReplayAuthorizer
{
    public function authorize(
        OperationInvocation $invocation,
        StoredOperationOutcome $outcome,
        bool $replayed,
    ): StoredOperationOutcome {
        return $outcome;
    }
}

final readonly class ThrowingOperationInvoker implements OperationInvoker
{
    public function __construct(private Throwable $error) {}

    public function invoke(OperationInvocation $invocation): OperationResult
    {
        throw $this->error;
    }
}
