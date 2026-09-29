<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Feature;

use Closure;
use CoreX\Config\SecretRef;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\CoreServiceProvider;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Definition\ModuleManifest;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Operations\ApiServiceProvider;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Cost\Contracts\OperationBudget;
use CoreX\Operations\Cost\Data\CostInput;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Definition\Operation;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\InvocationMetadata;
use CoreX\Operations\Invocation\OperationDispatcher;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\InvocationFixtures;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Testing\ResolvedContexts;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\JsonCanonical;
use CoreX\Time\FrozenClock;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\ToolServiceProvider;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use RuntimeException;

final class ToolCostInvocationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    private ContextFixture $tenants;

    private IdentityFixture $principal;

    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, ApiServiceProvider::class, ToolServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->principal = new IdentityFixture;
        Schema::dropIfExists('sys_api_idempotency');
        (require __DIR__.'/../../../operations/database/migrations/create_sys_api_idempotency_table.php')->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sys_api_idempotency');
        DB::purge('api_budget_admission');
        parent::tearDown();
    }

    public function test_http_and_exposed_tool_share_cost_metadata_without_scope_keys(): void
    {
        $schema = $this->schema();
        $registry = app(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::operation('notes.read', 'GET', '/notes', ToolCostInvocationCostInvocationHandler::class, 'notes.read', $schema, $schema)
                    ->cost(['base' => 2]),
                Api::exposeTool('notes.read', 'v1', 'notes.read', 'v1', 'read', 'read-only', $schema, $schema, ['name' => 'name']),
            ]),
        ], installedPackages: []);
        $modules = new ModuleFixture($registry);
        $budget = $this->sqlBudget();
        $invoker = $this->defaultInvoker($modules, $budget);
        $this->app->instance(ModuleRegistry::class, $modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        $this->app->instance(PrincipalResolver::class, $this->principal);
        $this->app->instance(OperationAuthorizer::class, $this->operationAuthorizer());
        $this->app->instance(OperationInvoker::class, $invoker);
        $this->app->instance(ToolPolicy::class, new ToolCostInvocationAllowToolPolicy);
        $this->app->instance(ToolExecutionGuard::class, new ToolCostInvocationPassThroughToolGuard);
        ToolCostInvocationCostInvocationHandler::$calls = 0;

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
        self::assertSame(2, ToolCostInvocationCostInvocationHandler::$calls);
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

    private function defaultInvoker(ModuleFixture $modules, OperationBudget $budget): OperationInvoker
    {
        return InvocationFixtures::costInvoker(
            modules: $modules,
            clientScopes: new ToolCostInvocationAllowClientScopeAuthorizer,
            authorizer: $this->operationAuthorizer(),
            fields: new ToolCostInvocationAllowFieldAuthorizer,
            container: $this->app,
            budget: $budget,
            installationId: 'installation-a',
            costInput: static fn (): CostInput => new CostInput(0, 0, 0, 0, false, 0, 0),
            clientId: static fn (): string => 'client-a',
        );
    }

    private function sqlBudget(): OperationBudget
    {
        $this->app['config']->set('database.connections.api_budget_admission', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('api_budget_admission');
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-operations.schema', [
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
        (require __DIR__.'/../../../operations/database/migrations/budget/create_sys_api_budget_tables.php')->up();

        return InvocationFixtures::sqlBudget(
            connection: $connection,
            clock: new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00+00:00')),
            scopeHmacSecret: new SecretRef('budget-test-secret'),
            capacity: 100,
            restoreRate: 10,
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

final class ToolCostInvocationCostInvocationHandler implements OperationHandler
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

final class ToolCostInvocationAllowClientScopeAuthorizer implements ClientScopeAuthorizer
{
    public function allows(Operation $operation, RequestContext $context): bool
    {
        return true;
    }
}

final class ToolCostInvocationAllowFieldAuthorizer implements OperationFieldAuthorizer
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

final class ToolCostInvocationAllowToolPolicy implements ToolPolicy
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

final class ToolCostInvocationPassThroughToolGuard implements ToolExecutionGuard
{
    public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult
    {
        return $effect();
    }
}
