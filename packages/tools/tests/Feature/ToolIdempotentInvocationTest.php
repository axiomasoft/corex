<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Feature;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\CoreServiceProvider;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Cost\Contracts\BudgetExecution;
use CoreX\Operations\Cost\Contracts\OperationCostEstimator;
use CoreX\Operations\Cost\Data\BudgetAttempt;
use CoreX\Operations\Cost\Data\BudgetInvocationResult;
use CoreX\Operations\Cost\Data\BudgetReceipt;
use CoreX\Operations\Cost\Internal\BudgetAdmission;
use CoreX\Operations\Cost\Internal\SqlOperationBudget;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Laravel\ResolvedContexts;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Testing\NoteHandler;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Tests\Support\WorkScopeFixture;
use CoreX\Tools\Contracts\ToolExecutionGuard;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use CoreX\Tools\Mcp\Laravel\McpToolEndpoint;
use CoreX\Tools\ToolsServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Packman\Config\Data\SecretRef;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\ModulesServiceProvider;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Clock\ClockInterface;
use RuntimeException;

require_once dirname(__DIR__, 4).'/tests/Support/autoload.php';

final class ToolIdempotentInvocationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('corex-operations.routes.middleware', [TrustedTransportClient::class]);
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
        return [CoreServiceProvider::class, ModulesServiceProvider::class, OperationsServiceProvider::class, ToolsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = DB::connection('sqlite');
        $this->prepareTenantStorage();
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->principal = new IdentityFixture;
        $this->authorizer = new ToolIdempotentInvocationReplayAuthorizerFixture;
        $this->operationAuthorizer = new ToolIdempotentInvocationOperationAuthorizerFixture;
        $this->modules = new ModuleFixture(resolve(ModuleCompilation::class)->compile([], installedPackages: []));
    }

    private function prepareTenantStorage(): void
    {
        Schema::dropIfExists('sys_api_idempotency');
        Schema::dropIfExists('idempotent_effects');
        resolve(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-operations:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());
        Schema::create('idempotent_effects', static function (Blueprint $table): void {
            $table->id();
            $table->string('value');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('idempotent_effects');
        Schema::dropIfExists('sys_api_idempotency');
        parent::tearDown();
    }

    public function test_http_key_and_exposed_tool_operation_key_share_one_effect(): void
    {
        $guard = $this->mountTransport();
        $headers = ['Authorization' => 'Bearer test-transport-credential'];
        $missing = $this->postJson('/api/v2/notes', ['name' => 'alpha'], $headers);
        $missing->assertUnprocessable()->assertJsonPath('code', 'operations.idempotency_key_required');
        self::assertSame(0, ToolIdempotentInvocationIdempotentHttpHandler::$calls);
        self::assertSame(0, $this->connection->table('sys_api_idempotency')->count());

        $rpc = ['jsonrpc' => '2.0', 'id' => 1];
        $catalogue = $this->postJson('/mcp', [...$rpc, 'method' => 'tools/list'], $headers);
        $catalogue->assertOk()->assertJsonPath('result.tools.0.operationRef', 'v2:notes.create');
        $name = $catalogue->json('result.tools.0.name');
        self::assertIsString($name);

        $http = $this->postJson('/api/v2/notes', ['name' => 'alpha'], [...$headers, 'Idempotency-Key' => 'shared-key']);
        $http->assertOk()->assertJsonPath('meta.replayed', false);
        $this->authorizer->removeSecret = true;
        $call = [...$rpc, 'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => ['label' => 'alpha'], 'operationKey' => 'shared-key']];
        $replay = $this->postJson('/mcp', $call, $headers);
        $replay->assertOk()->assertJsonPath('result.outcome', 'completed')->assertJsonPath('result.replayed', true)
            ->assertJsonPath('result.structuredContent.name', 'alpha')->assertJsonMissingPath('result.structuredContent.secret');

        $spoof = $call;
        $spoof['params']['client_id'] = 'client-1';
        $this->postJson('/mcp', $spoof)->assertUnauthorized();
        $this->postJson('/mcp', $spoof, $headers)->assertOk()->assertJsonPath('error.code', -32602);

        $invalidName = str_repeat('x', 13);
        $invalid = $this->postJson('/api/v2/notes', ['name' => $invalidName], [...$headers, 'Idempotency-Key' => 'invalid'])
            ->assertUnprocessable()->assertJsonPath('path', '/name');
        $call['params']['arguments']['label'] = $invalidName;
        $this->postJson('/mcp', $call, $headers)->assertOk()->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.code', $invalid->json('code'))
            ->assertJsonPath('result.structuredContent.status', 422)
            ->assertJsonPath('result.structuredContent.path', $invalid->json('path'));
        $call['params']['arguments']['label'] = 'changed';
        $this->postJson('/mcp', $call, $headers)->assertOk()->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.code', 'operations.idempotency_conflict')
            ->assertJsonPath('result.structuredContent.status', 409);

        $call['params']['arguments']['label'] = 'alpha';
        $this->authorizer->deny = true;
        $guard->storedReplay = new ToolResult(
            outcome: ToolOutcome::Committed,
            structuredData: JsonObjectFactory::fromArray(['name' => 'alpha', 'secret' => 'protected']),
            errorCode: null,
            operationKey: 'shared-key',
            replayed: true,
        );
        foreach ([false, true] as $callBeforeReplay) {
            $guard->callBeforeReplay = $callBeforeReplay;
            $this->postJson('/mcp', $call, $headers)->assertOk()
                ->assertJsonPath('result.isError', true)
                ->assertJsonPath('result.structuredContent.code', ToolsErrorCode::InternalError->value)
                ->assertJsonMissingPath('result.structuredContent.secret');
        }
        $guard->storedReplay = null;
        $this->postJson('/mcp', $call, $headers)->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.code', OperationsErrorCode::Forbidden->value)
            ->assertJsonMissingPath('result.structuredContent.secret');
        self::assertSame(1, ToolIdempotentInvocationIdempotentHttpHandler::$calls);
        self::assertSame(1, $this->connection->table('idempotent_effects')->count());
        self::assertSame(1, $this->connection->table('sys_api_idempotency')->count());
    }

    public function test_exposed_tool_enforces_its_narrower_argument_schema_without_effects_or_receipts(): void
    {
        $this->mountTransport();
        $headers = ['Authorization' => 'Bearer test-transport-credential'];
        $call = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'notes_create__v1', 'arguments' => ['label' => 'beta']]];

        $this->postJson('/mcp', $call, $headers)->assertOk()->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.code', OperationsErrorCode::IdempotencyKeyRequired->value)
            ->assertJsonPath('result.structuredContent.status', 422);
        $call['params']['operationKey'] = 'tool-only-invalid';
        $this->postJson('/mcp', $call, $headers)->assertOk()->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.code', ToolsErrorCode::InputInvalid->value)
            ->assertJsonPath('result.structuredContent.status', 422);
        self::assertSame(0, ToolIdempotentInvocationIdempotentHttpHandler::$calls);
        self::assertSame(0, $this->connection->table('idempotent_effects')->count());
        self::assertSame(0, $this->connection->table('sys_api_idempotency')->count());

        $this->postJson('/api/v2/notes', ['name' => 'beta'], [...$headers, 'Idempotency-Key' => 'tool-only-invalid'])
            ->assertOk()->assertJsonPath('data.name', 'beta');
        $call['params']['arguments']['label'] = 'alpha';
        $call['params']['operationKey'] = 'valid-in-both';
        $this->postJson('/mcp', $call, $headers)->assertOk()->assertJsonPath('result.outcome', 'completed')
            ->assertJsonPath('result.structuredContent.name', 'alpha');
        self::assertSame(2, ToolIdempotentInvocationIdempotentHttpHandler::$calls);
        self::assertSame(2, $this->connection->table('sys_api_idempotency')->count());
    }

    #[TestWith([false], 'operation transaction')]
    #[TestWith([true], 'outer host transaction')]
    public function test_mcp_keeps_a_committed_effect_unknown_until_same_key_settlement_recovery(bool $outerTransaction): void
    {
        $guard = $this->mountTransport();
        $guard->connection = $outerTransaction ? $this->connection : null;
        $recovered = [];
        $budget = $this->createMock(BudgetExecution::class);
        $budget->expects(self::once())->method('invokeTransactional')->willReturnCallback(
            static fn (OperationInvocation $invocation, Closure $effect, BudgetAttempt $attempt): BudgetInvocationResult => new BudgetInvocationResult(
                result: $effect(static function (): void {}),
                receipt: new BudgetReceipt(reservationId: $attempt->reservationId, actualUnits: 2),
            ),
        );
        $budget->expects(self::exactly(2))->method('recover')->willReturnCallback(static function (BudgetReceipt $receipt) use (&$recovered): void {
            $recovered[] = $receipt;

            if (count($recovered) === 1) {
                throw new RuntimeException('Fixture lost the settlement acknowledgement after the tenant commit.');
            }
        });
        $this->app->instance(BudgetExecution::class, $budget);

        $headers = ['Authorization' => 'Bearer test-transport-credential'];
        $call = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'notes_create__v1', 'arguments' => ['label' => 'alpha'], 'operationKey' => 'settlement-key']];
        $this->postJson('/mcp', $call, $headers)->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.code', OperationsErrorCode::ReconciliationRequired->value)
            ->assertJsonPath('result.structuredContent.status', 409)
            ->assertJsonPath('result.structuredContent.retryable', false)
            ->assertJsonPath('result.outcome', 'unknown');
        self::assertSame(1, $this->connection->table('idempotent_effects')->count());
        self::assertSame('completed', $this->connection->table('sys_api_idempotency')->value('state'));

        $this->postJson('/mcp', $call, $headers)->assertOk()
            ->assertJsonPath('result.outcome', 'completed')->assertJsonPath('result.replayed', true)
            ->assertJsonPath('result.structuredContent.name', 'alpha');
        self::assertEquals($recovered[0], $recovered[1]);
        self::assertSame(1, ToolIdempotentInvocationIdempotentHttpHandler::$calls);
        self::assertSame(1, $this->connection->table('idempotent_effects')->count());
        self::assertSame(1, $this->connection->table('sys_api_idempotency')->count());
    }

    public function test_mcp_recovers_the_same_receipt_after_the_host_loses_native_commit_acknowledgement(): void
    {
        $pdo = new class('sqlite::memory:') extends PDO
        {
            public bool $failNextBegin = false;

            public bool $loseNextCommitAcknowledgement = false;

            public function exec(string $statement): int|false
            {
                if ($this->failNextBegin && str_starts_with($statement, 'BEGIN ')) {
                    $this->failNextBegin = false;

                    throw new PDOException('Fixture native BEGIN failed.');
                }

                return parent::exec($statement);
            }

            public function commit(): bool
            {
                $committed = parent::commit();

                if ($committed && $this->loseNextCommitAcknowledgement) {
                    $this->loseNextCommitAcknowledgement = false;

                    throw new PDOException('Fixture lost the native COMMIT acknowledgement.');
                }

                return $committed;
            }
        };
        $this->connection->setPdo($pdo);
        $this->prepareTenantStorage();
        $guard = $this->mountTransport();
        $guard->connection = $this->connection;
        resolve(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-operations:budget'], null);
        $budget = resolve(ApiSchemaConnections::class)->budget();
        $this->app->instance(BudgetExecution::class, new BudgetAdmission(
            budget: new SqlOperationBudget(
                connection: $budget,
                clock: resolve(ClockInterface::class),
                scopeHmacSecret: new SecretRef('fixture-commit-ack'),
            ),
            estimator: resolve(OperationCostEstimator::class),
            installationId: 'test-installation',
            exceptions: resolve(ExceptionHandler::class),
        ));
        $headers = ['Authorization' => 'Bearer test-transport-credential'];
        $call = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'notes_create__v1', 'arguments' => ['label' => 'alpha'], 'operationKey' => 'native-commit-key']];

        $pdo->failNextBegin = true;
        $this->postJson('/mcp', $call, $headers)->assertStatus(500)
            ->assertJsonPath('code', 'core.internal_error')
            ->assertDontSee('Fixture native BEGIN failed.');
        self::assertSame(0, ToolIdempotentInvocationIdempotentHttpHandler::$calls);
        self::assertSame(0, $this->connection->table('sys_api_idempotency')->count());
        self::assertSame(0, $budget->table('sys_api_budget_reservations')->count());

        $pdo->loseNextCommitAcknowledgement = true;
        $this->postJson('/mcp', $call, $headers)->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.structuredContent.code', OperationsErrorCode::ReconciliationRequired->value)
            ->assertJsonPath('result.structuredContent.status', 409)
            ->assertJsonPath('result.structuredContent.retryable', false)
            ->assertJsonPath('result.outcome', 'unknown')
            ->assertDontSee('Fixture lost the native COMMIT acknowledgement.');
        self::assertFalse($pdo->inTransaction());
        self::assertSame(1, $this->connection->table('idempotent_effects')->count());
        self::assertSame('completed', $this->connection->table('sys_api_idempotency')->value('state'));
        $hold = $budget->table('sys_api_budget_reservations')->sole();
        self::assertSame('held', $hold->state);

        $this->postJson('/mcp', $call, $headers)->assertOk()
            ->assertJsonPath('result.outcome', 'completed')->assertJsonPath('result.replayed', true)
            ->assertJsonPath('result.structuredContent.name', 'alpha');
        $settlement = $budget->table('sys_api_budget_reservations')->sole();
        self::assertSame($hold->id, $settlement->id);
        self::assertSame('settled', $settlement->state);
        self::assertSame($hold->estimate, $settlement->charged);
        self::assertSame(1, ToolIdempotentInvocationIdempotentHttpHandler::$calls);
        self::assertSame(1, $this->connection->table('idempotent_effects')->count());
        self::assertSame(1, $this->connection->table('sys_api_idempotency')->count());
    }

    private function mountTransport(): ToolIdempotentInvocationGuard
    {
        // Install the same declaring module that owns the HTTP and tool operation.
        $files = new Filesystem;
        $base = $this->app->basePath();
        $fixture = sys_get_temp_dir().'/corex-tools-notes-'.bin2hex(random_bytes(12));
        $files->makeDirectory($fixture.'/vendor/composer', 0700, true);
        $this->beforeApplicationDestroyed(function () use ($base, $files, $fixture): void {
            $this->app->setBasePath($base);
            $files->deleteDirectory($fixture);
        });
        $catalogue = json_decode($files->get(dirname(__DIR__, 4).'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
        $catalogue['packages'][] = ['name' => 'example/notes', 'version' => '1.0.0', 'type' => 'packman-module'];
        $files->put($fixture.'/vendor/composer/installed.json', json_encode($catalogue, JSON_THROW_ON_ERROR));
        $this->app->setBasePath($fixture);
        $schema = ['type' => 'object'];
        $toolInput = ['type' => 'object', 'additionalProperties' => false, 'required' => ['label'],
            'properties' => ['label' => ['type' => 'string', 'enum' => ['alpha', 'changed']]]];
        $registry = resolve(ModuleCompilation::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::operation('notes.create', 'POST', '/notes', ToolIdempotentInvocationIdempotentHttpHandler::class, 'notes.write', $schema, $schema, ['name' => ['required', 'string', 'max:12']], version: 'v2')
                    ->asCommand()->idempotency('required')->effect('tenant_transactional'),
                Api::exposeTool('notes.create', 'v2', 'notes.create', 'v1', 'write', 'domain-key', $toolInput, $schema, ['label' => 'name']),
            ]),
        ], installedPackages: []);
        $modules = new ModuleFixture($registry);
        $this->app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('s', 32)));
        $this->app->instance(ModuleRegistry::class, $modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        $this->app->instance(PrincipalResolver::class, $this->principal);
        $scope = WorkScopeFixture::install(
            app: $this->app,
            principalId: $this->principal->principalId(),
            principalKind: $this->principal->principalType()->value,
        );
        $scope->register(context: $this->tenants->current(), connection: $this->connection);
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
        $guard = new ToolIdempotentInvocationGuard;

        $this->app->instance(ToolExecutionGuard::class, $guard);
        ToolIdempotentInvocationIdempotentHttpHandler::$calls = 0;
        $this->app['router']->post('/mcp', McpToolEndpoint::class)->middleware(TrustedTransportClient::class);

        return $guard;
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
                requestMetadata: new InvocationMetadata($requestId, 'v2', 'notes.create', $method),
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
            version: 'v2',
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

final class ToolIdempotentInvocationGuard implements ToolExecutionGuard
{
    public ?Connection $connection = null;

    public ?ToolResult $storedReplay = null;

    public bool $callBeforeReplay = false;

    public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult
    {
        if ($this->storedReplay === null) {
            return $this->connection === null ? $effect() : $this->connection->transaction($effect);
        }

        if ($this->callBeforeReplay) {
            $effect();
        }

        return $this->storedReplay;
    }
}

final class TrustedTransportClient
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->bearerToken() === 'test-transport-credential') {
            $request->attributes->set('corex.client_id', 'client-1');
        }

        return $next($request);
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
