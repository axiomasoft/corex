<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use Closure;
use CoreX\Api\ApiServiceProvider;
use CoreX\Api\Contracts\ClientScopeAuthorizer;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationBudget;
use CoreX\Api\Contracts\OperationFieldAuthorizer;
use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\OperationInvoker;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ToolExecutionGuard;
use CoreX\Api\Contracts\ToolInvoker;
use CoreX\Api\Contracts\ToolPolicy;
use CoreX\Api\Data\CostInput;
use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\ToolCall;
use CoreX\Api\Data\ToolDescriptor;
use CoreX\Api\Data\ToolResult;
use CoreX\Api\Internal\JsonCanonical;
use CoreX\Api\InvocationMetadata;
use CoreX\Api\Laravel\ApiSchemaRegistrar;
use CoreX\Api\Operation;
use CoreX\Api\OperationDispatcher;
use CoreX\Api\Testing\InvocationFixtures;
use CoreX\Api\Testing\ResolvedContexts;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\Support\IdentityFixture;
use CoreX\Api\Tests\Support\ModuleFixture;
use CoreX\Api\ToolServiceProvider;
use CoreX\CoreServiceProvider;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Support\SecretRef;
use CoreX\Support\Time\FrozenClock;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
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
        (require __DIR__.'/../../database/migrations/create_sys_api_idempotency_table.php')->up();
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
