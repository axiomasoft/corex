<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use Closure;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ToolExecutionGuard;
use CoreX\Api\Contracts\ToolInvoker;
use CoreX\Api\Contracts\ToolPolicy;
use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\ToolCall;
use CoreX\Api\Data\ToolDescriptor;
use CoreX\Api\Data\ToolResult;
use CoreX\Api\Internal\JsonCanonical;
use CoreX\Api\Internal\ResolvedContext;
use CoreX\Api\InvocationMetadata;
use CoreX\Api\Operation;
use CoreX\Api\OperationDispatcher;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\Support\IdentityFixture;
use CoreX\Api\Tests\Support\ModuleFixture;
use CoreX\Api\Tests\Support\NoteHandler;
use CoreX\Api\Tests\TestCase;
use CoreX\Api\ToolServiceProvider;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Http\Request;

final class OperationParityTest extends TestCase
{
    private ModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ToolServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $schema = $this->schema(['title' => ['type' => 'string'], 'id' => ['type' => 'string'], 'actor' => ['type' => 'string'], 'account' => ['type' => 'string'], 'workspace' => ['type' => ['string', 'null']]]);
        $this->modules = new ModuleFixture(app(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::operation('notes.create', 'POST', '/notes', NoteHandler::class, 'notes.read', $schema, $schema, ['title' => ['required', 'string']]),
                Api::exposeTool('notes.create', 'v1', 'notes.create', 'v1', 'read', 'read-only', $schema, $schema, ['title' => 'title']),
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
        $request = Request::create('/chosen/v1/notes', 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUEST_ID' => 'request-123'], content: '{"title":"Hello"}');
        $http = $this->app->make(OperationDispatcher::class)->dispatch($request, 'v1', 'notes');
        $tool = $this->app->make(ToolInvoker::class)->invoke($this->context(), $this->toolCall());

        self::assertSame(200, $http->getStatusCode());
        self::assertSame('committed', $tool->outcome);
        self::assertSame(2, NoteHandler::$calls);
        self::assertSame($http->getData(true)['data']['actor'], json_decode((string) $tool->structuredData?->canonicalJson, true)['actor']);
        self::assertSame($http->getData(true)['data']['account'], json_decode((string) $tool->structuredData?->canonicalJson, true)['account']);
        self::assertSame('Hello', $http->getData(true)['data']['title']);
        self::assertNull($request->attributes->get(RequestContext::class));
    }

    public function test_denied_activation_and_authorization_skip_the_handler(): void
    {
        $this->app->instance(PrincipalResolver::class, $this->identity);
        $denied = $this->app->make(OperationDispatcher::class)->dispatch(
            Request::create('/chosen/v1/notes', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"title":"Hello"}'),
            'v1',
            'notes',
        );
        $this->modules->enabled = false;
        $this->allow();
        $inactive = $this->app->make(OperationDispatcher::class)->dispatch(
            Request::create('/chosen/v1/notes', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"title":"Hello"}'),
            'v1',
            'notes',
        );

        self::assertSame(403, $denied->getStatusCode());
        self::assertSame(404, $inactive->getStatusCode());
        self::assertSame(0, NoteHandler::$calls);
    }

    public function test_throwing_handler_restores_request_context(): void
    {
        $this->allow();
        $request = Request::create('/chosen/v1/notes/broken', 'GET', ['title' => 'Hello'], server: ['HTTP_X_REQUEST_ID' => 'request-123']);
        $this->modules = new ModuleFixture(app(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::operation('notes.card', 'GET', '/notes/{id}', NoteHandler::class, 'notes.read', $this->schema(), $this->schema(), ['title' => ['required', 'string']]),
            ]),
        ], installedPackages: []));
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $response = $this->app->make(OperationDispatcher::class)->dispatch($request, 'v1', 'notes/broken');

        self::assertSame(500, $response->getStatusCode());
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

    private function context(): ResolvedContext
    {
        return new ResolvedContext($this->tenants->current(), $this->identity, new InvocationMetadata('request-123', 'v1', 'notes.create', 'POST'));
    }

    private function toolCall(): ToolCall
    {
        $declaration = collect($this->modules->registry->api)->first(fn (Api $api): bool => $api->toolCode === 'notes.create');
        $descriptor = ToolDescriptor::fromDeclaration($declaration);

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
