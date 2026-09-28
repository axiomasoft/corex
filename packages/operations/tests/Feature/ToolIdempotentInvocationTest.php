<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use Closure;
use CoreX\Api\ApiError;
use CoreX\Api\ApiServiceProvider;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\ReplayAuthorizer;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ToolExecutionGuard;
use CoreX\Api\Contracts\ToolInvoker;
use CoreX\Api\Contracts\ToolPolicy;
use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Data\StoredOperationOutcome;
use CoreX\Api\Data\ToolCall;
use CoreX\Api\Data\ToolDescriptor;
use CoreX\Api\Data\ToolResult;
use CoreX\Api\Enums\IdempotencyRequirement;
use CoreX\Api\Enums\OperationMode;
use CoreX\Api\InvocationMetadata;
use CoreX\Api\Operation;
use CoreX\Api\OperationDispatcher;
use CoreX\Api\Testing\ResolvedContexts;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\Support\IdentityFixture;
use CoreX\Api\Tests\Support\ModuleFixture;
use CoreX\Api\Tests\Support\NoteHandler;
use CoreX\Api\ToolServiceProvider;
use CoreX\CoreServiceProvider;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
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
        $migration = require __DIR__.'/../../database/migrations/create_sys_api_idempotency_table.php';
        $migration->up();
        Schema::create('idempotent_effects', static function (Blueprint $table): void {
            $table->id();
            $table->string('value');
        });
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->principal = new IdentityFixture;
        $this->authorizer = new ToolIdempotentInvocationReplayAuthorizerFixture;
        $this->operationAuthorizer = new ToolIdempotentInvocationOperationAuthorizerFixture;
        $this->modules = new ModuleFixture(app(RegistryCompiler::class)->compile([], installedPackages: []));
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
        $registry = app(RegistryCompiler::class)->compile([
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
                expectedContractHash: ToolDescriptor::fromDeclaration($toolDeclaration)->contractHash,
                arguments: JsonObject::fromArray(['name' => 'alpha']),
                requestId: 'tool-request',
                operationKey: 'shared-key',
            ),
        );

        self::assertSame(200, $http->getStatusCode());
        self::assertSame(200, $httpReplay->getStatusCode());
        self::assertSame(ToolResult::OUTCOME_COMMITTED, $tool->outcome);
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
            input: $input,
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
            requestSchema: [],
            responseSchema: [],
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
            throw new ApiError('forbidden', 403, 'Operation is not permitted.');
        }

        if ($this->removeSecret && $outcome->kind === 'success') {
            $data = $outcome->data ?? [];
            unset($data['secret']);

            return StoredOperationOutcome::success($data);
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
