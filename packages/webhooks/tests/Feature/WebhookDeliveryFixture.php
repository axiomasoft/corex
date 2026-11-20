<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use CoreX\Time\Testing\FrozenClock;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Internal\HmacWebhookSigner;
use CoreX\Webhooks\Internal\WebhookKeyRing;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use CoreX\Webhooks\Internal\WebhookUrlPolicy;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Packman\Config\Data\SecretRef;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSets;

trait WebhookDeliveryFixture
{
    private const string Secret = 'whsec_AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';

    private Connection $connection;

    private FrozenClock $clock;

    private DataScope $scope;

    private ContextFixture $contexts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure('api_budget_stub');
        $this->configure('api_tenant');
        $this->selectSchema();
        $this->connection = DB::connection('api_tenant');

        $this->migrateSchemaSets(['axiomasoft/corex-webhooks:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());
        $this->connection->getSchemaBuilder()->create('sys_inbox_messages', function (Blueprint $table): void {
            $table->string('consumer', 120);
            $table->string('message_id', 64);
            $table->string('name', 160);
            $table->timestampTz('processed_at', precision: 6);
            $table->primary(['consumer', 'message_id']);
        });
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00.000000+00:00'));
        $this->contexts = new ContextFixture;
        $this->contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $this->scope = CallerWorkScopeFixture::scope('account-1', null, 'actor-1', hash('sha256', 'webhook-delivery-restore'));
    }

    private function signer(): HmacWebhookSigner
    {
        $keys = new WebhookKeyRing;
        $keys->provision('current', new SecretRef(self::Secret), 0);

        return new HmacWebhookSigner($keys);
    }

    private function urlPolicy(): WebhookUrlPolicy
    {
        return new WebhookUrlPolicy(static fn (): array => ['93.184.216.34']);
    }

    private function topics(): WebhookTopicCatalogue
    {
        $schema = ['id' => 'string'];
        $projector = new FixtureDeliveryProjector;

        return new WebhookTopicCatalogue(
            MessagingFixtures::contracts([
                new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks'),
            ]),
            [new WebhookTopic(
                'orders.placed',
                1,
                'v1',
                $schema,
                hash('sha256', JsonCanonical::encode($schema)),
                'personal',
                'orders.placed',
            )],
            ['orders.placed' => $projector],
        );
    }

    private function seedDelivery(
        string $delivery,
        string $subscription,
        int $attempt = 0,
        ?DateTimeImmutable $createdAt = null,
    ): void {
        $now = $this->clock->now();
        $createdAt ??= $now;
        $this->connection->table('sys_api_webhook_subscriptions')->insert([
            'id' => $subscription,
            'revision' => 0,
            'major' => 'v1',
            'topic' => 'orders.placed',
            'topic_version' => 1,
            'endpoint_url' => 'https://hooks.example/receiver',
            'status' => 'active',
            'max_pii_class' => 'personal',
            'key_refs' => '["current"]',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $body = JsonCanonical::encode(['event_id' => $delivery, 'data' => ['id' => 'order-1']]);
        $this->connection->table('sys_api_webhook_deliveries')->insert([
            'id' => $delivery,
            'subscription_id' => $subscription,
            'subscription_revision' => 0,
            'event_id' => 'event-'.$delivery,
            'topic' => 'orders.placed',
            'topic_version' => 1,
            'major' => 'v1',
            'endpoint_snapshot' => 'https://hooks.example/receiver',
            'scope' => JsonCanonical::encode(resolve(DataScopeCodec::class)->encode($this->scope)),
            'body' => $body,
            'body_hash' => hash('sha256', $body),
            'status' => 'pending',
            'attempt' => $attempt,
            'replay_count' => 0,
            'lease_owner' => null,
            'lease_until' => null,
            'next_attempt_at' => $now,
            'created_at' => $createdAt,
            'finished_at' => null,
            'expires_at' => $createdAt->add(new DateInterval('P7D')),
            'last_error' => null,
        ]);
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

    private function selectSchema(): void
    {
        $this->app->forgetInstance(ApiSchemaConnections::class);
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
    }
}

final class FixtureDeliveryProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        return [];
    }
}
