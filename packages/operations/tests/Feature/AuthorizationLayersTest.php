<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Authorization\Internal\DenyClientScopeAuthorizer;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Data\ResourceId;
use CoreX\Operations\Definition\Enums\ResourceIdFormat;
use CoreX\Operations\Definition\Laravel\OperationFactory;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Internal\OperationDispatcher;
use CoreX\Operations\Invocation\Laravel\ResolvedContexts;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Tests\TestCase;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
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
        return [...parent::getPackageProviders($app)];
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
        $registry = app(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([$operation]),
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
        $this->app->instance(PrincipalResolver::class, $this->identity);
        AuthorizationHandler::$calls = 0;
    }

    public function test_resource_ids_round_trip_all_supported_formats_and_reject_mismatch(): void
    {
        $vectors = [
            [ResourceIdFormat::Uuid7, '01890f3e-7b2a-7cc0-98c4-dc0c0c07398f', self::LocalResource],
            [ResourceIdFormat::Uuid, '550e8400-e29b-41d4-a716-446655440000', 'note:uuid:550e8400-e29b-41d4-a716-446655440000'],
            [ResourceIdFormat::Ulid, '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'note:ulid:01ARZ3NDEKTSV4RRFFQ69G5FAV'],
            [ResourceIdFormat::Integer, 42, 'note:integer:42'],
        ];

        foreach ($vectors as [$format, $value, $encoded]) {
            $resourceId = ResourceId::fromValue('note', $value, $format);

            self::assertSame($format, $resourceId->format);
            self::assertSame($encoded, $resourceId->toString());
            self::assertSame($encoded, ResourceId::parse($encoded, $format, 'note')->toString());
        }

        self::assertSame(self::LocalResource, ResourceId::fromValue(
            'note',
            '01890f3e-7b2a-7cc0-98c4-dc0c0c07398f',
            ResourceIdFormat::Uuid7,
        )->toString());
        self::assertSame(self::LocalResource, ResourceId::parse(
            self::LocalResource,
            ResourceIdFormat::Uuid,
        )->toString());

        foreach ([
            fn () => ResourceId::parse(self::LocalResource, ResourceIdFormat::Ulid),
            fn () => ResourceId::parse('note:uuid:550e8400-e29b-41d4-a716-446655440000', ResourceIdFormat::Uuid7),
            fn () => ResourceId::parse(self::LocalResource, ResourceIdFormat::Uuid7, 'contact'),
            fn () => ResourceId::fromValue('note', '01arz3ndektsv4rrffq69g5fav', ResourceIdFormat::Ulid),
            fn () => ResourceId::fromValue('note', '042', ResourceIdFormat::Integer),
            fn () => ResourceId::fromValue('Note', 42, ResourceIdFormat::Integer),
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

    public function test_replay_rechecks_scope_actor_and_read_fields_without_invoking_handler(): void
    {
        $this->fields->deniedRead = ['/owner'];
        $authorizer = $this->app->make(ReplayAuthorizer::class);
        $outcome = StoredOperationOutcome::success(JsonDocuments::capture([
            'id' => self::LocalResource,
            'title' => 'Stored',
            'owner' => 'trusted-user',
            'account' => 'account-a',
            'secret' => 'stored-secret',
        ]));
        $projected = $authorizer->authorize($this->invocation(), $outcome, true);

        self::assertSame([
            'id' => self::LocalResource,
            'title' => 'Stored',
            'account' => 'account-a',
        ], JsonDocuments::optionalArray($projected->data));
        self::assertSame(0, AuthorizationHandler::$calls);

        $this->clientScopes->allow = false;
        $this->assertApiError('operations.scope_required', fn () => $authorizer->authorize($this->invocation(), $outcome, true));
        $this->clientScopes->allow = true;
        $this->actors->allow = false;
        $this->assertApiError('operations.access_denied', fn () => $authorizer->authorize($this->invocation(), $outcome, true));
        self::assertSame(0, AuthorizationHandler::$calls);
    }

    public function test_idempotent_authorization_denials_leave_no_terminal_store_row(): void
    {
        $this->app['config']->set('app.key', 'authorization-layers-secret');
        Schema::dropIfExists('sys_api_idempotency');

        $this->migrateSchemaSets(['axiomasoft/corex-operations:tenant'], app(ApiSchemaRegistrar::class)->tenant()->getName());

        $this->clientScopes->allow = false;
        self::assertSame('operations.scope_required', $this->dispatch($this->validInput(), 'scope-denied')->getData(true)['code']);
        self::assertSame(0, DB::table('sys_api_idempotency')->count());

        $this->clientScopes->allow = true;
        self::assertSame('operations.access_denied', $this->dispatch(
            [...$this->validInput(), 'secret' => 'must-not-be-stored'],
            'field-denied',
        )->getData(true)['code']);
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

    private function context(): RequestContext
    {
        return ResolvedContexts::make(
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
            input: JsonDocuments::capture($this->validInput()),
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
        $operation = OperationFactory::fromDeclaration($declaration, $this->modules->compiled()->fingerprint);

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
            requestSchema: JsonDocuments::capture(JsonDocuments::map($operation->requestSchema)),
            responseSchema: JsonDocuments::capture(JsonDocuments::map($operation->responseSchema)),
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
        $resource = ResourceId::parse((string) $input['resource_id'], ResourceIdFormat::Uuid7, 'note');

        if ($resource->toString() === AuthorizationLayersTest::ForeignTenantResource
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
