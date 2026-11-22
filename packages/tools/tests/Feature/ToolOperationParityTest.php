<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Feature;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\CoreServiceProvider;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Invocation\Laravel\ResolvedContexts;
use CoreX\Operations\Laravel\Contracts\HttpOperationDispatcher;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Testing\NoteHandler;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Internal\ToolDescriptorFactory;
use CoreX\Tools\ToolsServiceProvider;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\ModulesServiceProvider;

final class ToolOperationParityTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    private ModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, OperationsServiceProvider::class, ToolsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $schema = $this->schema(['title' => ['type' => 'string'], 'id' => ['type' => 'string'], 'actor' => ['type' => 'string'], 'account' => ['type' => 'string'], 'workspace' => ['type' => ['string', 'null']]]);
        $this->modules = new ModuleFixture(resolve(ModuleCompilation::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::operation('notes.create', 'POST', '/notes', NoteHandler::class, 'notes.read', $schema, $schema, ['title' => ['required', 'string']], version: 'v2'),
                Api::exposeTool('notes.create', 'v2', 'notes.create', 'v1', 'read', 'read-only', $schema, $schema, ['title' => 'title']),
            ]),
        ], installedPackages: []));
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
        NoteHandler::$calls = 0;
    }

    public function test_http_and_exposed_tool_share_one_handler_call(): void
    {
        $this->allow();
        $request = Request::create('/chosen/v2/notes', 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUEST_ID' => 'request-123'], content: '{"title":"Hello"}');
        $request->attributes->set('corex.client_id', 'client-1');
        $http = $this->app->make(HttpOperationDispatcher::class)->dispatch($request, 'v2', 'notes');
        $tool = $this->app->make(ToolInvoker::class)->invoke($this->context(), $this->toolCall());

        self::assertSame(200, $http->getStatusCode());
        self::assertSame(ToolOutcome::Committed, $tool->outcome);
        self::assertSame(2, NoteHandler::$calls);
        self::assertSame($http->getData(true)['data']['actor'], json_decode((string) $tool->structuredData?->canonicalJson, true)['actor']);
        self::assertSame($http->getData(true)['data']['account'], json_decode((string) $tool->structuredData?->canonicalJson, true)['account']);
        self::assertSame('Hello', $http->getData(true)['data']['title']);
        self::assertNull($request->attributes->get(RequestContext::class));
    }

    private function allow(): void
    {
        $this->app->instance(PrincipalResolver::class, $this->identity);
        $this->app->instance(OperationAuthorizer::class, new class implements OperationAuthorizer
        {
            public function allows(Operation $operation, RequestContext $context): bool
            {
                return $operation->ability === 'notes.read';
            }
        });
    }

    private function context(): RequestContext
    {
        return ResolvedContexts::make($this->tenants->current(), $this->identity, new InvocationMetadata('request-123', 'v2', 'notes.create', 'POST'));
    }

    private function toolCall(): ToolCall
    {
        $declaration = collect($this->modules->registry->api)->first(fn (Api $api): bool => $api->toolCode === 'notes.create');
        $descriptor = ToolDescriptorFactory::fromDeclaration($declaration);

        return new ToolCall('notes.create', 'v1', $descriptor->contractHash, new JsonObject(JsonCanonical::encode(['title' => 'Hello'])), 'request-123');
    }

    /** @param  array<string, mixed>  $properties */
    private function schema(array $properties = ['title' => ['type' => 'string']]): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
        ];
    }
}
