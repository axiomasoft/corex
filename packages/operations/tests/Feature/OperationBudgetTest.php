<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Config\SecretRef;
use CoreX\Operations\Cost\Data\ActualCost;
use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\EstimatedCost;
use CoreX\Operations\Cost\Internal\SqlBudgetReservation;
use CoreX\Operations\Cost\Internal\SqlOperationBudget;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Tests\TestCase;
use CoreX\Operations\Validation\JsonCanonical;
use CoreX\Time\FrozenClock;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class OperationBudgetTest extends TestCase
{
    private const int MicroUnits = 1_000_000;

    private const string Secret = 'budget-test-hmac-secret';

    private Connection $connection;

    private FrozenClock $clock;

    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSqlite('api_budget');
        $this->configureSqlite('api_tenant_stub');
        $this->selectSchema('api_budget', 'api_tenant_stub');
        $this->connection = DB::connection('api_budget');
        $this->migration = require __DIR__.'/../../database/migrations/budget/create_sys_api_budget_tables.php';
        $this->migration->up();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00.000000+00:00'));
    }

    protected function tearDown(): void
    {
        foreach (['api_budget', 'api_tenant_stub', 'api_pg_root', 'api_pg_budget'] as $name) {
            DB::purge($name);
        }

        parent::tearDown();
    }

    public function test_reserve_debits_four_hmac_buckets_without_persisting_scope_identifiers(): void
    {
        $reservation = $this->budget()->reserve($this->scope(), $this->cost(3));
        $rows = $this->connection->table('sys_api_budget_buckets')->orderBy('key')->get();
        $stored = JsonCanonical::encode([
            'buckets' => $rows->map(fn (object $row): array => (array) $row)->all(),
            'reservation' => (array) $this->connection->table('sys_api_budget_reservations')->first(),
        ]);

        self::assertInstanceOf(SqlBudgetReservation::class, $reservation);
        self::assertCount(4, $rows);
        self::assertSame([7, 7, 7, 7], $rows->map(fn (object $row): int => intdiv((int) $row->available, self::MicroUnits))->all());
        self::assertSame(7, $reservation->throttleStatus->available);
        self::assertSame(10, $reservation->throttleStatus->capacity);
        self::assertDoesNotMatchRegularExpression('/installation-1|account-1|client-1|actor-1/', $stored);

        foreach ($rows as $row) {
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/D', (string) $row->key);
        }
    }

    public function test_default_global_enforcement_observes_lower_scope_debt(): void
    {
        $first = $this->budget()->reserve($this->scope(account: 'account-a'), $this->cost(1));
        $second = $this->budget()->reserve($this->scope(account: 'account-b'), $this->cost(1));
        $firstKeys = $this->reservationKeys(1);
        $secondKeys = $this->reservationKeys(2);
        $global = array_values(array_intersect($firstKeys, $secondKeys));
        $lower = array_values(array_diff($firstKeys, $global))[0];
        $this->connection->table('sys_api_budget_buckets')->where('key', $lower)->update(['available' => 0]);

        $third = $this->budget()->reserve($this->scope(account: 'account-a'), $this->cost(1));

        self::assertSame(-self::MicroUnits, (int) $this->connection->table('sys_api_budget_buckets')->where('key', $lower)->value('available'));
        self::assertSame(7, $third->throttleStatus->available);
        self::assertInstanceOf(SqlBudgetReservation::class, $first);
        self::assertInstanceOf(SqlBudgetReservation::class, $second);
    }

    public function test_enforced_deficit_rolls_back_every_bucket_debit_and_reservation(): void
    {
        $budget = $this->budget(capacity: 2, enforcedLevels: ['global', 'account', 'client', 'actor']);
        $budget->reserve($this->scope(), $this->cost(1));
        $key = (string) $this->connection->table('sys_api_budget_buckets')->orderBy('key')->value('key');
        $this->connection->table('sys_api_budget_buckets')->where('key', $key)->update(['available' => 0]);
        $before = $this->available();

        try {
            $budget->reserve($this->scope(), $this->cost(1));
            self::fail('An enforced hierarchy deficit unexpectedly admitted a reservation.');
        } catch (TooManyRequestsHttpException $error) {
            self::assertSame(429, $error->getStatusCode());
            self::assertSame(1, $error->getHeaders()['Retry-After']);
        }

        self::assertSame($before, $this->available());
        self::assertSame(1, $this->connection->table('sys_api_budget_reservations')->count());
    }

    public function test_settlement_refunds_once_and_conflicting_terminal_outcome_cannot_refund_twice(): void
    {
        $reservation = $this->budget()->reserve($this->scope(), $this->cost(8));

        $reservation->settle(new ActualCost(3));
        self::assertSame([7, 7, 7, 7], $this->wholeAvailable());
        $reservation->settle(new ActualCost(3));
        self::assertSame([7, 7, 7, 7], $this->wholeAvailable());

        try {
            $reservation->settle(new ActualCost(4));
            self::fail('A conflicting settlement unexpectedly replaced the terminal charge.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('conflicting terminal outcome', $exception->getMessage());
        }

        try {
            $reservation->reject();
            self::fail('A reject unexpectedly replaced a completed settlement.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('conflicting terminal outcome', $exception->getMessage());
        }

        self::assertSame([7, 7, 7, 7], $this->wholeAvailable());
        self::assertSame('settled', $this->connection->table('sys_api_budget_reservations')->value('state'));
        self::assertSame(3 * self::MicroUnits, (int) $this->connection->table('sys_api_budget_reservations')->value('charged'));
    }

    public function test_reject_refunds_once_and_actual_overrun_becomes_debt(): void
    {
        $rejected = $this->budget()->reserve($this->scope(account: 'rejected'), $this->cost(8));
        $rejected->reject();
        $rejected->reject();
        self::assertSame(10 * self::MicroUnits, (int) $this->connection->table('sys_api_budget_buckets')->max('available'));

        $overrun = $this->budget()->reserve($this->scope(account: 'overrun'), $this->cost(3));
        $overrun->settle(new ActualCost(12));

        self::assertContains(-2, $this->wholeAvailable());

        try {
            $this->budget()->reserve($this->scope(account: 'overrun'), $this->cost(1));
            self::fail('Global budget debt unexpectedly admitted another operation.');
        } catch (TooManyRequestsHttpException $error) {
            self::assertSame(429, $error->getStatusCode());
        }
    }

    public function test_expired_and_missing_reservations_never_refund(): void
    {
        $expired = $this->budget(capacity: 10, ttl: 1)->reserve($this->scope(account: 'expired'), $this->cost(4));
        $this->clock->advance(new DateInterval('PT2S'));
        $expired->reject();

        self::assertSame('abandoned', $this->connection->table('sys_api_budget_reservations')->value('state'));
        self::assertContains(6, $this->wholeAvailable());

        $missing = $this->budget()->reserve($this->scope(account: 'missing'), $this->cost(2));
        $before = $this->available();
        $this->connection->table('sys_api_budget_reservations')->delete();
        $missing->reject();

        self::assertSame($before, $this->available());
    }

    public function test_scope_isolation_refill_and_clock_rollback_are_deterministic(): void
    {
        $budget = $this->budget(capacity: 2, restoreRate: 1);
        $budget->reserve($this->scope(account: 'account-a'), $this->cost(2));
        $budget->reserve($this->scope(installation: 'installation-2', account: 'account-a'), $this->cost(2));
        self::assertSame(8, $this->connection->table('sys_api_budget_buckets')->count());

        $earlier = new SqlOperationBudget(
            connection: $this->connection,
            clock: new FrozenClock(new DateTimeImmutable('2026-09-25T09:59:59.000000+00:00')),
            scopeHmacSecret: new SecretRef(self::Secret),
            capacity: 2,
            restoreRate: 1,
        );

        try {
            $earlier->reserve($this->scope(account: 'account-a'), $this->cost(1));
            self::fail('Clock rollback unexpectedly refilled an exhausted bucket.');
        } catch (TooManyRequestsHttpException $error) {
            self::assertSame(429, $error->getStatusCode());
        }

        $later = new SqlOperationBudget(
            connection: $this->connection,
            clock: new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:01.000000+00:00')),
            scopeHmacSecret: new SecretRef(self::Secret),
            capacity: 2,
            restoreRate: 1,
        );
        $reservation = $later->reserve($this->scope(account: 'account-a'), $this->cost(1));

        self::assertSame(0, $reservation->throttleStatus->available);
    }

    #[Group('pg')]
    public function test_postgresql_concurrent_reserve_admits_only_available_global_capacity(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            self::markTestSkipped('The PostgreSQL concurrency proof requires pcntl and Unix sockets.');
        }

        $database = getenv('PGSQL_DATABASE') ?: '';

        if (preg_match('/\A[a-z0-9_]+_test\z/D', $database) !== 1) {
            self::markTestSkipped('PGSQL_DATABASE must name an isolated *_test database.');
        }

        $root = $this->configurePostgres($database);
        $root->statement('DROP SCHEMA IF EXISTS api_p144_budget_test CASCADE');
        $root->statement('CREATE SCHEMA api_p144_budget_test');

        try {
            $this->selectSchema('api_pg_budget', 'api_tenant_stub');
            $migration = require __DIR__.'/../../database/migrations/budget/create_sys_api_budget_tables.php';
            $migration->up();
            $children = $this->forkReservations();

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
                $results[] = $result['result'];
            }

            sort($results);
            self::assertSame(['admitted', 'rate_limited'], $results);
            DB::purge('api_pg_budget');
            self::assertSame(1, DB::connection('api_pg_budget')->table('sys_api_budget_reservations')->count());
            self::assertSame([0, 0, 0, 0], DB::connection('api_pg_budget')->table('sys_api_budget_buckets')->pluck('available')->map(static fn (mixed $value): int => (int) $value)->all());
        } finally {
            DB::disconnect('api_pg_budget');
            $root->statement('DROP SCHEMA IF EXISTS api_p144_budget_test CASCADE');
            DB::purge('api_pg_root');
        }
    }

    /** @return list<array{pid: int, socket: resource}> */
    private function forkReservations(): array
    {
        $children = [];

        for ($index = 0; $index < 2; $index++) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertNotFalse($pair);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);

            if ($pid === 0) {
                fclose($pair[0]);

                try {
                    DB::purge('api_pg_budget');
                    $budget = new SqlOperationBudget(
                        connection: DB::connection('api_pg_budget'),
                        clock: new FrozenClock(new DateTimeImmutable('2026-09-25T10:00:00.000000+00:00')),
                        scopeHmacSecret: new SecretRef(self::Secret),
                        capacity: 1,
                        restoreRate: 1,
                    );
                    fwrite($pair[1], "ready\n");
                    fgets($pair[1]);

                    try {
                        $budget->reserve($this->scope(), $this->cost(1));
                        $result = 'admitted';
                    } catch (TooManyRequestsHttpException) {
                        $result = 'rate_limited';
                    }

                    fwrite($pair[1], json_encode(['result' => $result], JSON_THROW_ON_ERROR)."\n");
                } catch (Throwable $exception) {
                    fwrite($pair[1], json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
                }

                fclose($pair[1]);
                exit(0);
            }

            fclose($pair[1]);
            $children[] = ['pid' => $pid, 'socket' => $pair[0]];
        }

        return $children;
    }

    private function budget(
        int $capacity = 10,
        int $restoreRate = 1,
        array $enforcedLevels = ['global'],
        int $ttl = 3_600,
    ): SqlOperationBudget {
        return new SqlOperationBudget(
            connection: $this->connection,
            clock: $this->clock,
            scopeHmacSecret: new SecretRef(self::Secret),
            capacity: $capacity,
            restoreRate: $restoreRate,
            enforcedLevels: $enforcedLevels,
            reservationTtlSeconds: $ttl,
        );
    }

    private function scope(
        string $installation = 'installation-1',
        string $account = 'account-1',
        ?string $client = 'client-1',
        ?string $actor = 'actor-1',
    ): BudgetScope {
        return new BudgetScope($installation, $account, $client, $actor);
    }

    private function cost(int $units): EstimatedCost
    {
        return new EstimatedCost($units, hash('sha256', 'recipe'));
    }

    /** @return list<string> */
    private function reservationKeys(int $offset): array
    {
        $json = (string) $this->connection->table('sys_api_budget_reservations')->orderBy('created_at')->orderBy('id')->offset($offset - 1)->value('keys');
        $keys = JsonCanonical::decode($json);

        if (! is_array($keys) || ! array_is_list($keys)) {
            throw new RuntimeException('Test reservation keys are malformed.');
        }

        return $keys;
    }

    /** @return array<string, int> */
    private function available(): array
    {
        return $this->connection->table('sys_api_budget_buckets')
            ->orderBy('key')
            ->pluck('available', 'key')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();
    }

    /** @return list<int> */
    private function wholeAvailable(): array
    {
        return $this->connection->table('sys_api_budget_buckets')
            ->orderBy('key')
            ->pluck('available')
            ->map(static fn (mixed $value): int => intdiv((int) $value, self::MicroUnits))
            ->all();
    }

    private function configureSqlite(string $name): void
    {
        $this->app['config']->set('database.connections.'.$name, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
    }

    private function selectSchema(string $budget, string $tenant): void
    {
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => [
                'connection' => $budget,
                'driver' => (string) $this->app['config']->get('database.connections.'.$budget.'.driver'),
                'database' => (string) $this->app['config']->get('database.connections.'.$budget.'.database'),
            ],
            'tenant' => [
                'connection' => $tenant,
                'driver' => (string) $this->app['config']->get('database.connections.'.$tenant.'.driver'),
                'database' => (string) $this->app['config']->get('database.connections.'.$tenant.'.database'),
            ],
        ]);
    }

    private function configurePostgres(string $database): Connection
    {
        $base = [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => $database,
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
        ];
        $this->app['config']->set('database.connections.api_pg_root', $base + ['search_path' => 'public']);
        $this->app['config']->set('database.connections.api_pg_budget', $base + ['search_path' => 'api_p144_budget_test']);
        DB::purge('api_pg_root');
        DB::purge('api_pg_budget');

        $root = DB::connection('api_pg_root');
        self::assertSame($database, $root->getDatabaseName());

        return $root;
    }
}
