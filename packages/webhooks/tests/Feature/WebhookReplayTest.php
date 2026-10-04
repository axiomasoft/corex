<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Errors\Internal\ErrorMapper;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Pagination\Contracts\CursorKeyring;
use CoreX\Operations\Pagination\Internal\SignedCursorCodec;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Time\Testing\FrozenClock;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Internal\WebhookReplay;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use CoreX\Webhooks\Tests\TestCase;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

final class WebhookReplayTest extends TestCase
{
    private Connection $connection;

    private FrozenClock $clock;

    private DataScope $scope;

    private ReplayAuditLogger $audit;

    private WebhookReplay $replay;

    private TenantContextRestorer $restorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure('api_budget_stub');
        $this->configure('api_tenant');
        $this->selectSchema();
        $this->connection = DB::connection('api_tenant');
        $migration = require __DIR__.'/../../database/migrations/tenant/create_sys_api_webhook_tables.php';
        $migration->up();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-26T16:00:00.000000+00:00'));
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $this->scope = new DataScope('account-a', null, 'sqlite::memory:', 'actor-a', hash('sha256', 'webhook-replay'));
        $this->audit = new ReplayAuditLogger;
        $this->restorer = new GuardedTenantContextRestorer($contexts);
        $this->replay = new WebhookReplay(
            connection: $this->connection,
            contexts: $this->restorer,
            topics: $this->catalogue(),
            clock: $this->clock,
            cursors: new SignedCursorCodec($this->clock, $this->keyring()),
            audit: $this->audit,
        );
    }

    public function test_replay_keeps_delivery_identity_bytes_destination_and_monotonic_attempt_sequence(): void
    {
        $this->seedSubscription('subscription-a');
        $before = $this->seedDelivery('delivery-a', 'subscription-a', 'dead', attempt: 7);

        $result = $this->replay->one($this->scope, 'delivery-a', false, null);
        $after = $this->connection->table('sys_api_webhook_deliveries')->where('id', 'delivery-a')->first();

        self::assertNotNull($after);
        self::assertSame('delivery-a', $result['id']);
        self::assertSame($before['body'], $after->body);
        self::assertSame($before['body_hash'], $after->body_hash);
        self::assertSame($before['endpoint_snapshot'], $after->endpoint_snapshot);
        self::assertSame(7, $after->attempt);
        self::assertSame(1, $after->replay_count);
        self::assertSame('pending', $after->status);
        self::assertNull($after->finished_at);
    }

    public function test_succeeded_replay_requires_explicit_confirmation_and_audit_reason(): void
    {
        $this->seedSubscription('subscription-success');
        $this->seedDelivery('delivery-success', 'subscription-success', 'succeeded');

        foreach ([[false, 'investigate'], [true, null], [true, '']] as [$explicit, $reason]) {
            try {
                $this->replay->one($this->scope, 'delivery-success', $explicit, $reason);
                self::fail('Succeeded replay bypassed confirmation or audit reason.');
            } catch (ApiError $error) {
                self::assertSame('operations.validation_failed', $error->errorCode);
            }
        }

        $result = $this->replay->one($this->scope, 'delivery-success', true, 'operator incident 42');

        self::assertSame('pending', $result['status']);
        self::assertCount(1, $this->audit->entries);
        self::assertSame('webhook.delivery.replayed', $this->audit->entries[0]->action);
        self::assertSame('actor-a', $this->audit->entries[0]->actorId);
        self::assertArrayHasKey('reason_sha256', JsonDocuments::map($this->audit->entries[0]->context));
        self::assertStringNotContainsString('operator incident 42', JsonCanonical::encode(JsonDocuments::map($this->audit->entries[0]->context)));
    }

    public function test_active_lease_changed_or_disabled_destination_removed_topic_expiry_and_cycle_cap_refuse(): void
    {
        $this->seedSubscription('subscription-active');
        $this->seedDelivery('delivery-active', 'subscription-active', 'sending', leaseOwner: 'worker-a');
        $this->assertReplayError('delivery-active', 'operations.conflict');

        $this->seedSubscription('subscription-disabled', status: 'disabled');
        $this->seedDelivery('delivery-disabled', 'subscription-disabled', 'dead');
        $this->assertReplayError('delivery-disabled', 'webhooks.webhook_endpoint_changed');

        $this->seedSubscription('subscription-revised', revision: 1);
        $this->seedDelivery('delivery-revised', 'subscription-revised', 'dead', subscriptionRevision: 0);
        $this->assertReplayError('delivery-revised', 'webhooks.webhook_endpoint_changed');

        $this->seedSubscription('subscription-expired');
        $this->seedDelivery('delivery-expired', 'subscription-expired', 'dead', expiresAt: $this->clock->now());
        $this->assertReplayError('delivery-expired', 'webhooks.webhook_replay_expired');

        $this->seedSubscription('subscription-cap');
        $this->seedDelivery('delivery-cap', 'subscription-cap', 'dead', replayCount: 3);
        $this->assertReplayError('delivery-cap', 'operations.conflict');

        $this->seedSubscription('subscription-topic');
        $this->seedDelivery('delivery-topic', 'subscription-topic', 'dead');
        $withoutTopic = new WebhookReplay(
            $this->connection,
            $this->restorer,
            new WebhookTopicCatalogue(MessagingFixtures::contracts([]), [], []),
            $this->clock,
            new SignedCursorCodec($this->clock, $this->keyring()),
            $this->audit,
        );

        try {
            $withoutTopic->one($this->scope, 'delivery-topic', false, null);
            self::fail('Removed topic replay was admitted.');
        } catch (ApiError $error) {
            self::assertSame('operations.conflict', $error->errorCode);
        }
    }

    public function test_range_is_bounded_to_twenty_four_hours_one_hundred_and_a_fixed_upper_bound(): void
    {
        $this->seedSubscription('subscription-range');
        foreach (['a', 'b', 'c'] as $offset => $suffix) {
            $this->seedDelivery(
                'delivery-'.$suffix,
                'subscription-range',
                'dead',
                createdAt: $this->clock->now()->sub(new DateInterval('PT'.(3 - $offset).'M')),
            );
        }

        $from = $this->clock->now()->sub(new DateInterval('PT1H'));
        $to = $this->clock->now()->add(new DateInterval('PT1H'));
        $first = $this->replay->range($this->scope, 'client-a', 'subscription-range', $from, $to, 2, null, 'range incident');

        self::assertSame(['delivery-a', 'delivery-b'], $first['selected']);
        self::assertSame(2, $first['requeued']);
        self::assertIsString($first['next']);

        $this->clock->advance(new DateInterval('PT1M'));
        $this->seedDelivery('delivery-late', 'subscription-range', 'dead', createdAt: $this->clock->now());
        $second = $this->replay->range($this->scope, 'client-a', 'subscription-range', $from, $to, 2, $first['next'], 'range incident');

        self::assertSame(['delivery-c'], $second['selected']);
        self::assertSame(1, $second['requeued']);
        self::assertNull($second['next']);
        self::assertSame('dead', $this->connection->table('sys_api_webhook_deliveries')->where('id', 'delivery-late')->value('status'));

        foreach ([[101, $from, $to], [10, $from, $from->add(new DateInterval('PT25H'))]] as [$max, $start, $end]) {
            try {
                $this->replay->range($this->scope, 'client-a', 'subscription-range', $start, $end, $max, null, 'range incident');
                self::fail('An unbounded replay range was admitted.');
            } catch (ApiError $error) {
                self::assertSame('operations.validation_failed', $error->errorCode);
            }
        }
    }

    #[Group('pg')]
    public function test_postgresql_scope_predicate_and_replay_cas(): void
    {
        $database = getenv('PGSQL_DATABASE') ?: '';

        if (preg_match('/\A[a-z0-9_]+_test\z/D', $database) !== 1) {
            self::markTestSkipped('PGSQL_DATABASE must name an isolated *_test database.');
        }

        $root = $this->configurePostgres($database);
        $root->statement('DROP SCHEMA IF EXISTS api_p1416_tenant_test CASCADE');
        $root->statement('CREATE SCHEMA api_p1416_tenant_test');

        try {
            $this->selectPostgresSchema($database);
            $migration = require __DIR__.'/../../database/migrations/tenant/create_sys_api_webhook_tables.php';
            $migration->up();
            $this->connection = DB::connection('api_p1416_tenant');
            $contexts = new ContextFixture;
            $contexts->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
            $this->restorer = new GuardedTenantContextRestorer($contexts);
            $this->scope = new DataScope(
                'account-a',
                null,
                'pgsql:'.$this->connection->getDatabaseName(),
                'actor-a',
                hash('sha256', 'webhook-replay-pg'),
            );
            $this->replay = new WebhookReplay(
                $this->connection,
                $this->restorer,
                $this->catalogue(),
                $this->clock,
                new SignedCursorCodec($this->clock, $this->keyring()),
                $this->audit,
            );
            $this->seedSubscription('subscription-pg');
            $before = $this->seedDelivery('delivery-pg', 'subscription-pg', 'dead', attempt: 4);

            $result = $this->replay->one($this->scope, 'delivery-pg', false, null);
            $after = $this->connection->table('sys_api_webhook_deliveries')->where('id', 'delivery-pg')->first();

            self::assertNotNull($after);
            self::assertSame('delivery-pg', $result['id']);
            self::assertSame($before['body_hash'], $after->body_hash);
            self::assertSame('pending', $after->status);
            self::assertSame(4, $after->attempt);
            self::assertSame(1, $after->replay_count);
        } finally {
            DB::disconnect('api_p1416_tenant');
            $root->statement('DROP SCHEMA IF EXISTS api_p1416_tenant_test CASCADE');
            DB::purge('api_p1416_root');
        }
    }

    private function assertReplayError(string $delivery, string $code): void
    {
        try {
            $this->replay->one($this->scope, $delivery, true, 'bounded reason');
            self::fail("Replay [{$delivery}] unexpectedly succeeded.");
        } catch (ApiError $error) {
            self::assertSame($code, $error->errorCode);
            $problems = app(ProblemRegistry::class);
            self::assertSame($error, (new ErrorMapper($problems, app(ExceptionHandler::class)))->map($error, reportUnexpected: false));
            $restored = StoredOperationOutcome::fromCanonicalJson(StoredOperationOutcome::failure($error)->canonicalJson(), $problems)->error($problems);
            self::assertSame($error->status, $restored->status);
            self::assertSame($error->category, $restored->category);
            self::assertSame($code, $restored->errorCode);
        }
    }

    private function seedSubscription(string $id, string $status = 'active', int $revision = 0): void
    {
        $this->connection->table('sys_api_webhook_subscriptions')->insert([
            'id' => $id, 'revision' => $revision, 'major' => 'v1', 'topic' => 'orders.placed', 'topic_version' => 1,
            'endpoint_url' => 'https://hooks.example/receiver', 'status' => $status, 'max_pii_class' => 'personal',
            'key_refs' => '["current"]', 'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function seedDelivery(
        string $id,
        string $subscription,
        string $status,
        int $attempt = 1,
        int $replayCount = 0,
        int $subscriptionRevision = 0,
        ?string $leaseOwner = null,
        ?DateTimeImmutable $expiresAt = null,
        ?DateTimeImmutable $createdAt = null,
    ): array {
        $createdAt ??= $this->clock->now()->sub(new DateInterval('PT5M'));
        $expiresAt ??= $createdAt->add(new DateInterval('P7D'));
        $body = JsonCanonical::encode(['event_id' => $id, 'data' => ['id' => 'order-1']]);
        $row = [
            'id' => $id, 'subscription_id' => $subscription, 'subscription_revision' => $subscriptionRevision,
            'event_id' => 'event-'.$id, 'topic' => 'orders.placed', 'topic_version' => 1, 'major' => 'v1',
            'endpoint_snapshot' => 'https://hooks.example/receiver',
            'scope' => JsonCanonical::encode(['account' => 'account-a', 'workspace' => null]),
            'body' => $body, 'body_hash' => hash('sha256', $body), 'status' => $status, 'attempt' => $attempt,
            'replay_count' => $replayCount, 'lease_owner' => $leaseOwner,
            'lease_until' => $leaseOwner === null ? null : $this->clock->now()->add(new DateInterval('PT30S')),
            'next_attempt_at' => $createdAt, 'created_at' => $createdAt,
            'finished_at' => in_array($status, ['dead', 'succeeded'], true) ? $createdAt : null,
            'expires_at' => $expiresAt, 'last_error' => $status === 'dead' ? 'http_permanent' : null,
        ];
        $this->connection->table('sys_api_webhook_deliveries')->insert($row);

        return $row;
    }

    private function catalogue(): WebhookTopicCatalogue
    {
        $schema = ['id' => 'string'];

        return new WebhookTopicCatalogue(
            MessagingFixtures::contracts([
                new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks'),
            ]),
            [new WebhookTopic('orders.placed', 1, 'v1', $schema, hash('sha256', JsonCanonical::encode($schema)), 'personal', 'orders.placed')],
            ['orders.placed' => new ReplayProjector],
        );
    }

    private function keyring(): CursorKeyring
    {
        return new class implements CursorKeyring
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
        };
    }

    private function configure(string $name): void
    {
        $this->app['config']->set('database.connections.'.$name, [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
    }

    private function selectSchema(): void
    {
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
    }

    private function configurePostgres(string $database): Connection
    {
        $base = [
            'driver' => 'pgsql', 'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434', 'database' => $database,
            'username' => getenv('PGSQL_USERNAME') ?: 'corex', 'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8', 'prefix' => '',
        ];
        $this->app['config']->set('database.connections.api_p1416_root', $base + ['search_path' => 'public']);
        $this->app['config']->set('database.connections.api_p1416_tenant', $base + ['search_path' => 'api_p1416_tenant_test']);
        DB::purge('api_p1416_root');
        DB::purge('api_p1416_tenant');

        return DB::connection('api_p1416_root');
    }

    private function selectPostgresSchema(string $database): void
    {
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_p1416_tenant', 'driver' => 'pgsql', 'database' => $database],
        ]);
    }
}

final class ReplayAuditLogger implements AuditLogger
{
    /** @var list<AuditEntry> */
    public array $entries = [];

    public function record(AuditEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}

final class ReplayProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        return [];
    }
}
