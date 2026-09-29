<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Audit\Contracts\AuditEntry;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\GuardedTenantContextRestorer;
use CoreX\CoreServiceProvider;
use CoreX\Data\DataScope;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Operations\ApiServiceProvider;
use CoreX\Operations\Errors\ApiError;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Pagination\Contracts\CursorKeyring;
use CoreX\Operations\Pagination\SignedCursorCodec;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Validation\JsonCanonical;
use CoreX\Time\FrozenClock;
use CoreX\Webhooks\Commands\PruneWebhooks;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Internal\WebhookReplay;
use CoreX\Webhooks\Internal\WebhookRetention;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use CoreX\Webhooks\WebhooksServiceProvider;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;

final class WebhookApiRetentionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, ApiServiceProvider::class, WebhooksServiceProvider::class];
    }

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
        $this->expectUnsafe(fn () => $this->webhooks(body: 604_801, history: 604_800, replay: 604_801));
        $this->expectUnsafe(fn () => $this->webhooks(body: 3_600, history: 86_400, replay: 7_200));
        $shorter = $this->webhooks(body: 3_600, history: 86_400, replay: 3_600);

        self::assertInstanceOf(WebhookRetention::class, $shorter);
    }

    public function test_expired_webhook_body_cannot_replay_and_tombstone_preserves_dedup(): void
    {
        $retention = $this->webhooks();
        $now = $this->clock->now();
        $this->delivery('delivery-old', self::OldEvent, 'dead', $now->sub(new DateInterval('P31D')), $now->sub(new DateInterval('P24D')), 'old-body');
        $this->delivery('delivery-pending', '44444444-4444-4444-8444-444444444444', 'pending', $now->sub(new DateInterval('P31D')), $now->sub(new DateInterval('P24D')), 'active-body');
        $this->delivery('delivery-leased', '55555555-5555-4555-8555-555555555555', 'dead', $now->sub(new DateInterval('P8D')), $now->sub(new DateInterval('P1D')), 'leased-body', $now->add(new DateInterval('PT30S')));
        $this->delivery('delivery-fresh', self::FreshEvent, 'dead', $now, $now->add(new DateInterval('P7D')), 'fresh-body');
        $this->insertWebhookAttempt('attempt-old', 'delivery-old');
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
            $this->delivery('delivery-pg', self::OldEvent, 'dead', $now->sub(new DateInterval('P8D')), $now->sub(new DateInterval('P1D')), 'pg-body');
            $this->inboxRow(self::OldEvent);
            $this->inboxRow(self::OrphanEvent);
            $webhooks = new WebhookRetention($tenant, $restorer, $this->clock);
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

    public function test_command_prunes_only_the_restored_scope_and_rejects_an_unbounded_batch(): void
    {
        $now = $this->clock->now();
        $this->delivery('delivery-command', self::OldEvent, 'dead', $now->sub(new DateInterval('P8D')), $now->sub(new DateInterval('P1D')), 'command-body');
        $this->app->instance(WebhookRetention::class, $this->webhooks());
        $this->app->instance(DataScope::class, $this->scope);
        $kernel = $this->app->make(Kernel::class);
        self::assertInstanceOf(ConsoleKernel::class, $kernel);
        $kernel->registerCommand(new PruneWebhooks);

        $this->artisan('corex:webhooks:prune', ['--max' => 101])->assertExitCode(2);
        self::assertSame('command-body', $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-command')->value('body'));
        $this->artisan('corex:webhooks:prune', ['--max' => 100])->assertSuccessful();
        self::assertSame('', $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-command')->value('body'));
        self::assertNotNull($this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-command')->first());
    }

    public function test_command_without_a_trusted_scope_preserves_tenant_rows(): void
    {
        $now = $this->clock->now();
        $this->delivery('delivery-unscoped', self::OldEvent, 'dead', $now->sub(new DateInterval('P8D')), $now->sub(new DateInterval('P1D')), 'unscoped-body');
        $this->app->instance(WebhookRetention::class, $this->webhooks());
        $this->artisan('corex:webhooks:prune', ['--max' => 100])->assertSuccessful();
        self::assertSame('unscoped-body', $this->tenant->table('sys_api_webhook_deliveries')->where('id', 'delivery-unscoped')->value('body'));
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

    private function insertWebhookAttempt(string $id, string $deliveryId): void
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
            topics: new WebhookTopicCatalogue(MessagingFixtures::contracts([
                new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks'),
            ]), [$topic], ['orders.placed' => new WebhookApiRetentionRetentionProjector]),
            clock: $this->clock,
            cursors: new SignedCursorCodec($this->clock, new WebhookApiRetentionRetentionKeyring),
            audit: new WebhookApiRetentionRetentionAudit,
        );

        try {
            $replay->one($this->scope, $deliveryId, true, 'retention check');
            self::fail("Replay [{$deliveryId}] succeeded after body expiry.");
        } catch (ApiError $error) {
            self::assertSame('webhook_replay_expired', $error->errorCode);
        }
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

final class WebhookApiRetentionRetentionProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        return ['event_id' => $message->messageId];
    }
}

final class WebhookApiRetentionRetentionKeyring implements CursorKeyring
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

final class WebhookApiRetentionRetentionAudit implements AuditLogger
{
    public function record(AuditEntry $entry): void {}
}
