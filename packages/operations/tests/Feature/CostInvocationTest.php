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
use CoreX\Operations\Tests\TestCase;
use CoreX\Time\Testing\FrozenClock;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Packman\Config\Data\SecretRef;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
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
        $store = new SqlIdempotencyStore(
            connection: $connection,
            contexts: $this->tenants,
            clock: new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00+00:00')),
            keyHmacSecret: new SecretRef('idempotency-test-secret'),
            leaseSeconds: 30,
            retentionSeconds: 300,
        );
        $invoker = new IdempotentOperationInvoker(
            inner: $inner,
            store: static fn (): IdempotencyStore => $store,
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

    private function admission(OperationBudget $budget): BudgetAdmission
    {
        return new BudgetAdmission(
            budget: $budget,
            estimator: new DeclaredCostEstimator,
            installationId: 'installation-a',
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
