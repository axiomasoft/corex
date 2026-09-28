<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use Closure;
use CoreX\Api\ApiError;
use CoreX\Api\Contracts\IdempotencyStore;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\OperationInvoker;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\ReplayAuthorizer;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ToolExecutionGuard;
use CoreX\Api\Contracts\ToolInvoker;
use CoreX\Api\Contracts\ToolPolicy;
use CoreX\Api\Data\IdempotencyAttempt;
use CoreX\Api\Data\IdempotencyScope;
use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Data\OperationResult;
use CoreX\Api\Data\StoredOperationOutcome;
use CoreX\Api\Data\ToolCall;
use CoreX\Api\Data\ToolDescriptor;
use CoreX\Api\Data\ToolResult;
use CoreX\Api\Enums\IdempotencyRequirement;
use CoreX\Api\Enums\OperationMode;
use CoreX\Api\Internal\ErrorMapper;
use CoreX\Api\Internal\IdempotentOperationInvoker;
use CoreX\Api\Internal\OperationFingerprint;
use CoreX\Api\Internal\ResolvedContext;
use CoreX\Api\Internal\SqlIdempotencyStore;
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
use CoreX\Support\SecretRef;
use CoreX\Support\Time\FrozenClock;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;

final class IdempotentInvocationTest extends TestCase
{
    private Connection $connection;

    private ContextFixture $tenants;

    private IdentityFixture $principal;

    private SqlIdempotencyStore $store;

    private EffectInvokerFixture $inner;

    private ReplayAuthorizerFixture $authorizer;

    private OperationAuthorizerFixture $operationAuthorizer;

    private ModuleFixture $modules;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ToolServiceProvider::class];
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
        $this->store = $this->makeStore($this->connection);
        $this->inner = new EffectInvokerFixture($this->connection);
        $this->authorizer = new ReplayAuthorizerFixture;
        $this->operationAuthorizer = new OperationAuthorizerFixture;
        $this->modules = new ModuleFixture(app(RegistryCompiler::class)->compile([], installedPackages: []));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('idempotent_effects');
        Schema::dropIfExists('sys_api_idempotency');
        parent::tearDown();
    }

    public function test_same_key_replays_across_transports_and_rechecks_current_authority(): void
    {
        $invoker = $this->invoker();
        $http = $this->invocation(input: ['name' => 'alpha'], requestId: 'http-1', method: 'POST');
        $tool = $this->invocation(input: ['name' => 'alpha'], requestId: 'tool-1', method: 'TOOL');

        $fresh = $invoker->invoke($http);
        $this->authorizer->removeSecret = true;
        $replay = $invoker->invoke($tool);

        self::assertFalse($fresh->replayed);
        self::assertTrue($replay->replayed);
        self::assertSame(['name' => 'alpha'], $replay->data);
        self::assertSame(1, $this->inner->calls);
        self::assertSame(1, $this->connection->table('idempotent_effects')->count());

        $this->authorizer->deny = true;

        try {
            $invoker->invoke($tool);
            self::fail('Current authorization denial did not override the stored success.');
        } catch (ApiError $error) {
            self::assertSame('forbidden', $error->errorCode);
        }

        self::assertSame(1, $this->inner->calls);
    }

    public function test_http_key_and_exposed_tool_operation_key_share_one_effect(): void
    {
        $schema = ['type' => 'object'];
        $registry = app(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::operation('notes.create', 'POST', '/notes', IdempotentHttpHandler::class, 'notes.write', $schema, $schema, ['name' => ['required', 'string']])
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
        IdempotentHttpHandler::$calls = 0;
        $request = static fn (?string $key): Request => Request::create(
            '/api/v1/notes',
            'POST',
            server: array_filter(['CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $key]),
            content: '{"name":"alpha"}',
        );
        $dispatcher = $this->app->make(OperationDispatcher::class);
        $missing = $dispatcher->dispatch($request(null), 'v1', 'notes');
        self::assertSame(422, $missing->getStatusCode());
        self::assertSame(0, IdempotentHttpHandler::$calls);
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
        self::assertSame(1, IdempotentHttpHandler::$calls);
        self::assertSame(1, $this->connection->table('idempotent_effects')->count());
    }

    public function test_required_forbidden_conflict_in_progress_and_contract_mismatch_skip_effect(): void
    {
        $invoker = $this->invoker();

        $this->expectApiError('idempotency_key_required', fn () => $invoker->invoke($this->invocation(key: null)));
        $this->expectApiError('idempotency_key_forbidden', fn () => $invoker->invoke($this->invocation(
            operation: $this->operation(idempotency: IdempotencyRequirement::Forbidden, effectMode: null),
        )));

        $invoker->invoke($this->invocation(input: ['name' => 'alpha']));
        $this->expectApiError('idempotency_conflict', fn () => $invoker->invoke($this->invocation(input: ['name' => 'beta'])));

        $changedContract = $this->operation(contractHash: hash('sha256', 'changed-contract'));
        $this->expectApiError('idempotency_contract_mismatch', fn () => $invoker->invoke($this->invocation(operation: $changedContract, input: ['name' => 'alpha'])));

        $pending = $this->invocation(key: 'pending-key');
        $this->connection->transaction(fn (): IdempotencyAttempt => $this->store->begin(
            OperationFingerprint::scope($pending, $this->connection),
            'pending-key',
            OperationFingerprint::request($pending),
            $pending->operation->contractHash,
        ));
        $this->expectApiError('idempotency_in_progress', fn () => $invoker->invoke($pending));

        self::assertSame(1, $this->inner->calls);
    }

    public function test_auth_validation_and_mapped_internal_failures_have_distinct_persistence(): void
    {
        $store = new CountingStore($this->store);
        $invoker = $this->invoker($store);
        $this->operationAuthorizer->allow = false;
        $this->expectApiError('forbidden', fn () => $invoker->invoke($this->invocation(key: 'auth-key')));
        self::assertSame(0, $store->beginCalls);
        self::assertSame(0, $this->inner->calls);
        self::assertSame(0, $this->connection->table('sys_api_idempotency')->count());

        $this->operationAuthorizer->allow = true;
        $this->inner->mode = 'internal';
        $this->expectApiError('internal_error', fn () => $invoker->invoke($this->invocation(key: 'failure-key')));
        self::assertSame('failed', $this->connection->table('sys_api_idempotency')->value('state'));

        $this->expectApiError('internal_error', fn () => $invoker->invoke($this->invocation(key: 'failure-key')));
        self::assertSame(1, $this->inner->calls);
    }

    public function test_terminal_store_failure_rolls_back_effect_and_retry_commits_once(): void
    {
        $store = new FailingCompleteStore($this->store);
        $invoker = $this->invoker($store);

        $this->expectApiError('internal_error', fn () => $invoker->invoke($this->invocation(key: 'rollback-key')));
        self::assertSame(0, $this->connection->table('idempotent_effects')->count());
        self::assertSame(0, $this->connection->table('sys_api_idempotency')->count());

        $result = $invoker->invoke($this->invocation(key: 'rollback-key'));
        self::assertFalse($result->replayed);
        self::assertSame(1, $this->connection->table('idempotent_effects')->count());
        self::assertSame(2, $this->inner->calls);
    }

    public function test_ambiguous_commit_requires_reconciliation_without_second_effect(): void
    {
        $connection = new AmbiguousCommitConnection(new PDO('sqlite::memory:'), ':memory:', '', ['name' => 'ambiguous']);
        $this->createTables($connection);
        $store = $this->makeStore($connection);
        $inner = new EffectInvokerFixture($connection);
        $invoker = $this->invoker($store, $connection, $inner);
        $connection->failAfterCommit = true;

        $this->expectApiError('reconciliation_required', fn () => $invoker->invoke($this->invocation(key: 'ambiguous-key')));
        self::assertSame(1, $connection->table('idempotent_effects')->count());

        $replay = $invoker->invoke($this->invocation(key: 'ambiguous-key'));
        self::assertTrue($replay->replayed);
        self::assertSame(1, $inner->calls);
    }

    private function invoker(
        ?IdempotencyStore $store = null,
        ?Connection $connection = null,
        ?EffectInvokerFixture $inner = null,
    ): IdempotentOperationInvoker {
        $store ??= $this->store;

        return new IdempotentOperationInvoker(
            inner: $inner ?? $this->inner,
            store: static fn (): IdempotencyStore => $store,
            connection: $connection ?? $this->connection,
            modules: $this->modules,
            authorizer: $this->operationAuthorizer,
            replayAuthorizer: $this->authorizer,
            errors: new ErrorMapper,
        );
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
            context: new ResolvedContext(
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

    private function makeStore(Connection $connection): SqlIdempotencyStore
    {
        return new SqlIdempotencyStore(
            connection: $connection,
            contexts: $this->tenants,
            clock: new FrozenClock(new DateTimeImmutable('2026-09-25T00:00:00+00:00')),
            keyHmacSecret: new SecretRef('idempotency-test-secret'),
            leaseSeconds: 30,
            retentionSeconds: 300,
        );
    }

    private function expectApiError(string $code, Closure $callback): void
    {
        try {
            $callback();
            self::fail("Expected API error {$code}.");
        } catch (ApiError $error) {
            self::assertSame($code, $error->errorCode);
        }
    }

    private function createTables(Connection $connection): void
    {
        $connection->statement('CREATE TABLE sys_api_idempotency (scope_hash VARCHAR(64) NOT NULL, key_hash VARCHAR(64) NOT NULL, request_fingerprint VARCHAR(64) NOT NULL, state VARCHAR(16) NOT NULL, attempt INTEGER NOT NULL, lease_token VARCHAR(64), lease_until DATETIME, outcome_json TEXT, contract_hash VARCHAR(64) NOT NULL, expires_at DATETIME, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE(scope_hash, key_hash))');
        $connection->statement('CREATE TABLE idempotent_effects (id INTEGER PRIMARY KEY AUTOINCREMENT, value VARCHAR(255) NOT NULL)');
    }
}

final class EffectInvokerFixture implements OperationInvoker
{
    public int $calls = 0;

    public string $mode = 'success';

    public function __construct(private readonly Connection $connection) {}

    public function invoke(OperationInvocation $invocation): OperationResult
    {
        $this->calls++;

        if ($this->mode === 'forbidden') {
            throw new ApiError('forbidden', 403, 'Operation is not permitted.');
        }

        if ($this->mode === 'internal') {
            throw new RuntimeException('SQLSTATE secret must not escape');
        }

        $this->connection->table('idempotent_effects')->insert(['value' => (string) $invocation->input['name']]);

        return new OperationResult(data: ['name' => $invocation->input['name'], 'secret' => 'protected']);
    }
}

final class IdempotentHttpHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;
        DB::table('idempotent_effects')->insert(['value' => (string) $input['name']]);

        return ['name' => $input['name'], 'secret' => 'protected'];
    }
}

final class ReplayAuthorizerFixture implements ReplayAuthorizer
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

final class OperationAuthorizerFixture implements OperationAuthorizer
{
    public bool $allow = true;

    public function allows(Operation $operation, RequestContext $context): bool
    {
        return $this->allow;
    }
}

final class CountingStore implements IdempotencyStore
{
    public int $beginCalls = 0;

    public function __construct(private readonly IdempotencyStore $inner) {}

    public function begin(IdempotencyScope $scope, string $key, string $requestFingerprint, string $contractHash): IdempotencyAttempt
    {
        $this->beginCalls++;

        return $this->inner->begin($scope, $key, $requestFingerprint, $contractHash);
    }

    public function takeover(IdempotencyAttempt $attempt): IdempotencyAttempt
    {
        return $this->inner->takeover($attempt);
    }

    public function complete(IdempotencyAttempt $attempt, string $outcomeJson): void
    {
        $this->inner->complete($attempt, $outcomeJson);
    }

    public function fail(IdempotencyAttempt $attempt, string $outcomeJson): void
    {
        $this->inner->fail($attempt, $outcomeJson);
    }

    public function prune(): int
    {
        return $this->inner->prune();
    }
}

final class FailingCompleteStore implements IdempotencyStore
{
    private bool $fail = true;

    public function __construct(private readonly IdempotencyStore $inner) {}

    public function begin(IdempotencyScope $scope, string $key, string $requestFingerprint, string $contractHash): IdempotencyAttempt
    {
        return $this->inner->begin($scope, $key, $requestFingerprint, $contractHash);
    }

    public function takeover(IdempotencyAttempt $attempt): IdempotencyAttempt
    {
        return $this->inner->takeover($attempt);
    }

    public function complete(IdempotencyAttempt $attempt, string $outcomeJson): void
    {
        if ($this->fail) {
            $this->fail = false;

            throw new RuntimeException('terminal store failure');
        }

        $this->inner->complete($attempt, $outcomeJson);
    }

    public function fail(IdempotencyAttempt $attempt, string $outcomeJson): void
    {
        $this->inner->fail($attempt, $outcomeJson);
    }

    public function prune(): int
    {
        return $this->inner->prune();
    }
}

final class AmbiguousCommitConnection extends SQLiteConnection
{
    public bool $failAfterCommit = false;

    public function transaction(Closure $callback, $attempts = 1)
    {
        $result = parent::transaction($callback, $attempts);

        if ($this->failAfterCommit) {
            $this->failAfterCommit = false;

            throw new RuntimeException('connection lost after commit acknowledgement');
        }

        return $result;
    }
}
