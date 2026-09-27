<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use CoreX\Api\ApiError;
use CoreX\Api\Commands\PruneApiOperations;
use CoreX\Api\Contracts\BudgetReservation;
use CoreX\Api\Contracts\CursorKeyring;
use CoreX\Api\Contracts\WebhookPayloadProjector;
use CoreX\Api\Data\BudgetScope;
use CoreX\Api\Data\EstimatedCost;
use CoreX\Api\Data\WebhookTopic;
use CoreX\Api\Internal\Bulk\BulkRetention;
use CoreX\Api\Internal\Cost\BudgetRetention;
use CoreX\Api\Internal\Cost\SqlOperationBudget;
use CoreX\Api\Internal\JsonCanonical;
use CoreX\Api\Internal\SignedCursorCodec;
use CoreX\Api\Internal\Webhook\WebhookReplay;
use CoreX\Api\Internal\Webhook\WebhookRetention;
use CoreX\Api\Internal\Webhook\WebhookTopicCatalogue;
use CoreX\Api\Laravel\ApiSchemaRegistrar;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\TestCase;
use CoreX\Audit\Contracts\AuditEntry;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Enums\MessageClass;
use CoreX\Enums\PiiClass;
use CoreX\Exceptions\ScopeContextMismatch;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Support\Data\ArrayDataScopeCodec;
use CoreX\Support\Data\DataScope;
use CoreX\Support\SecretRef;
use CoreX\Support\Time\FrozenClock;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use CoreX\Tenancy\GuardedTenantContextRestorer;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
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

    private TenantContextRestorer $restorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure('api_budget', 'sqlite', ':memory:');
        $this->configure('api_tenant', 'sqlite', ':memory:');
        $this->selectSchema('api_budget', 'sqlite', ':memory:', 'api_tenant', 'sqlite', ':memory:');
        $this->budget = DB::connection('api_budget');
        $this->tenant = DB::connection('api_tenant');
        $this->migrate('budget', 'create_sys_api_budget_tables.php');
        $this->migrate('tenant', 'create_sys_api_bulk_operations_table.php');
        $this->migrate('tenant', 'create_sys_api_webhook_tables.php');
        $this->inbox($this->tenant);
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00.000000+00:00'));
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->restorer = new GuardedTenantContextRestorer($contexts);
        $this->scope = new DataScope(
            'account-a',
            null,
            $this->tenant->getDriverName().':'.$this->tenant->getDatabaseName(),
            'actor-a',
            hash('sha256', 'api-retention'),
        );
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
        $this->expectUnsafe(fn () => $this->webhooks(body: 604_801, history: 604_800, replay: 604_801));
        $this->expectUnsafe(fn () => $this->webhooks(body: 3_600, history: 86_400, replay: 7_200));
        $shorter = $this->webhooks(body: 3_600, history: 86_400, replay: 3_600);

        self::assertInstanceOf(WebhookRetention::class, $shorter);
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
            $retention->prune(new DataScope('account-a', null, 'sqlite:nowhere', 'actor-a', hash('sha256', 'api-retention')));
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

    public function test_expired_webhook_body_cannot_replay_and_tombstone_preserves_dedup(): void
    {
        $retention = $this->webhooks();
        $now = $this->clock->now();
        $this->delivery('delivery-old', self::OldEvent, 'dead', $now->sub(new DateInterval('P31D')), $now->sub(new DateInterval('P24D')), 'old-body');
        $this->delivery('delivery-pending', '44444444-4444-4444-8444-444444444444', 'pending', $now->sub(new DateInterval('P31D')), $now->sub(new DateInterval('P24D')), 'active-body');
        $this->delivery('delivery-leased', '55555555-5555-4555-8555-555555555555', 'dead', $now->sub(new DateInterval('P8D')), $now->sub(new DateInterval('P1D')), 'leased-body', $now->add(new DateInterval('PT30S')));
        $this->delivery('delivery-fresh', self::FreshEvent, 'dead', $now, $now->add(new DateInterval('P7D')), 'fresh-body');
        $this->attempt('attempt-old', 'delivery-old');
        $this->inboxRow(self::OldEvent);
        $this->inboxRow(self::OrphanEvent);
        $oldHash = (string) $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-old')->value('body_hash');

        $first = $retention->prune($this->scope);
        $old = $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-old')->first();

        self::assertSame(1, $first['bodies']);
        self::assertSame(1, $first['attempts']);
        self::assertSame(1, $first['inbox']);
        self::assertNotNull($old);
        self::assertSame('', $old->body);
        self::assertSame($oldHash, $old->body_hash);
        self::assertSame('dead', $old->status);
        self::assertNull($this->tenant->table('sys_api_webhook_attempts')->where('id', 'attempt-old')->first());
        self::assertSame('active-body', $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-pending')->value('body'));
        self::assertSame('leased-body', $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-leased')->value('body'));
        self::assertSame('fresh-body', $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-fresh')->value('body'));
        self::assertNotNull($this->tenant->table('sys_inbox_messages')->where('message_id', self::OldEvent)->first());
        self::assertNull($this->tenant->table('sys_inbox_messages')->where('message_id', self::OrphanEvent)->first());
        self::assertSame(0, $this->tenant->table('sys_api_webhook_deliveries')->insertOrIgnore($this->deliveryInsert('delivery-duplicate', self::OldEvent, 'pending', $now, $now->add(new DateInterval('P7D')), 'new-body')));
        self::assertSame(1, $this->tenant->table('sys_api_webhook_deliveries')->where('event_id', self::OldEvent)->count());
        $this->assertReplayExpired('delivery-old');

        $this->clock->advance(new DateInterval('P8D'));
        $second = $retention->prune($this->scope);

        self::assertSame(2, $second['bodies']);
        self::assertSame('', $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-fresh')->value('body'));
        self::assertSame('', $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-leased')->value('body'));
        self::assertNotNull($this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-fresh')->first());
        $this->assertReplayExpired('delivery-fresh');
        self::assertSame('active-body', $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-pending')->value('body'));
    }

    public function test_shorter_body_window_expires_replay_with_the_body(): void
    {
        $retention = $this->webhooks(body: 3_600, history: 86_400, replay: 3_600);
        $now = $this->clock->now();
        $this->delivery('delivery-short', self::FreshEvent, 'dead', $now, $now->add(new DateInterval('P7D')), 'short-body');

        $this->clock->advance(new DateInterval('PT2H'));
        $result = $retention->prune($this->scope);

        self::assertSame(1, $result['bodies']);
        self::assertSame('', $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-short')->value('body'));
        $this->assertReplayExpired('delivery-short');
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
        $this->app->instance(WebhookRetention::class, $this->webhooks());
        $this->app->instance(BudgetRetention::class, new BudgetRetention($this->budget, $this->clock));
        $this->app->instance(DataScope::class, $this->scope);
        $kernel = $this->app->make(Kernel::class);
        self::assertInstanceOf(ConsoleKernel::class, $kernel);
        $kernel->registerCommand(new PruneApiOperations);

        $this->artisan('corex:api:prune', ['--max' => 101])->assertExitCode(2);
        self::assertNotNull($this->tenant->table('sys_api_bulk_operations')->where('id', 'bulk-command')->value('result_ref'));

        $this->artisan('corex:api:prune', ['--max' => 100])->assertSuccessful();
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
            $this->migrate('budget', 'create_sys_api_budget_tables.php');
            $this->migrate('tenant', 'create_sys_api_bulk_operations_table.php');
            $this->migrate('tenant', 'create_sys_api_webhook_tables.php');
            $tenant = DB::connection('api_p1417_tenant');
            $budget = DB::connection('api_p1417_budget');
            $this->inbox($tenant);
            $contexts = new ContextFixture;
            $contexts->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
            $restorer = new GuardedTenantContextRestorer($contexts);
            $scope = new DataScope('account-a', null, $tenant->getDriverName().':'.$tenant->getDatabaseName(), 'actor-a', hash('sha256', 'api-retention-pg'));
            $previous = $this->tenant;
            $previousBudget = $this->budget;
            $previousScope = $this->scope;
            $this->tenant = $tenant;
            $this->budget = $budget;
            $this->scope = $scope;
            $now = $this->clock->now();
            $this->bulkRow('bulk-pg', 'completed', $now->sub(new DateInterval('PT2H')), $now->sub(new DateInterval('PT1H')), 'pg-input', 'pg-bytes');
            $this->delivery('delivery-pg', self::OldEvent, 'dead', $now->sub(new DateInterval('P8D')), $now->sub(new DateInterval('P1D')), 'pg-body');
            $this->inboxRow(self::OldEvent);
            $this->inboxRow(self::OrphanEvent);
            $bulk = new BulkRetention($tenant, new ArrayDataScopeCodec, $restorer, $this->clock);
            $webhooks = new WebhookRetention($tenant, $restorer, $this->clock);
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
            self::assertSame(['bodies' => 1, 'attempts' => 0, 'inbox' => 1], $webhooks->prune($scope));
            self::assertSame('', $tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-pg')->value('body'));
            self::assertNotNull($tenant->table('sys_inbox_messages')->where('message_id', self::OldEvent)->first());
            self::assertNull($tenant->table('sys_inbox_messages')->where('message_id', self::OrphanEvent)->first());
            self::assertSame(0, $tenant->table('sys_api_webhook_deliveries')->insertOrIgnore($this->deliveryInsert('delivery-pg-dup', self::OldEvent, 'pending', $now, $now->add(new DateInterval('P7D')), 'again')));
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

    private function webhooks(int $body = 604_800, int $history = 2_592_000, int $replay = 604_800): WebhookRetention
    {
        return new WebhookRetention(
            connection: $this->tenant,
            contexts: $this->restorer,
            clock: $this->clock,
            bodyRetentionSeconds: $body,
            historyRetentionSeconds: $history,
            replayHorizonSeconds: $replay,
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
            : new DataScope($account, null, $this->scope->connectionIdentity, 'actor-a', hash('sha256', 'api-retention'));
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
            'scope' => JsonCanonical::encode((new ArrayDataScopeCodec)->encode($scope)),
            'actor_id' => 'actor-a',
            'client_id' => 'client-a',
            'contract_hash' => str_repeat('a', 64),
            'extension_stamp' => '{}',
            'input_ref' => JsonCanonical::encode(['token' => $input]),
            'transfer_ref' => null,
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

    private function delivery(
        string $id,
        string $eventId,
        string $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        string $body,
        ?DateTimeImmutable $leaseUntil = null,
    ): void {
        $this->tenant->table('sys_api_webhook_deliveries')->insert($this->deliveryInsert(
            $id,
            $eventId,
            $status,
            $createdAt,
            $expiresAt,
            $body,
            $leaseUntil,
        ));
    }

    /** @return array<string, mixed> */
    private function deliveryInsert(
        string $id,
        string $eventId,
        string $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
        string $body,
        ?DateTimeImmutable $leaseUntil = null,
    ): array {
        return [
            'id' => $id,
            'subscription_id' => 'subscription-a',
            'subscription_revision' => 0,
            'event_id' => $eventId,
            'topic' => 'orders.placed',
            'topic_version' => 1,
            'major' => 'v1',
            'endpoint_snapshot' => 'https://hooks.example/receiver',
            'scope' => JsonCanonical::encode(['account_id' => 'account-a', 'workspace_id' => null]),
            'body' => $body,
            'body_hash' => hash('sha256', $body),
            'status' => $status,
            'attempt' => 1,
            'replay_count' => 0,
            'lease_owner' => $leaseUntil === null ? null : 'worker-a',
            'lease_until' => $leaseUntil,
            'next_attempt_at' => $createdAt,
            'created_at' => $createdAt,
            'finished_at' => in_array($status, ['dead', 'succeeded'], true) ? $createdAt : null,
            'expires_at' => $expiresAt,
            'last_error' => $status === 'dead' ? 'http_permanent' : null,
        ];
    }

    private function attempt(string $id, string $deliveryId): void
    {
        $this->tenant->table('sys_api_webhook_attempts')->insert([
            'id' => $id,
            'delivery_id' => $deliveryId,
            'attempt' => 1,
            'lease_owner' => 'worker-a',
            'started_at' => $this->clock->now()->sub(new DateInterval('P31D')),
            'finished_at' => $this->clock->now()->sub(new DateInterval('P31D')),
            'outcome' => 'dead',
            'http_status' => 500,
            'duration_ms' => 10,
            'error_code' => 'http_permanent',
        ]);
    }

    private function inboxRow(string $eventId): void
    {
        $this->tenant->table('sys_inbox_messages')->insert([
            'consumer' => WebhookRetention::Consumer,
            'message_id' => $eventId,
            'name' => 'orders.placed',
            'processed_at' => $this->clock->now(),
        ]);
    }

    private function assertReplayExpired(string $deliveryId): void
    {
        $schema = ['id' => 'string'];
        $topic = new WebhookTopic('orders.placed', 1, 'v1', $schema, hash('sha256', JsonCanonical::encode($schema)), 'personal', 'orders.placed');
        $replay = new WebhookReplay(
            connection: $this->tenant,
            contexts: $this->restorer,
            topics: new WebhookTopicCatalogue(new ArrayMessageContractRegistry([
                new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks'),
            ]), [$topic], ['orders.placed' => new RetentionProjector]),
            clock: $this->clock,
            cursors: new SignedCursorCodec($this->clock, new RetentionKeyring),
            audit: new RetentionAudit,
        );

        try {
            $replay->one($this->scope, $deliveryId, true, 'retention check');
            self::fail("Replay [{$deliveryId}] succeeded after body expiry.");
        } catch (ApiError $error) {
            self::assertSame('webhook_replay_expired', $error->errorCode);
        }
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

    private function inbox(Connection $connection): void
    {
        $connection->getSchemaBuilder()->create('sys_inbox_messages', function (Blueprint $table): void {
            $table->string('consumer', 120);
            $table->string('message_id', 64);
            $table->string('name', 160);
            $table->timestampTz('processed_at', precision: 6);
            $table->primary(['consumer', 'message_id']);
        });
    }

    private function migrate(string $directory, string $file): void
    {
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $migration = require __DIR__."/../../database/migrations/{$directory}/{$file}";
        $migration->up();
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
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-api.schema', [
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

final class RetentionProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        return ['event_id' => $message->messageId];
    }
}

final class RetentionKeyring implements CursorKeyring
{
    public function currentKeyId(): string
    {
        return 'current';
    }

    public function ttlSeconds(): int
    {
        return 900;
    }

    public function verifyingSecret(string $keyId): ?string
    {
        return $keyId === 'current' ? str_repeat('r', 32) : null;
    }
}

final class RetentionAudit implements AuditLogger
{
    public function record(AuditEntry $entry): void {}
}
