<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Feature;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\CoreServiceProvider;
use CoreX\Database\Enums\IdStrategy;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Operations\ApiServiceProvider;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Data\ResourceId;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Internal\OperationDispatcher;
use CoreX\Operations\Invocation\Laravel\ResolvedContexts;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Internal\ToolDescriptorFactory;
use CoreX\Tools\ToolServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;

final class ToolAuthorizationLayersTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    public const LocalResource = 'note:uuid:01890f3e-7b2a-7cc0-98c4-dc0c0c07398f';

    public const ForeignTenantResource = 'note:uuid:01890f3e-7b2a-7cc0-98c4-dc0c0c073990';

    private ModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    private ToolAuthorizationLayersClientScopeFixture $clientScopes;

    private ToolAuthorizationLayersActorAuthorizerFixture $actors;

    private ToolAuthorizationLayersFieldAuthorizerFixture $fields;

    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, ApiServiceProvider::class, ToolServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $input = $this->objectSchema([
            'title' => ['type' => 'string'],
            'owner' => ['type' => 'string'],
            'resource_id' => ['type' => 'string'],
            'claimed_account' => ['type' => 'string'],
            'secret' => ['type' => 'string'],
        ]);
        $output = $this->objectSchema([
            'id' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'owner' => ['type' => 'string'],
            'account' => ['type' => 'string'],
            'secret' => ['type' => 'string'],
            'items' => [
                'type' => 'array',
                'items' => $this->objectSchema([
                    'title' => ['type' => 'string'],
                    'secret' => ['type' => 'string'],
                ]),
            ],
        ]);
        $operation = Api::operation(
            'notes.update',
            'POST',
            '/notes',
            ToolAuthorizationLayersAuthorizationHandler::class,
            'notes.write',
            $input,
            $output,
            [
                'title' => ['required', 'string'],
                'owner' => ['required', 'string'],
                'resource_id' => ['required', 'string'],
                'claimed_account' => ['sometimes', 'string'],
                'secret' => ['sometimes', 'string'],
            ],
        )->asCommand()
            ->idempotency('optional')
            ->effect('tenant_transactional')
            ->requiresScopes('notes.write')
            ->fields(
                read: ['/id', '/title', '/owner', '/account', '/items'],
                write: ['/title', '/owner', '/resource_id', '/claimed_account'],
            );
        $tool = Api::exposeTool(
            'notes.update',
            'v1',
            'notes.update',
            'v1',
            'write',
            'domain-key',
            $input,
            $output,
            [
                'title' => 'title',
                'owner' => 'owner',
                'resource_id' => 'resource_id',
                'claimed_account' => 'claimed_account',
                'secret' => 'secret',
            ],
        );
        $registry = app(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([$operation, $tool]),
        ], installedPackages: []);

        $this->modules = new ModuleFixture($registry);
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->identity = new IdentityFixture;
        $this->clientScopes = new ToolAuthorizationLayersClientScopeFixture;
        $this->actors = new ToolAuthorizationLayersActorAuthorizerFixture;
        $this->fields = new ToolAuthorizationLayersFieldAuthorizerFixture;

        $this->app->instance(ModuleRegistry::class, $this->modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        $this->app->instance(ClientScopeAuthorizer::class, $this->clientScopes);
        $this->app->instance(OperationAuthorizer::class, $this->actors);
        $this->app->instance(OperationFieldAuthorizer::class, $this->fields);
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
        $this->app->instance(PrincipalResolver::class, $this->identity);
        ToolAuthorizationLayersAuthorizationHandler::$calls = 0;
    }

    public function test_client_actor_record_field_and_tenant_layers_deny_independently(): void
    {
        $this->clientScopes->allow = false;
        $this->assertHttpError('operations.scope_required', $this->validInput());
        self::assertSame(0, ToolAuthorizationLayersAuthorizationHandler::$calls);

        $this->clientScopes->allow = true;
        $this->actors->allow = false;
        $this->assertHttpError('operations.access_denied', $this->validInput());
        self::assertSame(0, ToolAuthorizationLayersAuthorizationHandler::$calls);

        $this->actors->allow = true;
        $forbiddenInput = [...$this->validInput(), 'secret' => 'must-not-reach-handler'];
        $this->assertHttpError('operations.access_denied', $forbiddenInput);
        $toolDenied = $this->app->make(ToolInvoker::class)->invoke(
            $this->context(),
            $this->toolCall($forbiddenInput),
        );
        self::assertSame('operations.access_denied', $toolDenied->errorCode);
        self::assertSame(0, ToolAuthorizationLayersAuthorizationHandler::$calls);

        $this->assertHttpError('operations.resource_not_found', $this->validInput(owner: 'foreign-user'));
        self::assertSame(1, ToolAuthorizationLayersAuthorizationHandler::$calls);

        $this->assertHttpError('operations.resource_not_found', $this->validInput(resourceId: self::ForeignTenantResource));
        self::assertSame(2, ToolAuthorizationLayersAuthorizationHandler::$calls);
    }

    public function test_http_and_tool_apply_the_same_recursive_read_policy_without_trusting_payload_scope(): void
    {
        $this->fields->deniedRead = ['/owner', '/items/0/secret', '/items/1/secret'];
        $input = $this->validInput(claimedAccount: 'forged-account');

        $http = $this->dispatch($input);
        $tool = $this->app->make(ToolInvoker::class)->invoke($this->context(), $this->toolCall($input));
        $httpData = $http->getData(true)['data'];
        $toolData = JsonCanonical::decodeObject((string) $tool->structuredData?->canonicalJson);

        self::assertSame(200, $http->getStatusCode());
        self::assertSame(ToolResult::OUTCOME_COMMITTED, $tool->outcome);
        self::assertSame('account-a', $httpData['account']);
        self::assertSame('account-a', $toolData['account']);
        self::assertArrayNotHasKey('owner', $httpData);
        self::assertArrayNotHasKey('owner', $toolData);
        self::assertArrayNotHasKey('secret', $httpData);
        self::assertArrayNotHasKey('secret', $toolData);
        self::assertSame([0, 1], array_keys($httpData['items']));
        self::assertSame([0, 1], array_keys($toolData['items']));
        self::assertArrayNotHasKey('secret', $httpData['items'][0]);
        self::assertArrayNotHasKey('secret', $httpData['items'][1]);
        self::assertArrayNotHasKey('secret', $toolData['items'][0]);
        self::assertArrayNotHasKey('secret', $toolData['items'][1]);
        self::assertSame(2, ToolAuthorizationLayersAuthorizationHandler::$calls);
    }

    /** @param array<string, mixed> $input */
    private function dispatch(array $input, ?string $idempotencyKey = null): JsonResponse
    {
        return $this->app->make(OperationDispatcher::class)->dispatch(Request::create(
            '/api/v1/notes',
            'POST',
            server: array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => $idempotencyKey,
            ]),
            content: JsonCanonical::encode($input),
        ), 'v1', 'notes');
    }

    /** @param array<string, mixed> $input */
    private function assertHttpError(string $code, array $input): void
    {
        self::assertSame($code, $this->dispatch($input)->getData(true)['code']);
    }

    /** @return array<string, string> */
    private function validInput(
        string $owner = 'trusted-user',
        string $resourceId = self::LocalResource,
        string $claimedAccount = 'account-a',
    ): array {
        return [
            'title' => 'Hello',
            'owner' => $owner,
            'resource_id' => $resourceId,
            'claimed_account' => $claimedAccount,
        ];
    }

    /** @param array<string, mixed> $input */
    private function toolCall(array $input): ToolCall
    {
        $declaration = collect($this->modules->registry->api)->first(
            static fn (Api $api): bool => $api->mode === 'tool' && $api->toolCode === 'notes.update',
        );

        return new ToolCall(
            code: 'notes.update',
            toolVersion: 'v1',
            expectedContractHash: ToolDescriptorFactory::fromDeclaration($declaration)->contractHash,
            arguments: JsonObjectFactory::fromArray($input),
            requestId: 'tool-request',
        );
    }

    private function context(): RequestContext
    {
        return ResolvedContexts::make(
            $this->tenants->current(),
            $this->identity,
            new InvocationMetadata('request-1', 'v1', 'notes.update', 'POST'),
        );
    }

    /** @param array<string, mixed> $properties */
    private function objectSchema(array $properties): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
        ];
    }
}

final class ToolAuthorizationLayersClientScopeFixture implements ClientScopeAuthorizer
{
    public bool $allow = true;

    public function allows(Operation $operation, RequestContext $context): bool
    {
        return $this->allow;
    }
}

final class ToolAuthorizationLayersActorAuthorizerFixture implements OperationAuthorizer
{
    public bool $allow = true;

    public function allows(Operation $operation, RequestContext $context): bool
    {
        return $this->allow;
    }
}

final class ToolAuthorizationLayersFieldAuthorizerFixture implements OperationFieldAuthorizer
{
    /** @var list<string> */
    public array $deniedRead = [];

    /** @var list<string> */
    public array $deniedWrite = [];

    public function allowsRead(Operation $operation, RequestContext $context, string $path): bool
    {
        return ! in_array($path, $this->deniedRead, true);
    }

    public function allowsWrite(Operation $operation, RequestContext $context, string $path): bool
    {
        return ! in_array($path, $this->deniedWrite, true);
    }
}

final class ToolAuthorizationLayersAuthorizationHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;
        $resource = ResourceId::parse((string) $input['resource_id'], IdStrategy::Uuid7, 'note');

        if ($resource->toString() === ToolAuthorizationLayersTest::ForeignTenantResource
            || $input['owner'] !== $context->principal()->id()) {
            throw new ApiError(OperationsErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }

        return [
            'id' => $resource->toString(),
            'title' => $input['title'],
            'owner' => $input['owner'],
            'account' => $context->tenant()->account->id,
            'secret' => 'top-secret',
            'items' => [
                ['title' => 'first', 'secret' => 'first-secret'],
                ['title' => 'second', 'secret' => 'second-secret'],
            ],
        ];
    }
}
