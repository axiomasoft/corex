<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Config\Data\CoreOptions;
use CoreX\Config\Data\SecretRef;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\CoreServiceProvider;
use CoreX\Data\Data\DataScope;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Operations\ApiServiceProvider;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\LoadContourModule;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Time\Testing\FrozenClock;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookSubscription;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Internal\HmacWebhookSigner;
use CoreX\Webhooks\Internal\SqlWebhookDeliveryStore;
use CoreX\Webhooks\Internal\SqlWebhookSubscriptionStore;
use CoreX\Webhooks\Internal\WebhookDeliveryWorker;
use CoreX\Webhooks\Internal\WebhookFanout;
use CoreX\Webhooks\Internal\WebhookKeyRing;
use CoreX\Webhooks\Internal\WebhookRetryPolicy;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use CoreX\Webhooks\Internal\WebhookUrlPolicy;
use CoreX\Webhooks\Tests\Fixtures\LocalWebhookReceiver;
use CoreX\Webhooks\WebhooksServiceProvider;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;

final class WebhookLoadContourIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    private const string Secret = 'whsec_AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';

    private ModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, ApiServiceProvider::class, WebhooksServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->modules = new ModuleFixture(LoadContourModule::registry());
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('A', 'a', 'active', [], []));
        $this->identity = new IdentityFixture;
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        LoadContourModule::$calls = 0;
    }

    #[Group('pg')]
    public function test_postgresql_fanout_delivers_once_to_the_local_receiver(): void
    {
        $database = getenv('PGSQL_DATABASE') ?: '';

        if (preg_match('/\A[a-z0-9_]+_test\z/D', $database) !== 1) {
            self::markTestSkipped('PGSQL_DATABASE must name an isolated *_test database.');
        }

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
        $this->configureSqlite('api_budget_stub');
        $this->app['config']->set('database.connections.api_p1420_root', $base + ['search_path' => 'public']);
        $this->app['config']->set('database.connections.api_p1420_tenant', $base + ['search_path' => 'api_p1420_tenant_test']);
        DB::purge('api_p1420_root');
        DB::purge('api_p1420_tenant');
        $root = DB::connection('api_p1420_root');
        self::assertSame($database, $root->getDatabaseName());
        $root->statement('DROP SCHEMA IF EXISTS api_p1420_tenant_test CASCADE');
        $root->statement('CREATE SCHEMA api_p1420_tenant_test');

        try {
            $this->selectSchema('api_budget_stub', 'sqlite', ':memory:', 'api_p1420_tenant', 'pgsql', $database);
            $migration = require __DIR__.'/../../database/migrations/tenant/create_sys_api_webhook_tables.php';
            $migration->up();
            $connection = DB::connection('api_p1420_tenant');
            $connection->getSchemaBuilder()->create(app(CoreOptions::class)->inboxMessagesTable, function (Blueprint $table): void {
                $table->string('consumer', 120);
                $table->uuid('message_id');
                $table->string('name', 160);
                $table->timestampTz('processed_at', precision: 6);
                $table->primary(['consumer', 'message_id']);
            });
            $clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00.000000+00:00'));
            $catalogue = $this->catalogue();
            $fanout = new WebhookFanout($connection, MessagingFixtures::inbox($connection, $clock), $catalogue, ['orders.placed' => new WebhookLoadContourIntegrationLoadContourProjector], $clock);
            $now = $clock->now();
            $connection->table('sys_api_webhook_subscriptions')->insert([
                'id' => 'sub-local',
                'revision' => 0,
                'major' => 'v1',
                'topic' => 'orders.placed',
                'topic_version' => 1,
                'endpoint_url' => 'https://hooks.example/receiver',
                'status' => 'active',
                'max_pii_class' => 'personal',
                'key_refs' => '["current"]',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $envelope = new MessageEnvelope(
                messageId: '55555555-5555-4555-8555-555555555555',
                source: 'orders',
                name: 'orders.placed',
                contractVersion: 1,
                occurredAt: $now,
                scope: ['account_id' => 'account-1'],
                payload: new MessagePayload(['id' => 'order-1', 'secret' => 'hidden']),
                piiClass: PiiClass::Personal,
                correlationId: 'correlation-1',
            );
            $fanout->accept($envelope);
            $fanout->accept($envelope);
            $rows = $connection->table('sys_api_webhook_deliveries')->get();
            self::assertCount(1, $rows);
            self::assertNotSame($envelope->messageId, $rows[0]->id);
            self::assertStringNotContainsString('hidden', (string) $rows[0]->body);

            $contexts = new ContextFixture;
            $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
            $scope = new DataScope(
                'account-1',
                null,
                'pgsql:'.$database,
                'actor-1',
                hash('sha256', 'p1420-restore'),
            );
            $keys = new WebhookKeyRing;
            $keys->provision('current', new SecretRef(self::Secret), 0);
            $receiver = new LocalWebhookReceiver([self::Secret], $clock->now()->getTimestamp());
            $worker = new WebhookDeliveryWorker(
                store: new SqlWebhookDeliveryStore($connection, new GuardedTenantContextRestorer($contexts), $clock, new WebhookRetryPolicy),
                signer: new HmacWebhookSigner($keys),
                urlPolicy: new WebhookUrlPolicy(static fn (): array => ['93.184.216.34']),
                transport: $receiver,
                topics: $catalogue,
                clock: $clock,
            );
            $delivered = $worker->workOnce($scope, 'worker-local');

            self::assertNotNull($delivered);
            self::assertSame('succeeded', $delivered->status);
            self::assertSame(1, $receiver->effects);
            self::assertNull($worker->workOnce($scope, 'worker-local'));

            $signed = (new HmacWebhookSigner($keys))->sign($delivered->id, $clock->now()->getTimestamp(), $delivered->body, ['current']);
            $replay = new ValidatedWebhookRequest(
                'hooks.example',
                'https://hooks.example/receiver',
                '93.184.216.34',
                $delivered->body,
                $signed->toArray(),
            );
            $receiver->send($replay);
            $tampered = new ValidatedWebhookRequest(
                'hooks.example',
                'https://hooks.example/receiver',
                '93.184.216.34',
                $delivered->body.' ',
                $signed->toArray(),
            );

            self::assertSame(1, $receiver->effects);
            self::assertSame(401, $receiver->send($tampered)->status);
            self::assertSame('signature_invalid', $receiver->send($tampered)->errorCode);
            self::assertSame(1, $receiver->effects);
        } finally {
            $root->statement('DROP SCHEMA IF EXISTS api_p1420_tenant_test CASCADE');
        }
    }

    private function catalogue(): WebhookTopicCatalogue
    {
        $schema = ['id' => 'string'];

        return new WebhookTopicCatalogue(
            MessagingFixtures::contracts([
                new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks'),
            ]),
            [new WebhookTopic('orders.placed', 1, 'v1', $schema, hash('sha256', JsonCanonical::encode($schema)), 'personal', 'orders.placed')],
            ['orders.placed' => new WebhookLoadContourIntegrationLoadContourProjector],
        );
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

    public function test_boxed_and_cloud_scopes_stay_on_the_configured_connection(): void
    {
        $this->configureSqlite('api_budget_stub');
        $this->configureSqlite('api_tenant');
        $this->selectSchema('api_budget_stub', 'sqlite', ':memory:', 'api_tenant', 'sqlite', ':memory:');
        $migration = require __DIR__.'/../../database/migrations/tenant/create_sys_api_webhook_tables.php';
        $migration->up();
        $connection = DB::connection('api_tenant');
        $account = new AccountRef('account-1', 'account', 'active', [], []);
        $contexts = new ContextFixture;
        $contexts->value = new TenantContext($account, null, true);
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00.000000+00:00'));
        $store = new SqlWebhookSubscriptionStore($connection, new GuardedTenantContextRestorer($contexts), $this->catalogue(), $clock);
        $boxed = new DataScope('account-1', null, 'boxed:account-1', 'actor-1', hash('sha256', 'boxed-restore'), boxed: true);
        $created = $store->create($boxed, $this->subscription('boxed-sub', $clock->now()), true, true);

        self::assertSame('boxed-sub', $created->id);
        self::assertSame('api_tenant', $connection->getName());
        self::assertSame(':memory:', $connection->getDatabaseName());
        self::assertSame(1, $connection->table('sys_api_webhook_subscriptions')->count());

        try {
            $store->create(
                new DataScope('account-1', null, 'pgsql:production', 'actor-1', hash('sha256', 'boxed-restore'), boxed: true),
                $this->subscription('foreign-sub', $clock->now()),
                true,
                true,
            );
            self::fail('A foreign connection identity opened a subscription.');
        } catch (ScopeContextMismatch) {
            self::assertSame(1, $connection->table('sys_api_webhook_subscriptions')->count());
            self::assertSame(':memory:', $connection->getDatabaseName());
        }

        $contexts->value = new TenantContext($account, null, false);
        $cloud = new DataScope(
            'account-1',
            null,
            $connection->getDriverName().':'.$connection->getDatabaseName(),
            'actor-1',
            hash('sha256', 'cloud-restore'),
        );
        $store->create($cloud, $this->subscription('cloud-sub', $clock->now()), true, true);

        self::assertSame(2, $connection->table('sys_api_webhook_subscriptions')->count());
        self::assertSame(':memory:', $connection->getDatabaseName());
    }

    private function subscription(string $id, DateTimeImmutable $now): WebhookSubscription
    {
        return new WebhookSubscription($id, 0, 'v1', 'orders.placed', 1, 'https://hooks.example/receiver', 'active', 'personal', ['current'], $now, $now);
    }
}

final class WebhookLoadContourIntegrationLoadContourProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        return [
            'event_id' => $message->messageId,
            'type' => $message->name,
            'version' => $message->contractVersion,
            'occurred_at' => '2026-09-26T12:00:00+00:00',
            'data' => ['id' => 'order-1'],
            'correlation_id' => $message->correlationId,
        ];
    }
}
