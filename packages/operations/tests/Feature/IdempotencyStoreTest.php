<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use CoreX\Api\ApiServiceProvider;
use CoreX\Api\Contracts\IdempotencyStore;
use CoreX\Api\Data\IdempotencyAttempt;
use CoreX\Api\Data\IdempotencyScope;
use CoreX\Api\Internal\JsonCanonical;
use CoreX\Api\Internal\SqlIdempotencyStore;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\TestCase;
use CoreX\Support\SecretRef;
use CoreX\Support\Time\FrozenClock;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Throwable;

final class IdempotencyStoreTest extends TestCase
{
    private const string RawKey = 'customer-visible-key';

    private const string Secret = 'test-hmac-secret';

    private Migration $migration;

    private Connection $connection;

    private ContextFixture $contexts;

    private FrozenClock $clock;

    private SqlIdempotencyStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = DB::connection('sqlite');
        Schema::connection('sqlite')->dropIfExists('sys_api_idempotency');
        $this->migration = require __DIR__.'/../../database/migrations/create_sys_api_idempotency_table.php';
        $this->migration->up();
        Schema::connection('sqlite')->create('idempotency_effects', static function (Blueprint $table): void {
            $table->string('value')->primary();
        });
        $this->contexts = $this->contexts();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00+00:00'));
        $this->store = $this->store($this->connection, $this->contexts, $this->clock);
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropIfExists('idempotency_effects');
        Schema::connection('sqlite')->dropIfExists('sys_api_idempotency');
        parent::tearDown();
    }

    public function test_fresh_attempt_completes_and_replays_canonical_outcome_without_storing_raw_key(): void
    {
        $fingerprint = $this->digest('request-a');
        $contract = $this->digest('contract-a');
        $outcome = JsonCanonical::encode(['data' => ['id' => 'note-1'], 'status' => 'ok']);

        $owner = $this->connection->transaction(function () use ($fingerprint, $contract, $outcome): IdempotencyAttempt {
            $attempt = $this->store->begin($this->scope(), self::RawKey, $fingerprint, $contract);
            $this->store->complete($attempt, $outcome);

            return $attempt;
        });
        $replay = $this->connection->transaction(
            fn (): IdempotencyAttempt => $this->store->begin($this->scope(), self::RawKey, $fingerprint, $contract),
        );
        $row = $this->connection->table('sys_api_idempotency')->first();

        self::assertSame(IdempotencyAttempt::Owner, $owner->disposition);
        self::assertSame(IdempotencyAttempt::Replay, $replay->disposition);
        self::assertSame('completed', $replay->state);
        self::assertSame($outcome, $replay->outcomeJson);
        self::assertSame(hash_hmac('sha256', self::RawKey, self::Secret), $row->key_hash);
        self::assertStringNotContainsString(self::RawKey, json_encode((array) $row, JSON_THROW_ON_ERROR));
    }

    public function test_conflict_contract_mismatch_takeover_fence_and_retention_matrix(): void
    {
        $fingerprint = $this->digest('request-a');
        $contract = $this->digest('contract-a');
        $first = $this->connection->transaction(
            fn (): IdempotencyAttempt => $this->store->begin($this->scope(), self::RawKey, $fingerprint, $contract),
        );

        $live = $this->connection->transaction(
            fn (): IdempotencyAttempt => $this->store->begin($this->scope(), self::RawKey, $fingerprint, $contract),
        );
        $conflict = $this->connection->transaction(
            fn (): IdempotencyAttempt => $this->store->begin($this->scope(), self::RawKey, $this->digest('request-b'), $contract),
        );
        $mismatch = $this->connection->transaction(
            fn (): IdempotencyAttempt => $this->store->begin($this->scope(), self::RawKey, $fingerprint, $this->digest('contract-b')),
        );

        self::assertSame(IdempotencyAttempt::InProgress, $live->disposition);
        self::assertSame(IdempotencyAttempt::Conflict, $conflict->disposition);
        self::assertSame(IdempotencyAttempt::ContractMismatch, $mismatch->disposition);

        $this->clock->advance(new DateInterval('PT11S'));
        $failed = JsonCanonical::encode(['error' => ['code' => 'internal_error']]);
        $replacement = $this->connection->transaction(function () use ($live, $first, $failed): IdempotencyAttempt {
            $replacement = $this->store->takeover($live);

            try {
                $this->store->complete($first, JsonCanonical::encode(['data' => []]));
                self::fail('The superseded lease unexpectedly persisted an outcome.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('superseded', $exception->getMessage());
            }

            $this->store->fail($replacement, $failed);

            return $replacement;
        });
        $replay = $this->connection->transaction(
            fn (): IdempotencyAttempt => $this->store->begin($this->scope(), self::RawKey, $fingerprint, $contract),
        );

        self::assertSame(2, $replacement->attempt);
        self::assertNotSame($first->leaseToken, $replacement->leaseToken);
        self::assertSame(IdempotencyAttempt::Replay, $replay->disposition);
        self::assertSame('failed', $replay->state);
        self::assertSame($failed, $replay->outcomeJson);

        $this->connection->transaction(fn (): IdempotencyAttempt => $this->store->begin(
            $this->scope(),
            'live-key',
            $this->digest('request-live'),
            $contract,
        ));
        $this->clock->advance(new DateInterval('PT61S'));

        self::assertSame(1, $this->store->prune());
        self::assertSame(1, $this->connection->table('sys_api_idempotency')->count());
        self::assertSame('started', $this->connection->table('sys_api_idempotency')->value('state'));
    }

    public function test_effect_and_terminal_outcome_roll_back_together_before_retry(): void
    {
        $fingerprint = $this->digest('request-rollback');
        $contract = $this->digest('contract-a');
        $outcome = JsonCanonical::encode(['data' => ['saved' => true]]);

        try {
            $this->connection->transaction(function () use ($fingerprint, $contract, $outcome): void {
                $attempt = $this->store->begin($this->scope(), 'rollback-key', $fingerprint, $contract);
                $this->connection->table('idempotency_effects')->insert(['value' => 'effect']);
                $this->store->complete($attempt, $outcome);

                throw new RuntimeException('crash-before-commit');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('crash-before-commit', $exception->getMessage());
        }

        self::assertSame(0, $this->connection->table('idempotency_effects')->count());
        self::assertSame(0, $this->connection->table('sys_api_idempotency')->count());

        $retry = $this->connection->transaction(function () use ($fingerprint, $contract, $outcome): IdempotencyAttempt {
            $attempt = $this->store->begin($this->scope(), 'rollback-key', $fingerprint, $contract);
            $this->connection->table('idempotency_effects')->insert(['value' => 'effect']);
            $this->store->complete($attempt, $outcome);

            return $attempt;
        });

        self::assertSame(IdempotencyAttempt::Owner, $retry->disposition);
        self::assertSame(1, $this->connection->table('idempotency_effects')->count());
    }

    public function test_mutations_require_the_active_tenant_connection_and_transaction(): void
    {
        try {
            $this->store->begin($this->scope(), self::RawKey, $this->digest('request-a'), $this->digest('contract-a'));
            self::fail('An idempotency attempt started outside a transaction.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('active tenant transaction', $exception->getMessage());
        }

        $wrong = new IdempotencyScope(
            connectionName: 'sqlite',
            databaseName: ':memory:',
            accountId: 'other-account',
            workspaceId: null,
            principalId: 'principal-1',
            clientId: null,
            apiMajor: 'v1',
            operationId: 'notes.create',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('active tenant connection');
        $this->connection->transaction(
            fn (): IdempotencyAttempt => $this->store->begin($wrong, self::RawKey, $this->digest('request-a'), $this->digest('contract-a')),
        );
    }

    public function test_migration_has_exact_columns_unique_key_and_scoped_rollback(): void
    {
        self::assertSame([
            'scope_hash',
            'key_hash',
            'request_fingerprint',
            'state',
            'attempt',
            'lease_token',
            'lease_until',
            'outcome_json',
            'contract_hash',
            'expires_at',
            'created_at',
            'updated_at',
        ], Schema::connection('sqlite')->getColumnListing('sys_api_idempotency'));

        Schema::connection('sqlite')->create('idempotency_sentinel', static function (Blueprint $table): void {
            $table->id();
        });
        $this->migration->down();

        self::assertFalse(Schema::connection('sqlite')->hasTable('sys_api_idempotency'));
        self::assertTrue(Schema::connection('sqlite')->hasTable('idempotency_sentinel'));

        Schema::connection('sqlite')->drop('idempotency_sentinel');
        $this->migration->up();
    }

    public function test_provider_binds_store_and_publishes_only_an_explicit_tenant_tag(): void
    {
        $this->app['config']->set('app.key', 'provider-test-secret');
        $this->app->instance(TenantContextResolver::class, $this->contexts);

        self::assertInstanceOf(SqlIdempotencyStore::class, $this->app->make(IdempotencyStore::class));

        $published = ApiServiceProvider::pathsToPublish(ApiServiceProvider::class, 'corex-api-migrations-tenant');
        self::assertSame([
            realpath(__DIR__.'/../../database/migrations/create_sys_api_idempotency_table.php') => database_path('migrations/tenant/create_sys_api_idempotency_table.php'),
        ], array_combine(array_map('realpath', array_keys($published)), array_values($published)));
    }

    #[Group('pg')]
    public function test_two_postgresql_connections_racing_on_one_key_yield_one_owner(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            self::markTestSkipped('The PostgreSQL concurrency proof requires pcntl and Unix sockets.');
        }

        $database = getenv('PGSQL_DATABASE') ?: '';

        if (preg_match('/\A[a-z0-9_]+_test\z/D', $database) !== 1) {
            self::markTestSkipped('PGSQL_DATABASE must name an isolated *_test database.');
        }

        $this->configurePostgres($database);
        Schema::connection('pgsql')->dropIfExists('sys_api_idempotency');
        $migration = require __DIR__.'/../../database/migrations/create_sys_api_idempotency_table.php';
        $migration->up();

        $children = [];

        for ($index = 0; $index < 2; $index++) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertNotFalse($pair);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);

            if ($pid === 0) {
                fclose($pair[0]);

                try {
                    DB::purge('pgsql');
                    $connection = DB::connection('pgsql');
                    $contexts = $this->contexts();
                    $store = $this->store(
                        $connection,
                        $contexts,
                        new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00+00:00')),
                    );
                    fwrite($pair[1], "ready\n");
                    fgets($pair[1]);
                    $attempt = $connection->transaction(function () use ($store, $connection, $database): IdempotencyAttempt {
                        $attempt = $store->begin(
                            $this->scope($connection, $database),
                            'postgres-race-key',
                            $this->digest('postgres-request'),
                            $this->digest('postgres-contract'),
                        );

                        if ($attempt->ownsLease()) {
                            usleep(200_000);
                        }

                        return $attempt;
                    });
                    fwrite($pair[1], json_encode(['disposition' => $attempt->disposition], JSON_THROW_ON_ERROR)."\n");
                } catch (Throwable $exception) {
                    fwrite($pair[1], json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
                }

                fclose($pair[1]);
                exit(0);
            }

            fclose($pair[1]);
            $children[] = ['pid' => $pid, 'socket' => $pair[0]];
        }

        foreach ($children as $child) {
            self::assertSame("ready\n", fgets($child['socket']));
        }

        foreach ($children as $child) {
            fwrite($child['socket'], "go\n");
        }

        $results = [];

        foreach ($children as $child) {
            $result = json_decode((string) fgets($child['socket']), true, flags: JSON_THROW_ON_ERROR);
            fclose($child['socket']);
            pcntl_waitpid($child['pid'], $status);
            self::assertSame(0, pcntl_wexitstatus($status));
            self::assertArrayNotHasKey('error', $result, $result['error'] ?? 'PostgreSQL child failed.');
            $results[] = $result['disposition'];
        }

        sort($results);
        self::assertSame([IdempotencyAttempt::InProgress, IdempotencyAttempt::Owner], $results);
        self::assertSame(1, DB::connection('pgsql')->table('sys_api_idempotency')->count());

        Schema::connection('pgsql')->dropIfExists('sys_api_idempotency');
    }

    private function store(Connection $connection, ContextFixture $contexts, ?FrozenClock $clock = null): SqlIdempotencyStore
    {
        return new SqlIdempotencyStore(
            connection: $connection,
            contexts: $contexts,
            clock: $clock ?? $this->clock,
            keyHmacSecret: new SecretRef(self::Secret),
            leaseSeconds: 10,
            retentionSeconds: 60,
        );
    }

    private function contexts(): ContextFixture
    {
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));

        return $contexts;
    }

    private function scope(?Connection $connection = null, ?string $database = null): IdempotencyScope
    {
        $connection ??= $this->connection;

        return new IdempotencyScope(
            connectionName: (string) $connection->getName(),
            databaseName: $database ?? $connection->getDatabaseName(),
            accountId: 'account-1',
            workspaceId: null,
            principalId: 'principal-1',
            clientId: 'client-1',
            apiMajor: 'v1',
            operationId: 'notes.create',
        );
    }

    private function digest(string $value): string
    {
        return hash('sha256', $value);
    }

    private function configurePostgres(string $database): void
    {
        $this->app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => $database,
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);
        $this->app['config']->set('database.default', 'pgsql');
        DB::purge('pgsql');

        self::assertSame($database, DB::connection('pgsql')->getDatabaseName());
    }
}
