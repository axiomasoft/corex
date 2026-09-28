<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use Closure;
use CoreX\Api\ApiError;
use CoreX\Api\ApiServiceProvider;
use CoreX\Api\Contracts\OperationAuthorizer;
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
use CoreX\Api\Operation;
use CoreX\Api\OperationDispatcher;
use CoreX\Api\OperationRegistry;
use CoreX\Api\Testing\InvocationFixtures;
use CoreX\Api\Testing\ResolvedContexts;
use CoreX\Api\Tests\Fixtures\LoadContourModule;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\Support\IdentityFixture;
use CoreX\Api\Tests\Support\ModuleFixture;
use CoreX\Api\ToolServiceProvider;
use CoreX\CoreServiceProvider;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;

final class ToolLoadContourIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    private const string Secret = 'whsec_AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';

    private ModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, ApiServiceProvider::class, ToolServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->modules = new ModuleFixture(LoadContourModule::registry());
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('A', 'a', 'active', [], []));
        $this->identity = new IdentityFixture;
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        $this->app->instance(ToolPolicy::class, new class implements ToolPolicy
        {
            public function discover(ToolDescriptor $tool, RequestContext $context): bool
            {
                return true;
            }

            public function invoke(ToolDescriptor $tool, RequestContext $context, JsonObject $arguments): bool
            {
                return true;
            }
        });
        $this->app->instance(ToolExecutionGuard::class, new class implements ToolExecutionGuard
        {
            public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult
            {
                return $effect();
            }
        });
        LoadContourModule::$calls = 0;
    }

    public function test_http_and_tool_share_one_handler_and_declared_cost_clamps(): void
    {
        $this->app->instance(PrincipalResolver::class, $this->identity);
        $this->app->instance(OperationAuthorizer::class, new class implements OperationAuthorizer
        {
            public function allows(Operation $operation, RequestContext $context): bool
            {
                return $operation->ability === 'notes.read';
            }
        });
        $request = Request::create('/chosen/v1/notes', 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_REQUEST_ID' => 'request-123',
        ], content: '{"title":"Hello"}');
        $http = $this->app->make(OperationDispatcher::class)->dispatch($request, 'v1', 'notes');
        $declaration = collect($this->modules->registry->api)->first(
            static fn (Api $api): bool => $api->toolCode === 'notes.create',
        );
        $descriptor = ToolDescriptor::fromDeclaration($declaration);
        $tool = $this->app->make(ToolInvoker::class)->invoke(
            ResolvedContexts::make($this->tenants->current(), $this->identity, new InvocationMetadata('request-123', 'v1', 'notes.create', 'POST')),
            new ToolCall('notes.create', 'v1', $descriptor->contractHash, new JsonObject(JsonCanonical::encode(['title' => 'Hello'])), 'request-123'),
        );
        $operation = array_values(array_filter(
            (new OperationRegistry($this->modules))->all('v1'),
            static fn (Operation $candidate): bool => $candidate->id === 'notes.create',
        ))[0];
        $estimator = InvocationFixtures::declaredCosts();

        self::assertSame(200, $http->getStatusCode());
        self::assertSame('committed', $tool->outcome);
        self::assertSame(2, LoadContourModule::$calls);
        self::assertSame('Hello', $http->getData(true)['data']['title']);
        self::assertSame(2, $estimator->estimate($operation, new CostInput(1, 0, 0, 0, false, 0, 0))->units);

        try {
            $estimator->estimate($operation, new CostInput(100, 0, 0, 0, false, 0, 0));
            self::fail('Declared cost accepted an overflow.');
        } catch (ApiError $exception) {
            self::assertSame('validation_failed', $exception->errorCode);
        }
    }
}
