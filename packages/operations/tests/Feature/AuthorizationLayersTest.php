<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use Closure;
use CoreX\Api\ApiError;
use CoreX\Api\Contracts\ClientScopeAuthorizer;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationFieldAuthorizer;
use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\ReplayAuthorizer;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ToolExecutionGuard;
use CoreX\Api\Contracts\ToolInvoker;
use CoreX\Api\Contracts\ToolPolicy;
use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Data\ResourceId;
use CoreX\Api\Data\StoredOperationOutcome;
use CoreX\Api\Data\ToolCall;
use CoreX\Api\Data\ToolDescriptor;
use CoreX\Api\Data\ToolResult;
use CoreX\Api\Internal\DenyClientScopeAuthorizer;
use CoreX\Api\Internal\JsonCanonical;
use CoreX\Api\Internal\ResolvedContext;
use CoreX\Api\InvocationMetadata;
use CoreX\Api\Operation;
use CoreX\Api\OperationDispatcher;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\Support\IdentityFixture;
use CoreX\Api\Tests\Support\ModuleFixture;
use CoreX\Api\Tests\TestCase;
use CoreX\Api\ToolServiceProvider;
use CoreX\Enums\IdStrategy;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class AuthorizationLayersTest extends TestCase
{
    public const LocalResource = 'note:uuid:01890f3e-7b2a-7cc0-98c4-dc0c0c07398f';

    public const ForeignTenantResource = 'note:uuid:01890f3e-7b2a-7cc0-98c4-dc0c0c073990';

    private ModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    private ClientScopeFixture $clientScopes;

    private ActorAuthorizerFixture $actors;

    private FieldAuthorizerFixture $fields;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ToolServiceProvider::class];
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
            AuthorizationHandler::class,
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
        $this->clientScopes = new ClientScopeFixture;
        $this->actors = new ActorAuthorizerFixture;
        $this->fields = new FieldAuthorizerFixture;

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
        AuthorizationHandler::$calls = 0;
    }

    public function test_resource_ids_round_trip_all_supported_strategies_and_reject_mismatch(): void
    {
        $vectors = [
            [IdStrategy::Uuid7, '01890f3e-7b2a-7cc0-98c4-dc0c0c07398f', self::LocalResource],
            [IdStrategy::Uuid, '550e8400-e29b-41d4-a716-446655440000', 'note:uuid:550e8400-e29b-41d4-a716-446655440000'],
            [IdStrategy::Ulid, '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'note:ulid:01ARZ3NDEKTSV4RRFFQ69G5FAV'],
            [IdStrategy::Int, 42, 'note:integer:42'],
        ];

        foreach ($vectors as [$strategy, $value, $encoded]) {
            $resourceId = ResourceId::fromValue('note', $value, $strategy);

            self::assertSame($encoded, $resourceId->toString());
            self::assertSame($encoded, ResourceId::parse($encoded, $strategy, 'note')->toString());
        }

        self::assertSame(self::LocalResource, ResourceId::fromConfigured(
            'note',
            '01890f3e-7b2a-7cc0-98c4-dc0c0c07398f',
            IdStrategy::Uuid7,
        )->toString());
        self::assertSame(self::LocalResource, ResourceId::parse(
            self::LocalResource,
            IdStrategy::Uuid,
        )->toString());

        foreach ([
            fn () => ResourceId::parse(self::LocalResource, IdStrategy::Ulid),
            fn () => ResourceId::parse('note:uuid:550e8400-e29b-41d4-a716-446655440000', IdStrategy::Uuid7),
            fn () => ResourceId::parse(self::LocalResource, IdStrategy::Uuid7, 'contact'),
            fn () => ResourceId::fromValue('note', '01arz3ndektsv4rrffq69g5fav', IdStrategy::Ulid),
            fn () => ResourceId::fromValue('note', '042', IdStrategy::Int),
            fn () => ResourceId::fromValue('Note', 42, IdStrategy::Int),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Invalid resource id was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_default_client_scope_policy_allows_only_unscoped_operations(): void
    {
        $authorizer = new DenyClientScopeAuthorizer;

        self::assertFalse($authorizer->allows($this->operation(), $this->context()));
        self::assertTrue($authorizer->allows($this->operation(requiredScopes: []), $this->context()));
    }

    public function test_client_actor_record_field_and_tenant_layers_deny_independently(): void
    {
        $this->clientScopes->allow = false;
        $this->assertHttpError('scope_required', $this->validInput());
        self::assertSame(0, AuthorizationHandler::$calls);

        $this->clientScopes->allow = true;
        $this->actors->allow = false;
        $this->assertHttpError('access_denied', $this->validInput());
        self::assertSame(0, AuthorizationHandler::$calls);

        $this->actors->allow = true;
        $forbiddenInput = [...$this->validInput(), 'secret' => 'must-not-reach-handler'];
        $this->assertHttpError('access_denied', $forbiddenInput);
        $toolDenied = $this->app->make(ToolInvoker::class)->invoke(
            $this->context(),
            $this->toolCall($forbiddenInput),
        );
        self::assertSame('access_denied', $toolDenied->errorCode);
        self::assertSame(0, AuthorizationHandler::$calls);

        $this->assertHttpError('resource_not_found', $this->validInput(owner: 'foreign-user'));
        self::assertSame(1, AuthorizationHandler::$calls);

        $this->assertHttpError('resource_not_found', $this->validInput(resourceId: self::ForeignTenantResource));
        self::assertSame(2, AuthorizationHandler::$calls);
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
        self::assertSame(2, AuthorizationHandler::$calls);
    }

    public function test_replay_rechecks_scope_actor_and_read_fields_without_invoking_handler(): void
    {
        $this->fields->deniedRead = ['/owner'];
        $authorizer = $this->app->make(ReplayAuthorizer::class);
        $outcome = StoredOperationOutcome::success([
            'id' => self::LocalResource,
            'title' => 'Stored',
            'owner' => 'trusted-user',
            'account' => 'account-a',
            'secret' => 'stored-secret',
        ]);
        $projected = $authorizer->authorize($this->invocation(), $outcome, true);

        self::assertSame([
            'id' => self::LocalResource,
            'title' => 'Stored',
            'account' => 'account-a',
        ], $projected->data);
        self::assertSame(0, AuthorizationHandler::$calls);

        $this->clientScopes->allow = false;
        $this->assertApiError('scope_required', fn () => $authorizer->authorize($this->invocation(), $outcome, true));
        $this->clientScopes->allow = true;
        $this->actors->allow = false;
        $this->assertApiError('access_denied', fn () => $authorizer->authorize($this->invocation(), $outcome, true));
        self::assertSame(0, AuthorizationHandler::$calls);
    }

    public function test_idempotent_authorization_denials_leave_no_terminal_store_row(): void
    {
        $this->app['config']->set('app.key', 'authorization-layers-secret');
        Schema::dropIfExists('sys_api_idempotency');
        $migration = require __DIR__.'/../../database/migrations/create_sys_api_idempotency_table.php';
        $migration->up();

        $this->clientScopes->allow = false;
        self::assertSame('scope_required', $this->dispatch($this->validInput(), 'scope-denied')->getData(true)['error']['code']);
        self::assertSame(0, DB::table('sys_api_idempotency')->count());

        $this->clientScopes->allow = true;
        self::assertSame('access_denied', $this->dispatch(
            [...$this->validInput(), 'secret' => 'must-not-be-stored'],
            'field-denied',
        )->getData(true)['error']['code']);
        self::assertSame(0, DB::table('sys_api_idempotency')->count());
        self::assertSame(0, AuthorizationHandler::$calls);
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
        self::assertSame($code, $this->dispatch($input)->getData(true)['error']['code']);
    }

    private function assertApiError(string $code, Closure $callback): void
    {
        try {
            $callback();
            self::fail("Expected API error {$code}.");
        } catch (ApiError $error) {
            self::assertSame($code, $error->errorCode);
        }
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
            expectedContractHash: ToolDescriptor::fromDeclaration($declaration)->contractHash,
            arguments: JsonObject::fromArray($input),
            requestId: 'tool-request',
        );
    }

    private function context(): ResolvedContext
    {
        return new ResolvedContext(
            $this->tenants->current(),
            $this->identity,
            new InvocationMetadata('request-1', 'v1', 'notes.update', 'POST'),
        );
    }

    private function invocation(): OperationInvocation
    {
        return new OperationInvocation(
            operation: $this->operation(),
            context: $this->context(),
            input: $this->validInput(),
            parameters: [],
            idempotencyKey: null,
        );
    }

    /** @param list<string>|null $requiredScopes */
    private function operation(?array $requiredScopes = null): Operation
    {
        $declaration = collect($this->modules->registry->api)->first(
            static fn (Api $api): bool => $api->mode === 'operation' && $api->operationId === 'notes.update',
        );
        $operation = Operation::fromDeclaration($declaration, $this->modules->compiled()->fingerprint);

        if ($requiredScopes === null) {
            return $operation;
        }

        return new Operation(
            module: $operation->module,
            id: $operation->id,
            version: $operation->version,
            method: $operation->method,
            path: $operation->path,
            ability: $operation->ability,
            handler: $operation->handler,
            requestSchema: $operation->requestSchema,
            responseSchema: $operation->responseSchema,
            inputRules: $operation->inputRules,
            successStatus: $operation->successStatus,
            mode: $operation->mode,
            requiredScopes: $requiredScopes,
            idempotency: $operation->idempotency,
            readFields: $operation->readFields,
            writeFields: $operation->writeFields,
            contractHash: $operation->contractHash,
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

final class ClientScopeFixture implements ClientScopeAuthorizer
{
    public bool $allow = true;

    public function allows(Operation $operation, RequestContext $context): bool
    {
        return $this->allow;
    }
}

final class ActorAuthorizerFixture implements OperationAuthorizer
{
    public bool $allow = true;

    public function allows(Operation $operation, RequestContext $context): bool
    {
        return $this->allow;
    }
}

final class FieldAuthorizerFixture implements OperationFieldAuthorizer
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

final class AuthorizationHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;
        $resource = ResourceId::parse((string) $input['resource_id'], IdStrategy::Uuid7, 'note');

        if ($resource->toString() === AuthorizationLayersTest::ForeignTenantResource
            || $input['owner'] !== $context->principal()->id()) {
            throw new ApiError('resource_not_found', 404, 'Resource not found.');
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
