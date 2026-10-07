<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Feature;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\CoreServiceProvider;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Operations\ApiServiceProvider;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Internal\OperationDispatcher;
use CoreX\Operations\Invocation\Laravel\ResolvedContexts;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Testing\NoteHandler;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Internal\ToolDescriptorFactory;
use CoreX\Tools\ToolServiceProvider;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

final class ToolIdempotentInvocationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.api_schema_budget', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_schema_budget', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'sqlite', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    private Connection $connection;

    private ContextFixture $tenants;

    private IdentityFixture $principal;

    private ToolIdempotentInvocationReplayAuthorizerFixture $authorizer;

    private ToolIdempotentInvocationOperationAuthorizerFixture $operationAuthorizer;

    private ModuleFixture $modules;

    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, ApiServiceProvider::class, ToolServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = DB::connection('sqlite');
        Schema::dropIfExists('sys_api_idempotency');
        Schema::dropIfExists('idempotent_effects');
        resolve(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-operations:tenant'], resolve(ApiSchemaRegistrar::class)->tenant()->getName());
        Schema::create('idempotent_effects', static function (Blueprint $table): void {
            $table->id();
            $table->string('value');
        });
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->principal = new IdentityFixture;
        $this->authorizer = new ToolIdempotentInvocationReplayAuthorizerFixture;
        $this->operationAuthorizer = new ToolIdempotentInvocationOperationAuthorizerFixture;
        $this->modules = new ModuleFixture(resolve(RegistryCompiler::class)->compile([], installedPackages: []));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('idempotent_effects');
        Schema::dropIfExists('sys_api_idempotency');
        parent::tearDown();
    }

    public function test_http_key_and_exposed_tool_operation_key_share_one_effect(): void
    {
        $schema = ['type' => 'object'];
        $registry = resolve(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::operation('notes.create', 'POST', '/notes', ToolIdempotentInvocationIdempotentHttpHandler::class, 'notes.write', $schema, $schema, ['name' => ['required', 'string']])
                    ->asCommand()->idempotency('required')->effect('tenant_transactional'),
                Api::exposeTool('notes.create', 'v1', 'notes.create', 'v1', 'write', 'domain-key', $schema, $schema, ['name' => 'name']),
            ]),
        ], installedPackages: []);
        $modules = new ModuleFixture($registry);
        $this->app['config']->set('app.key', 'provider-idempotency-secret');
        $this->app->instance(ModuleRegistry::class, $modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        $this->app->instance(PrincipalResolver::class, $this->principal);
        $this->app->instance(ReplayAuthorizer::class, $this->authorizer);
        $this->app->instance(OperationAuthorizer::class, new class implements OperationAuthorizer
        {
            public function allows(Operation $operation, RequestContext $context): bool
            {
                return true;
            }
        });
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
        ToolIdempotentInvocationIdempotentHttpHandler::$calls = 0;
        $request = static fn (?string $key): Request => Request::create(
            '/api/v1/notes',
            'POST',
            server: array_filter(['CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $key]),
            content: '{"name":"alpha"}',
        );
        $dispatcher = $this->app->make(OperationDispatcher::class);
        $missing = $dispatcher->dispatch($request(null), 'v1', 'notes');
        self::assertSame(422, $missing->getStatusCode());
        self::assertSame(0, ToolIdempotentInvocationIdempotentHttpHandler::$calls);
        self::assertSame(0, $this->connection->table('sys_api_idempotency')->count());

        $http = $dispatcher->dispatch($request('shared-key'), 'v1', 'notes');
        $httpReplay = $dispatcher->dispatch($request('shared-key'), 'v1', 'notes');
        $this->authorizer->removeSecret = true;
        $toolDeclaration = collect($registry->api)->first(
            static fn (Api $api): bool => $api->mode === 'tool' && $api->toolCode === 'notes.create',
        );
        self::assertInstanceOf(Api::class, $toolDeclaration);
        $tool = $this->app->make(ToolInvoker::class)->invoke(
            $this->invocation()->context,
            new ToolCall(
                code: 'notes.create',
                toolVersion: 'v1',
                expectedContractHash: ToolDescriptorFactory::fromDeclaration($toolDeclaration)->contractHash,
                arguments: JsonObjectFactory::fromArray(['name' => 'alpha']),
                requestId: 'tool-request',
                operationKey: 'shared-key',
            ),
        );

        self::assertSame(200, $http->getStatusCode());
        self::assertSame(200, $httpReplay->getStatusCode());
        self::assertSame(ToolOutcome::Committed, $tool->outcome);
        self::assertTrue($tool->replayed);
        self::assertSame(['name' => 'alpha'], json_decode((string) $tool->structuredData?->canonicalJson, true, flags: JSON_THROW_ON_ERROR));
        self::assertSame(1, ToolIdempotentInvocationIdempotentHttpHandler::$calls);
        self::assertSame(1, $this->connection->table('idempotent_effects')->count());
    }

    private function invocation(
        array $input = ['name' => 'alpha'],
        ?string $key = 'operation-key',
        ?Operation $operation = null,
        string $requestId = 'request-1',
        string $method = 'POST',
    ): OperationInvocation {
        return new OperationInvocation(
            operation: $operation ?? $this->operation(),
            context: ResolvedContexts::make(
                tenantContext: $this->tenants->current(),
                authenticatedPrincipal: $this->principal,
                requestMetadata: new InvocationMetadata($requestId, 'v1', 'notes.create', $method),
            ),
            input: JsonDocuments::capture($input),
            parameters: ['parent' => 'root'],
            idempotencyKey: $key,
        );
    }

    private function operation(
        IdempotencyRequirement $idempotency = IdempotencyRequirement::Required,
        ?string $effectMode = 'tenant_transactional',
        ?string $contractHash = null,
    ): Operation {
        return new Operation(
            module: 'example/notes',
            id: 'notes.create',
            version: 'v1',
            method: 'POST',
            path: '/notes',
            ability: 'notes.write',
            handler: NoteHandler::class,
            requestSchema: JsonDocuments::capture([]),
            responseSchema: JsonDocuments::capture([]),
            inputRules: [],
            successStatus: 200,
            mode: OperationMode::Command,
            idempotency: $idempotency,
            contractHash: $contractHash ?? hash('sha256', 'notes.create/v1'),
            effectMode: $effectMode,
        );
    }
}

final class ToolIdempotentInvocationIdempotentHttpHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;
        DB::table('idempotent_effects')->insert(['value' => (string) $input['name']]);

        return ['name' => $input['name'], 'secret' => 'protected'];
    }
}

final class ToolIdempotentInvocationReplayAuthorizerFixture implements ReplayAuthorizer
{
    public bool $deny = false;

    public bool $removeSecret = false;

    public function authorize(
        OperationInvocation $invocation,
        StoredOperationOutcome $outcome,
        bool $replayed,
    ): StoredOperationOutcome {
        if ($this->deny) {
            throw new ApiError(OperationsErrorCode::Forbidden, 403, 'Operation is not permitted.');
        }

        if ($this->removeSecret && $outcome->kind === 'success') {
            $data = JsonDocuments::optionalArray($outcome->data) ?? [];
            unset($data['secret']);

            return StoredOperationOutcome::success(JsonDocuments::capture($data));
        }

        return $outcome;
    }
}

final class ToolIdempotentInvocationOperationAuthorizerFixture implements OperationAuthorizer
{
    public bool $allow = true;

    public function allows(Operation $operation, RequestContext $context): bool
    {
        return $this->allow;
    }
}
