<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use Closure;
use CoreX\Config\Data\SecretRef;
use CoreX\Context\Data\AccountRef;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Definition\Laravel\OperationFingerprint;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Errors\Internal\ErrorMapper;
use CoreX\Operations\Idempotency\Contracts\IdempotencyStore;
use CoreX\Operations\Idempotency\Data\IdempotencyAttempt;
use CoreX\Operations\Idempotency\Data\IdempotencyScope;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Idempotency\Internal\IdempotentOperationInvoker;
use CoreX\Operations\Idempotency\Internal\SqlIdempotencyStore;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Data\OperationResult;
use CoreX\Operations\Invocation\Laravel\ResolvedContexts;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Testing\NoteHandler;
use CoreX\Operations\Tests\TestCase;
use CoreX\Time\Testing\FrozenClock;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteConnection;
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
        return [...parent::getPackageProviders($app)];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = DB::connection('sqlite');
        Schema::dropIfExists('sys_api_idempotency');
        Schema::dropIfExists('idempotent_effects');

        $this->migrateSchemaSets(['axiomasoft/corex-operations:tenant'], resolve(ApiSchemaRegistrar::class)->tenant()->getName());
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
        $this->modules = new ModuleFixture(resolve(RegistryCompiler::class)->compile([], installedPackages: []));
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
        self::assertSame(['name' => 'alpha'], JsonDocuments::array($replay->data));
        self::assertSame(1, $this->inner->calls);
        self::assertSame(1, $this->connection->table('idempotent_effects')->count());

        $this->authorizer->deny = true;

        try {
            $invoker->invoke($tool);
            self::fail('Current authorization denial did not override the stored success.');
        } catch (ApiError $error) {
            self::assertSame('operations.forbidden', $error->errorCode);
        }

        self::assertSame(1, $this->inner->calls);
    }

    public function test_required_forbidden_conflict_in_progress_and_contract_mismatch_skip_effect(): void
    {
        $invoker = $this->invoker();

        $this->expectApiError('operations.idempotency_key_required', fn () => $invoker->invoke($this->invocation(key: null)));
        $this->expectApiError('operations.idempotency_key_forbidden', fn () => $invoker->invoke($this->invocation(
            operation: $this->operation(idempotency: IdempotencyRequirement::Forbidden, effectMode: null),
        )));

        $invoker->invoke($this->invocation(input: ['name' => 'alpha']));
        $this->expectApiError('operations.idempotency_conflict', fn () => $invoker->invoke($this->invocation(input: ['name' => 'beta'])));

        $changedContract = $this->operation(contractHash: hash('sha256', 'changed-contract'));
        $this->expectApiError('operations.idempotency_contract_mismatch', fn () => $invoker->invoke($this->invocation(operation: $changedContract, input: ['name' => 'alpha'])));

        $pending = $this->invocation(key: 'pending-key');
        $this->connection->transaction(fn (): IdempotencyAttempt => $this->store->begin(
            OperationFingerprint::scope($pending, $this->connection),
            'pending-key',
            OperationFingerprint::request($pending),
            $pending->operation->contractHash,
        ));
        $this->expectApiError('operations.idempotency_in_progress', fn () => $invoker->invoke($pending));

        self::assertSame(1, $this->inner->calls);
    }

    public function test_auth_validation_and_mapped_internal_failures_have_distinct_persistence(): void
    {
        $store = new CountingStore($this->store);
        $invoker = $this->invoker($store);
        $this->operationAuthorizer->allow = false;
        $this->expectApiError('operations.forbidden', fn () => $invoker->invoke($this->invocation(key: 'auth-key')));
        self::assertSame(0, $store->beginCalls);
        self::assertSame(0, $this->inner->calls);
        self::assertSame(0, $this->connection->table('sys_api_idempotency')->count());

        $this->operationAuthorizer->allow = true;
        $this->inner->mode = 'internal';
        $this->expectApiError('core.internal_error', fn () => $invoker->invoke($this->invocation(key: 'failure-key')));
        self::assertSame('failed', $this->connection->table('sys_api_idempotency')->value('state'));

        $this->expectApiError('core.internal_error', fn () => $invoker->invoke($this->invocation(key: 'failure-key')));
        self::assertSame(1, $this->inner->calls);
    }

    public function test_terminal_store_failure_rolls_back_effect_and_retry_commits_once(): void
    {
        $store = new FailingCompleteStore($this->store);
        $invoker = $this->invoker($store);

        $this->expectApiError('core.internal_error', fn () => $invoker->invoke($this->invocation(key: 'rollback-key')));
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

        $this->expectApiError('operations.reconciliation_required', fn () => $invoker->invoke($this->invocation(key: 'ambiguous-key')));
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
            errors: resolve(ErrorMapper::class),
            problems: resolve(ProblemRegistry::class),
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
            throw new ApiError(OperationsErrorCode::Forbidden, 403, 'Operation is not permitted.');
        }

        if ($this->mode === 'internal') {
            throw new RuntimeException('SQLSTATE secret must not escape');
        }

        $this->connection->table('idempotent_effects')->insert(['value' => (string) JsonDocuments::map($invocation->input)['name']]);

        return new OperationResult(data: JsonDocuments::capture(['name' => JsonDocuments::map($invocation->input)['name'], 'secret' => 'protected']));
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
