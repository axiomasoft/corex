<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\CoreServiceProvider;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Contracts\MessagePublisher;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Internal\JsonCloudEventCodec;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use CoreX\Time\Testing\FrozenClock;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Data\WebhookSubscription;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Internal\HmacWebhookSigner;
use CoreX\Webhooks\Internal\WebhookFanout;
use CoreX\Webhooks\Internal\WebhookKeyRing;
use CoreX\Webhooks\Internal\WebhookMessagePublisher;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use CoreX\Webhooks\Tests\TestCase;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Packman\Config\Data\SecretRef;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSets;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use TypeError;

final class WebhookFanoutTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-25T12:00:00.000000+00:00'));
        $this->configure('api_budget_stub');
        $this->configure('api_tenant');
        $this->selectSchema('api_tenant');

        $this->migrateSchemaSets(['axiomasoft/corex-webhooks:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());
        $this->inbox();
    }

    /** @param Application $app */
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    public function test_projection_failure_rolls_back_inbox_and_delivery_together(): void
    {
        $this->subscribe('sub-1');
        $publisher = $this->publisher(new ThrowingProjector);

        try {
            $publisher->publish($this->envelope('11111111-1111-4111-8111-111111111111'));
            self::fail('Projection failure was committed.');
        } catch (RuntimeException) {
            self::assertSame(0, DB::connection('api_tenant')->table('sys_inbox_messages')->count());
            self::assertSame(0, DB::connection('api_tenant')->table('sys_api_webhook_deliveries')->count());
        }
    }

    public function test_duplicate_event_creates_one_delivery_and_forwards_the_existing_publisher(): void
    {
        $this->subscribe('sub-1');
        $forwarded = 0;
        $inner = new class($forwarded) implements DurableMessagePublisher
        {
            public function __construct(private int &$count) {}

            public function publish(MessageEnvelope $message): void
            {
                $this->count++;
            }
        };
        $publisher = $this->publisher(new ExportProjector, $inner);
        $envelope = $this->envelope('22222222-2222-4222-8222-222222222222');
        $publisher->publish($envelope);
        $publisher->publish($envelope);
        $this->subscribe('sub-later');
        $publisher->publish($envelope);

        $rows = DB::connection('api_tenant')->table('sys_api_webhook_deliveries')->get();

        self::assertCount(1, $rows);
        self::assertSame($envelope->messageId, $rows[0]->event_id);
        self::assertNotSame($rows[0]->event_id, $rows[0]->id);
        self::assertSame(hash('sha256', (string) $rows[0]->body), $rows[0]->body_hash);
        self::assertSame('https://hooks.example/receiver', $rows[0]->endpoint_snapshot);
        self::assertSame(3, $forwarded);
        self::assertStringNotContainsString('hidden', (string) $rows[0]->body);
        $event = json_decode((string) $rows[0]->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['specversion', 'id', 'source', 'type', 'time', 'datacontenttype', 'data'], array_keys($event));
        self::assertSame('1.0', $event['specversion']);
        self::assertSame('orders.placed.v1', $event['type']);
        self::assertSame(['id' => 'order-1'], $event['data']);
        self::assertArrayNotHasKey('dataschema', $event);
        $keys = new WebhookKeyRing;
        $keys->provision('current', new SecretRef('whsec_'.base64_encode(str_repeat('s', 32))), 0);
        $signer = new HmacWebhookSigner($keys);
        $signed = $signer->sign((string) $rows[0]->id, 1790337600, (string) $rows[0]->body, ['current']);
        self::assertSame(['v1,'.base64_encode(hash_hmac('sha256', $rows[0]->id.'.1790337600.'.$rows[0]->body, str_repeat('s', 32), true))], $signed->signatures);
    }

    public function test_oversize_payload_is_dead_and_a_lossy_publisher_is_rejected(): void
    {
        $this->subscribe('sub-1');
        $this->publisher(new HugeProjector)->publish($this->envelope('33333333-3333-4333-8333-333333333333'));
        $row = DB::connection('api_tenant')->table('sys_api_webhook_deliveries')->first();

        self::assertNotNull($row);
        self::assertSame('dead', $row->status);
        self::assertSame('payload_too_large', $row->last_error);
        self::assertLessThan(WebhookFanout::BodyLimit, strlen((string) $row->body));

        $this->expectException(TypeError::class);
        new WebhookMessagePublisher(new class implements MessagePublisher
        {
            public function publish(MessageEnvelope $message): void {}
        }, $this->fanout(new ExportProjector));
    }

    #[Group('pg')]
    public function test_postgresql_inbox_and_delivery_roll_back_together(): void
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
        $this->app['config']->set('database.connections.api_pg_root', $base + ['search_path' => 'public']);
        $this->app['config']->set('database.connections.api_pg_tenant', $base + ['search_path' => 'api_p1412_tenant_test']);
        DB::purge('api_pg_root');
        DB::purge('api_pg_tenant');
        $root = DB::connection('api_pg_root');
        self::assertSame($database, $root->getDatabaseName());
        $root->statement('DROP SCHEMA IF EXISTS api_p1412_tenant_test CASCADE');
        $root->statement('CREATE SCHEMA api_p1412_tenant_test');

        try {
            $this->selectSchema('api_pg_tenant');

            $this->migrateSchemaSets(['axiomasoft/corex-webhooks:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());
            $this->inbox('api_pg_tenant');
            $connection = DB::connection('api_pg_tenant');
            $this->subscribe('sub-pg', $connection->getName());
            $fanout = $this->fanout(new ThrowingProjector, 'api_pg_tenant');

            try {
                $fanout->accept($this->envelope('44444444-4444-4444-8444-444444444444'));
                self::fail('PostgreSQL projection failure was committed.');
            } catch (RuntimeException) {
                self::assertSame(0, $connection->table('sys_inbox_messages')->count());
                self::assertSame(0, $connection->table('sys_api_webhook_deliveries')->count());
            }
        } finally {
            $root->statement('DROP SCHEMA IF EXISTS api_p1412_tenant_test CASCADE');
        }
    }

    private function publisher(WebhookPayloadProjector $projector, ?DurableMessagePublisher $inner = null): WebhookMessagePublisher
    {
        $inner ??= new class implements DurableMessagePublisher
        {
            public function publish(MessageEnvelope $message): void {}
        };

        return new WebhookMessagePublisher($inner, $this->fanout($projector));
    }

    private function fanout(WebhookPayloadProjector $projector, string $connection = 'api_tenant'): WebhookFanout
    {
        $db = DB::connection($connection);
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $schema = ['id' => 'string'];
        $topic = new WebhookTopic('orders.placed', 1, 'v1', $schema, hash('sha256', JsonCanonical::encode($schema)), 'personal', 'orders.placed');
        $contracts = MessagingFixtures::contracts([
            new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks', 'https://schemas.example.test/orders/v1.json'),
        ]);
        $catalogue = new WebhookTopicCatalogue($contracts, [$topic], ['orders.placed' => $projector]);

        return new WebhookFanout($db, MessagingFixtures::inbox($db, $this->clock), $catalogue, ['orders.placed' => $projector], $this->clock, new JsonCloudEventCodec($contracts), resolve(DataScopeCodec::class), new CallerWorkScopeFixture($contexts));
    }

    private function subscribe(string $id, string $connection = 'api_tenant'): void
    {
        $now = $this->clock->now();
        $subscription = new WebhookSubscription($id, 0, 'v1', 'orders.placed', 1, 'https://hooks.example/receiver', 'active', 'personal', ['current'], $now, $now);
        DB::connection($connection)->table('sys_api_webhook_subscriptions')->insert([
            'id' => $subscription->id,
            'revision' => 0,
            'major' => $subscription->major,
            'topic' => $subscription->topic,
            'topic_version' => $subscription->topicVersion,
            'endpoint_url' => $subscription->endpointUrl,
            'status' => 'active',
            'max_pii_class' => $subscription->maxPiiClass,
            'key_refs' => '["current"]',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function envelope(string $id): MessageEnvelope
    {
        return new MessageEnvelope(
            messageId: $id,
            source: 'orders',
            name: 'orders.placed',
            contractVersion: 1,
            occurredAt: new DateTimeImmutable('2026-09-25T12:00:00+00:00'),
            scope: resolve(DataScopeCodec::class)->encode(CallerWorkScopeFixture::scope('account-1', null, 'actor-1', str_repeat('a', 64))),
            payload: new MessagePayload(['id' => 'order-1', 'secret' => 'hidden']),
            piiClass: PiiClass::Personal,
            correlationId: 'correlation-1',
        );
    }

    private function inbox(string $connection = 'api_tenant'): void
    {
        DB::connection($connection)->getSchemaBuilder()->create('sys_inbox_messages', function (Blueprint $table): void {
            $table->string('consumer', 120);
            $table->uuid('message_id');
            $table->string('name', 160);
            $table->timestampTz('processed_at', precision: 6);
            $table->primary(['consumer', 'message_id']);
        });
    }

    private function configure(string $name): void
    {
        $this->app['config']->set('database.connections.'.$name, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
    }

    private function selectSchema(string $tenant): void
    {
        $this->app->forgetInstance(ApiSchemaConnections::class);
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
        $driver = (string) $this->app['config']->get('database.connections.'.$tenant.'.driver');
        $database = (string) $this->app['config']->get('database.connections.'.$tenant.'.database');
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => $tenant, 'driver' => $driver, 'database' => $database],
        ]);
    }
}

final class ExportProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        return ['id' => 'order-1'];
    }
}

final class ThrowingProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        throw new RuntimeException('projection failed');
    }
}

final class HugeProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        return ['id' => str_repeat('x', WebhookFanout::BodyLimit)];
    }
}
