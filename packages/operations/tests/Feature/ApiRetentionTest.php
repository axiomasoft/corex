<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Operations\Bulk\Internal\BulkRetention;
use CoreX\Operations\Cost\Contracts\BudgetReservation;
use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\EstimatedCost;
use CoreX\Operations\Cost\Internal\BudgetRetention;
use CoreX\Operations\Cost\Internal\SqlOperationBudget;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Laravel\Commands\PruneApiOperations;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Tests\TestCase;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use CoreX\Time\Testing\FrozenClock;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Packman\Config\Data\SecretRef;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSets;
use PHPUnit\Framework\Attributes\Group;

final class ApiRetentionTest extends TestCase
{
    private const string OldEvent = '11111111-1111-4111-8111-111111111111';

    private const string OrphanEvent = '22222222-2222-4222-8222-222222222222';

    private const string FreshEvent = '33333333-3333-4333-8333-333333333333';

    private Connection $tenant;

    private Connection $budget;

    private FrozenClock $clock;

    private DataScope $scope;

    private CallerWorkScopeFixture $restorer;

    private ContextFixture $scopeContexts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure('api_budget', 'sqlite', ':memory:');
        $this->configure('api_tenant', 'sqlite', ':memory:');
        $this->selectSchema('api_budget', 'sqlite', ':memory:', 'api_tenant', 'sqlite', ':memory:');
        $this->budget = DB::connection('api_budget');
        $this->tenant = DB::connection('api_tenant');
        $this->migrate('budget');
        $this->migrate('tenant');
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00.000000+00:00'));
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->scopeContexts = $contexts;
        $this->scope = CallerWorkScopeFixture::scope('account-a', null, 'actor-a', hash('sha256', 'api-retention'));
        $this->restorer = new CallerWorkScopeFixture($contexts, expected: $this->scope);
    }

    protected function tearDown(): void
    {
        foreach (['api_budget', 'api_tenant', 'api_p1417_root', 'api_p1417_budget', 'api_p1417_tenant'] as $name) {
            DB::purge($name);
        }

        parent::tearDown();
    }

    public function test_retention_windows_reject_unsafe_configuration(): void
    {
        $this->expectUnsafe(fn () => $this->bulk(result: 86_401, metadata: 86_400, idempotency: 3_600));
        $this->expectUnsafe(fn () => $this->bulk(result: 3_600, metadata: 3_599, idempotency: 3_600));

        self::assertSame(100, BulkRetention::BatchLimit);
    }

    public function test_clock_clears_owned_bulk_results_then_tombstones_and_keeps_active_rows(): void
    {
        $retention = $this->bulk();
        $now = $this->clock->now();
        $this->bulkRow('bulk-recent', 'completed', $now, $now->add(new DateInterval('PT24H')), 'secret-input', 'sealed-recent');
        $this->bulkRow('bulk-ancient', 'completed', $now->sub(new DateInterval('P31D')), $now->sub(new DateInterval('P30D')), 'old-input', 'sealed-ancient');
        $this->bulkRow('bulk-bad', 'completed', $now->sub(new DateInterval('PT2H')), $now->sub(new DateInterval('PT1H')), 'bad-input', 'sealed-bad', hash('sha256', 'other-bytes'));
        $this->bulkRow('bulk-active', 'running', null, null, 'active-input', null, null, $now->sub(new DateInterval('PT1H')));
        $this->bulkRow('bulk-foreign', 'completed', $now->sub(new DateInterval('PT2H')), $now->sub(new DateInterval('PT1H')), 'foreign-input', 'sealed-foreign', null, null, 'account-b');

        $first = $retention->prune($this->scope);

        self::assertSame(0, $first['cleared']);
        self::assertSame(1, $first['removed']);
        self::assertNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-ancient')->first());
        self::assertStringContainsString('secret-input', (string) $this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-recent')->value('input_ref'));
        self::assertStringContainsString('bad-input', (string) $this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-bad')->value('input_ref'));
        self::assertStringContainsString('active-input', (string) $this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-active')->value('input_ref'));
        self::assertStringContainsString('foreign-input', (string) $this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-foreign')->value('input_ref'));

        $this->clock->advance(new DateInterval('PT25H'));
        $second = $retention->prune($this->scope);
        $recent = $this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-recent')->first();

        self::assertSame(1, $second['cleared']);
        self::assertSame(0, $second['removed']);
        self::assertNotNull($recent);
        self::assertSame('completed', $recent->status);
        self::assertSame('completed', $recent->terminal_status);
        self::assertNull($recent->result_ref);
        self::assertSame('{}', (string) $recent->input_ref);
        self::assertStringContainsString('sealed-bad', (string) $this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-bad')->value('result_ref'));
        self::assertStringContainsString('active-input', (string) $this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-active')->value('input_ref'));

        try {
            $retention->prune(CallerWorkScopeFixture::scope('account-a', null, 'actor-a', hash('sha256', 'api-retention'), 'foreign-binding'));
            self::fail('A foreign connection identity was accepted.');
        } catch (ScopeContextMismatch) {
            self::assertNotNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-recent')->first());
        }

        $this->clock->advance(new DateInterval('P31D'));
        $third = $retention->prune($this->scope);

        self::assertSame(1, $third['removed']);
        self::assertNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-recent')->first());
        self::assertNotNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-bad')->first());
        self::assertNotNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-active')->first());
        self::assertNotNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-foreign')->first());
    }

    public function test_bulk_prune_is_limited_to_one_hundred_rows(): void
    {
        $retention = $this->bulk();
        $finished = $this->clock->now()->sub(new DateInterval('PT2H'));

        for ($index = 0; $index < 101; $index++) {
            $this->bulkRow(sprintf('bulk-%03d', $index), 'completed', $finished, $finished->add(new DateInterval('PT1H')), 'input-'.$index, 'bytes-'.$index);
        }

        $result = $retention->prune($this->scope);

        self::assertSame(100, $result['cleared']);
        self::assertNotNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-100')->value('result_ref'));
        self::assertNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-000')->value('result_ref'));
    }

    public function test_reservation_expiry_never_refunds_and_held_reservation_keeps_buckets(): void
    {
        $operation = new SqlOperationBudget(
            connection: $this->budget,
            clock: $this->clock,
            scopeHmacSecret: new SecretRef('budget-test-hmac-secret'),
            reservationTtlSeconds: 60,
        );
        $reservation = $operation->reserve(new BudgetScope('installation-1', 'account-a', 'client-a', 'actor-a'), new EstimatedCost(1, hash('sha256', 'recipe')));
        $available = $this->available();
        $id = $this->budget->table('sys_api_budget_reservations')->value('id');
        $past = $this->clock->now()->sub(new DateInterval('PT2M'));
        $this->budget->table('sys_api_budget_buckets')->update(['last_used_at' => $past]);
        $retention = new BudgetRetention($this->budget, $this->clock, 60);

        self::assertSame(0, $retention->forgetIdleBuckets());
        self::assertSame($available, $this->available());
        self::assertFalse($retention->release('missing-reservation'));
        self::assertSame($available, $this->available());

        $this->budget->table('sys_api_budget_reservations')->where('id', $id)->update([
            'expires_at' => $this->clock->now()->sub(new DateInterval('PT1S')),
        ]);

        self::assertSame(1, $retention->abandonExpired());
        self::assertSame('abandoned', $this->budget->table('sys_api_budget_reservations')->where('id', $id)->value('state'));
        self::assertSame($available, $this->available());
        self::assertFalse($retention->release((string) $id));
        self::assertSame($available, $this->available());

        $this->budget->table('sys_api_budget_reservations')->where('id', $id)->update(['created_at' => $past]);

        self::assertSame(1, $retention->pruneTerminal());
        self::assertNull($this->budget->table('sys_api_budget_reservations')->where('id', $id)->first());
        self::assertSame($available, $this->available());
        self::assertFalse($retention->release((string) $id));
        self::assertSame(4, $retention->forgetIdleBuckets());
        self::assertSame(0, $this->budget->table('sys_api_budget_buckets')->count());
        self::assertInstanceOf(BudgetReservation::class, $reservation);
    }

    public function test_command_prunes_only_the_restored_scope_and_rejects_an_unbounded_batch(): void
    {
        $now = $this->clock->now();
        $this->bulkRow('bulk-command', 'completed', $now->sub(new DateInterval('PT2H')), $now->sub(new DateInterval('PT1H')), 'command-input', 'command-bytes');
        $this->app->instance(BulkRetention::class, $this->bulk());
        $this->app->instance(BudgetRetention::class, new BudgetRetention($this->budget, $this->clock));
        $this->app->instance(TenantContextResolver::class, $this->scopeContexts);
        CallerWorkScopeFixture::bind($this->app, $this->scope);
        $kernel = $this->app->make(Kernel::class);
        self::assertInstanceOf(ConsoleKernel::class, $kernel);
        $kernel->registerCommand(new PruneApiOperations);

        $this->artisan('corex:operations:prune', ['--max' => 101])->assertExitCode(2);
        self::assertNotNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-command')->value('result_ref'));

        $this->artisan('corex:operations:prune', ['--max' => 100])->assertSuccessful();
        self::assertNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-command')->value('result_ref'));
        self::assertNotNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-command')->first());
    }

    #[Group('pg')]
    public function test_postgresql_retention_uses_isolated_schemas(): void
    {
        $database = getenv('PGSQL_DATABASE') ?: '';

        if (preg_match('/\A[a-z0-9_]+_test\z/D', $database) !== 1) {
            self::markTestSkipped('PGSQL_DATABASE must name an isolated *_test database.');
        }

        $root = $this->postgres($database);
        $root->statement('DROP SCHEMA IF EXISTS api_p1417_budget_test CASCADE');
        $root->statement('DROP SCHEMA IF EXISTS api_p1417_tenant_test CASCADE');
        $root->statement('CREATE SCHEMA api_p1417_budget_test');
        $root->statement('CREATE SCHEMA api_p1417_tenant_test');

        try {
            $this->selectSchema('api_p1417_budget', 'pgsql', $database, 'api_p1417_tenant', 'pgsql', $database);
            $this->migrate('budget');
            $this->migrate('tenant');
            $tenant = DB::connection('api_p1417_tenant');
            $budget = DB::connection('api_p1417_budget');
            $contexts = new ContextFixture;
            $contexts->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
            $restorer = new CallerWorkScopeFixture($contexts);
            $scope = CallerWorkScopeFixture::scope('account-a', null, 'actor-a', hash('sha256', 'api-retention-pg'));
            $previous = $this->tenant;
            $previousBudget = $this->budget;
            $previousScope = $this->scope;
            $this->tenant = $tenant;
            $this->budget = $budget;
            $this->scope = $scope;
            $now = $this->clock->now();
            $this->bulkRow('bulk-pg', 'completed', $now->sub(new DateInterval('PT2H')), $now->sub(new DateInterval('PT1H')), 'pg-input', 'pg-bytes');
            $bulk = new BulkRetention($tenant, new ArrayDataScopeCodec, $restorer, $this->clock);
            $operation = new SqlOperationBudget($budget, $this->clock, new SecretRef('budget-test-hmac-secret'), reservationTtlSeconds: 60);
            $operation->reserve(new BudgetScope('installation-1', 'account-a', 'client-a', 'actor-a'), new EstimatedCost(1, hash('sha256', 'recipe')));
            $available = $this->available();
            $reservationId = (string) $budget->table('sys_api_budget_reservations')->value('id');
            $budget->table('sys_api_budget_buckets')->update(['last_used_at' => $now->sub(new DateInterval('PT2M'))]);
            $budgets = new BudgetRetention($budget, $this->clock, 60);

            self::assertSame(0, $budgets->forgetIdleBuckets());
            $budget->table('sys_api_budget_reservations')->where('id', $reservationId)->update([
                'expires_at' => $now->sub(new DateInterval('PT1S')),
            ]);
            self::assertSame(1, $budgets->abandonExpired());
            self::assertSame($available, $this->available());
            self::assertFalse($budgets->release('missing-reservation'));
            self::assertSame(['cleared' => 1, 'removed' => 0], $bulk->prune($scope));
            self::assertSame('{}', (string) $tenant->table('sys_api_bulk_operations')->where('id', 'bulk-pg')->value('input_ref'));
            $this->tenant = $previous;
            $this->budget = $previousBudget;
            $this->scope = $previousScope;
        } finally {
            DB::disconnect('api_p1417_tenant');
            DB::disconnect('api_p1417_budget');
            $root->statement('DROP SCHEMA IF EXISTS api_p1417_budget_test CASCADE');
            $root->statement('DROP SCHEMA IF EXISTS api_p1417_tenant_test CASCADE');
            DB::purge('api_p1417_root');
        }
    }

    private function expectUnsafe(callable $callback): void
    {
        try {
            $callback();
            self::fail('An unsafe retention window was accepted.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    private function bulk(int $result = 86_400, int $metadata = 2_592_000, int $idempotency = 86_400): BulkRetention
    {
        return new BulkRetention(
            connection: $this->tenant,
            scopeCodec: new ArrayDataScopeCodec,
            contexts: $this->restorer,
            clock: $this->clock,
            resultRetentionSeconds: $result,
            metadataRetentionSeconds: $metadata,
            idempotencyRetentionSeconds: $idempotency,
        );
    }

    private function bulkRow(
        string $id,
        string $status,
        ?DateTimeImmutable $finishedAt,
        ?DateTimeImmutable $expiresAt,
        string $input,
        ?string $bytes,
        ?string $hash = null,
        ?DateTimeImmutable $leaseUntil = null,
        string $account = 'account-a',
    ): void {
        $scope = $account === $this->scope->accountId
            ? $this->scope
            : CallerWorkScopeFixture::scope($account, null, 'actor-a', hash('sha256', 'api-retention'));
        $result = null;

        if ($bytes !== null) {
            $result = JsonCanonical::encode([
                'account_id' => $account,
                'operation_id' => $id,
                'hash' => $hash ?? hash('sha256', $bytes),
                'bytes' => $bytes,
            ]);
        }

        $this->tenant->table('sys_api_bulk_operations')->insert([
            'id' => $id,
            'major' => 'v1',
            'operation_id' => 'contacts.export',
            'submission_id' => 'submission-'.$id,
            'logical_scope' => str_repeat('a', 64), 'scope' => JsonCanonical::encode((new ArrayDataScopeCodec)->encode($scope)),
            'actor_id' => 'actor-a',
            'client_id' => 'client-a',
            'contract_hash' => str_repeat('a', 64),
            'extension_stamp' => '{}',
            'input_ref' => JsonCanonical::encode(['token' => $input]),
            'status' => $status,
            'terminal_status' => in_array($status, ['completed', 'cancelled', 'failed'], true) ? $status : null,
            'revision' => 1,
            'lease_owner' => $leaseUntil === null ? null : 'worker-a',
            'lease_until' => $leaseUntil,
            'cancel_requested_at' => null,
            'checkpoint' => '{}',
            'progress' => '{}',
            'result_ref' => $result,
            'error' => null,
            'attempt' => 1,
            'next_attempt_at' => $finishedAt ?? $this->clock->now(),
            'created_at' => $finishedAt ?? $this->clock->now(),
            'finished_at' => $finishedAt,
            'expires_at' => $expiresAt,
        ]);
    }

    /** @return array<string, int> */
    private function available(): array
    {
        return $this->budget->table('sys_api_budget_buckets')
            ->orderBy('key')
            ->pluck('available', 'key')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();
    }

    private function migrate(string $directory): void
    {
        $this->app->forgetInstance(ApiSchemaConnections::class);
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
        $this->migrateSchemaSets(["axiomasoft/corex-operations:{$directory}"], $directory === 'budget' ? null : resolve(ApiSchemaConnections::class)->tenant()->getName());
    }

    private function configure(string $name, string $driver, string $database): void
    {
        $this->app['config']->set('database.connections.'.$name, [
            'driver' => $driver,
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
    }

    private function selectSchema(
        string $budget,
        string $budgetDriver,
        string $budgetDatabase,
        string $tenant,
        string $tenantDriver,
        string $tenantDatabase,
    ): void {
        $this->app->forgetInstance(ApiSchemaConnections::class);
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => $budget, 'driver' => $budgetDriver, 'database' => $budgetDatabase],
            'tenant' => ['connection' => $tenant, 'driver' => $tenantDriver, 'database' => $tenantDatabase],
        ]);
    }

    private function postgres(string $database): Connection
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
        $this->app['config']->set('database.connections.api_p1417_root', $base + ['search_path' => 'public']);
        $this->app['config']->set('database.connections.api_p1417_budget', $base + ['search_path' => 'api_p1417_budget_test']);
        $this->app['config']->set('database.connections.api_p1417_tenant', $base + ['search_path' => 'api_p1417_tenant_test']);
        DB::purge('api_p1417_root');
        DB::purge('api_p1417_budget');
        DB::purge('api_p1417_tenant');

        return DB::connection('api_p1417_root');
    }
}
