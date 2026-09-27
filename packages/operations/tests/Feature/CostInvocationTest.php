<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use Closure;
use CoreX\Api\ApiError;
use CoreX\Api\Contracts\BudgetReservation;
use CoreX\Api\Contracts\ClientScopeAuthorizer;
use CoreX\Api\Contracts\IdempotencyStore;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationBudget;
use CoreX\Api\Contracts\OperationFieldAuthorizer;
use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\OperationInvoker;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\ReplayAuthorizer;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ToolExecutionGuard;
use CoreX\Api\Contracts\ToolInvoker;
use CoreX\Api\Contracts\ToolPolicy;
use CoreX\Api\Data\ActualCost;
use CoreX\Api\Data\BudgetScope;
use CoreX\Api\Data\CostInput;
use CoreX\Api\Data\EstimatedCost;
use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Data\OperationResult;
use CoreX\Api\Data\StoredOperationOutcome;
use CoreX\Api\Data\ToolCall;
use CoreX\Api\Data\ToolDescriptor;
use CoreX\Api\Data\ToolResult;
use CoreX\Api\Enums\IdempotencyRequirement;
use CoreX\Api\Enums\OperationMode;
use CoreX\Api\Internal\Cost\BudgetAdmission;
use CoreX\Api\Internal\Cost\BudgetStoreUnavailable;
use CoreX\Api\Internal\Cost\DeclaredCostEstimator;
use CoreX\Api\Internal\Cost\SqlOperationBudget;
use CoreX\Api\Internal\DefaultOperationInvoker;
use CoreX\Api\Internal\ErrorMapper;
use CoreX\Api\Internal\IdempotentOperationInvoker;
use CoreX\Api\Internal\JsonCanonical;
use CoreX\Api\Internal\ResolvedContext;
use CoreX\Api\Internal\SqlIdempotencyStore;
use CoreX\Api\InvocationMetadata;
use CoreX\Api\Laravel\ApiSchemaRegistrar;
use CoreX\Api\Operation;
use CoreX\Api\OperationDispatcher;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\Support\IdentityFixture;
use CoreX\Api\Tests\Support\ModuleFixture;
use CoreX\Api\Tests\TestCase;
use CoreX\Api\ToolServiceProvider;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;
use CoreX\Support\SecretRef;
use CoreX\Support\Time\FrozenClock;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class CostInvocationTest extends TestCase
{
    private ContextFixture $tenants;

    private IdentityFixture $principal;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ToolServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->principal = new IdentityFixture;
        Schema::dropIfExists('sys_api_idempotency');
        (require __DIR__.'/../../database/migrations/create_sys_api_idempotency_table.php')->up();
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
            errors: new ErrorMapper,
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
        self::assertSame(1, $replay->metadata['cost']['requested']);
        self::assertSame(1, $replay->metadata['cost']['actual']);
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
            $mapped = (new ErrorMapper)->map($error, reportUnexpected: false);
            self::assertSame('internal_error', $mapped->errorCode);
            self::assertSame(500, $mapped->status);
        }

        $preHandlerBudget = new RecordingOperationBudget;
        $preHandler = $this->defaultInvoker($this->modules(), $this->admission($preHandlerBudget));

        try {
            $preHandler->invoke($this->invocation($this->operation(handler: NonOperationHandler::class)));
            self::fail('An invalid handler should fail after reserve but before admission.');
        } catch (ApiError $error) {
            self::assertSame('internal_error', $error->errorCode);
        }

        self::assertSame(1, $preHandlerBudget->rejected);
        self::assertSame([], $preHandlerBudget->settled);
    }

    public function test_http_and_exposed_tool_share_cost_metadata_without_scope_keys(): void
    {
        $schema = $this->schema();
        $registry = (new RegistryCompiler)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::operation('notes.read', 'GET', '/notes', CostInvocationHandler::class, 'notes.read', $schema, $schema)
                    ->cost(['base' => 2]),
                Api::exposeTool('notes.read', 'v1', 'notes.read', 'v1', 'read', 'read-only', $schema, $schema, ['name' => 'name']),
            ]),
        ], installedPackages: []);
        $modules = new ModuleFixture($registry);
        $budget = $this->sqlBudget();
        $invoker = $this->defaultInvoker($modules, $this->admission($budget));
        $this->app->instance(ModuleRegistry::class, $modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        $this->app->instance(PrincipalResolver::class, $this->principal);
        $this->app->instance(OperationAuthorizer::class, $this->operationAuthorizer());
        $this->app->instance(OperationInvoker::class, $invoker);
        $this->app->instance(ToolPolicy::class, new AllowToolPolicy);
        $this->app->instance(ToolExecutionGuard::class, new PassThroughToolGuard);
        CostInvocationHandler::$calls = 0;

        $http = $this->app->make(OperationDispatcher::class)->dispatch(
            Request::create('/api/v1/notes', 'GET', server: ['HTTP_X_REQUEST_ID' => 'http-cost']),
            'v1',
            'notes',
        );
        $toolDeclaration = collect($registry->api)->first(
            static fn (Api $api): bool => $api->mode === 'tool',
        );
        self::assertInstanceOf(Api::class, $toolDeclaration);
        $tool = $this->app->make(ToolInvoker::class)->invoke(
            $this->context(),
            new ToolCall(
                code: 'notes.read',
                toolVersion: 'v1',
                expectedContractHash: ToolDescriptor::fromDeclaration($toolDeclaration)->contractHash,
                arguments: JsonObject::fromArray(['name' => 'read']),
                requestId: 'tool-cost',
                operationKey: null,
            ),
        );

        $httpCost = $http->getData(true)['meta']['cost'];
        $toolCost = $tool->metadata['cost'];
        self::assertSame(200, $http->getStatusCode());
        self::assertSame(ToolResult::OUTCOME_COMMITTED, $tool->outcome);
        self::assertSame(2, CostInvocationHandler::$calls);
        self::assertSame(['requested', 'actual', 'mode', 'throttle'], array_keys($httpCost));
        self::assertSame(array_keys($httpCost), array_keys($toolCost));
        self::assertSame([2, 2, 'enforce'], [$httpCost['requested'], $httpCost['actual'], $httpCost['mode']]);
        self::assertSame([2, 2, 'enforce'], [$toolCost['requested'], $toolCost['actual'], $toolCost['mode']]);
        self::assertSame(['capacity', 'available', 'restore_rate', 'observed_at'], array_keys($toolCost['throttle']));

        $wire = JsonCanonical::encode(['http' => $httpCost, 'tool' => $toolCost]);
        self::assertStringNotContainsString('account-a', $wire);
        self::assertStringNotContainsString('trusted-user', $wire);
        self::assertStringNotContainsString('client-a', $wire);
    }

    public function test_http_rate_limit_keeps_retry_after_and_store_failure_has_no_retry_header(): void
    {
        $schema = $this->schema();
        $registry = (new RegistryCompiler)->compile([
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
        self::assertSame('rate_limited', $limited->getData(true)['error']['code']);
        self::assertSame('7', $limited->headers->get('Retry-After'));
        self::assertSame(500, $unavailable->getStatusCode());
        self::assertSame('internal_error', $unavailable->getData(true)['error']['code']);
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

    private function sqlBudget(): SqlOperationBudget
    {
        $this->app['config']->set('database.connections.api_budget_admission', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('api_budget_admission');
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-api.schema', [
            'budget' => [
                'connection' => 'api_budget_admission',
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
            'tenant' => [
                'connection' => 'sqlite',
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]);
        $connection = DB::connection('api_budget_admission');
        (require __DIR__.'/../../database/migrations/budget/create_sys_api_budget_tables.php')->up();

        return new SqlOperationBudget(
            connection: $connection,
            clock: new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00+00:00')),
            scopeHmacSecret: new SecretRef('budget-test-secret'),
            capacity: 100,
            restoreRate: 10,
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
            requestSchema: [],
            responseSchema: [],
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
            input: ['name' => 'alpha'],
            parameters: [],
            idempotencyKey: $key,
        );
    }

    private function context(): ResolvedContext
    {
        return new ResolvedContext(
            $this->tenants->current(),
            $this->principal,
            new InvocationMetadata('request-1', 'v1', 'notes.create', 'POST'),
        );
    }

    private function modules(): ModuleFixture
    {
        return new ModuleFixture((new RegistryCompiler)->compile([], installedPackages: []));
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

final class AllowToolPolicy implements ToolPolicy
{
    public function discover(ToolDescriptor $tool, RequestContext $context): bool
    {
        return true;
    }

    public function invoke(ToolDescriptor $tool, RequestContext $context, JsonObject $arguments): bool
    {
        return true;
    }
}

final class PassThroughToolGuard implements ToolExecutionGuard
{
    public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult
    {
        return $effect();
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
