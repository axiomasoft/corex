<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Data\Data\DataScope;
use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Database\Contracts\SchemaSets;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Time\Testing\FrozenClock;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Data\WebhookSubscription;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Internal\PermitPublicEgressPolicy;
use CoreX\Webhooks\Internal\SqlWebhookSubscriptionStore;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use CoreX\Webhooks\Tests\TestCase;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;

final class WebhookSubscriptionTest extends TestCase
{
    private FrozenClock $clock;

    private SqlWebhookSubscriptionStore $store;

    private WebhookTopicCatalogue $catalogue;

    private DataScope $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure('api_budget_stub');
        $this->configure('api_tenant');
        $this->selectSchema();

        $this->migrateSchemaSets(['axiomasoft/corex-webhooks:tenant'], resolve(ApiSchemaRegistrar::class)->tenant()->getName());
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $connection = DB::connection('api_tenant');
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-25T12:00:00.000000+00:00'));
        $this->catalogue = $this->catalogue();
        $this->store = new SqlWebhookSubscriptionStore($connection, new GuardedTenantContextRestorer($contexts), $this->catalogue, $this->clock, new PermitPublicEgressPolicy);
        $this->scope = new DataScope('account-1', null, $connection->getDriverName().':'.$connection->getDatabaseName(), 'actor-1', hash('sha256', 'epoch'));
    }

    public function test_unknown_contract_version_schema_and_class_names_are_rejected(): void
    {
        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('The message contract is not declared.');
        $this->catalogue->get('orders.unknown', 1);
    }

    public function test_unknown_version_and_schema_are_rejected(): void
    {
        try {
            $this->catalogue->get('orders.placed', 2);
            self::fail('Unknown version was accepted.');
        } catch (ApiError $error) {
            self::assertSame('webhooks.unknown_message_contract', $error->errorCode);
        }

        try {
            $this->catalogue->assertSchema('orders.placed', 1, ['id' => 'string', 'extra' => 'string']);
            self::fail('Unknown schema was accepted.');
        } catch (ApiError $error) {
            self::assertSame('webhooks.unknown_message_schema', $error->errorCode);
        }

        $this->expectException(InvalidArgumentException::class);
        new WebhookTopic('orders.placed', 1, 'v1', ['id' => 'string'], hash('sha256', '{}'), 'none', 'App\\Events\\OrderPlaced');
    }

    public function test_sensitive_topics_require_an_ability_and_secrets_are_always_denied(): void
    {
        $personal = $this->catalogue->get('orders.placed', 1);

        try {
            $this->catalogue->admit($personal, false, true);
            self::fail('Sensitive topic was admitted without an ability.');
        } catch (ApiError $error) {
            self::assertSame('webhooks.webhook_sensitive_denied', $error->errorCode);
            self::assertSame(403, $error->status);
        }

        $this->catalogue->admit($personal, true, true);

        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('Secret webhook topics cannot be declared.');
        $this->catalogue(secret: true);
    }

    public function test_subscription_is_optimistic_capped_and_tenant_scoped(): void
    {
        $created = $this->store->create($this->scope, $this->subscription('sub-1'), true, true);
        $updated = $this->store->update($this->scope, 'sub-1', 0, 'https://hooks.example/next', ['current'], true, true);
        $disabled = $this->store->disable($this->scope, 'sub-1', 1);

        self::assertSame(0, $created->revision);
        self::assertSame(1, $updated->revision);
        self::assertSame('https://hooks.example/next', $updated->endpointUrl);
        self::assertSame('disabled', $disabled->status);
        self::assertSame(2, $disabled->revision);

        try {
            $this->store->update($this->scope, 'sub-1', 0, 'https://hooks.example/stale', ['current'], true, true);
            self::fail('Stale revision was accepted.');
        } catch (ApiError $error) {
            self::assertSame('operations.conflict', $error->errorCode);
        }

        self::assertSame('https://hooks.example/next', $this->store->find($this->scope, 'sub-1')?->endpointUrl);

        $foreign = new DataScope('account-2', null, 'sqlite:foreign', 'actor-2', hash('sha256', 'epoch'));

        try {
            $this->store->find($foreign, 'sub-1');
            self::fail('Foreign tenant read was accepted.');
        } catch (ScopeContextMismatch) {
            self::assertNotNull($this->store->find($this->scope, 'sub-1'));
        }

        for ($index = 2; $index <= SqlWebhookSubscriptionStore::CAP; $index++) {
            $this->store->create($this->scope, $this->subscription('sub-'.$index, 'billing.issued', 'none'), false, false);
        }

        try {
            $this->store->create($this->scope, $this->subscription('sub-101', 'billing.issued', 'none'), false, false);
            self::fail('Subscription cap was exceeded.');
        } catch (ApiError $error) {
            self::assertSame('webhooks.webhook_subscription_cap', $error->errorCode);
        }
    }

    #[Group('pg')]
    public function test_postgresql_subscription_revision_is_optimistic(): void
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
        $this->app['config']->set('database.connections.api_pg_tenant', $base + ['search_path' => 'api_p1411_tenant_test']);
        DB::purge('api_pg_root');
        DB::purge('api_pg_tenant');
        $root = DB::connection('api_pg_root');
        self::assertSame($database, $root->getDatabaseName());
        $root->statement('DROP SCHEMA IF EXISTS api_p1411_tenant_test CASCADE');
        $root->statement('CREATE SCHEMA api_p1411_tenant_test');

        try {
            $this->selectSchema();
            $this->app['config']->set('corex-operations.schema.tenant', [
                'connection' => 'api_pg_tenant',
                'driver' => 'pgsql',
                'database' => $database,
            ]);
            $this->app->forgetInstance(ApiSchemaRegistrar::class);
            $this->app->forgetInstance(SchemaSets::class);
            $this->app->forgetInstance(SchemaMigrator::class);

            $this->migrateSchemaSets(['axiomasoft/corex-webhooks:tenant'], resolve(ApiSchemaRegistrar::class)->tenant()->getName());
            $connection = DB::connection('api_pg_tenant');
            $contexts = new ContextFixture;
            $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
            $store = new SqlWebhookSubscriptionStore($connection, new GuardedTenantContextRestorer($contexts), $this->catalogue, $this->clock, new PermitPublicEgressPolicy);
            $scope = new DataScope('account-1', null, 'pgsql:'.$database, 'actor-1', hash('sha256', 'epoch'));
            $created = $store->create($scope, $this->subscription('sub-pg'), true, true);

            self::assertSame(0, $created->revision);
            self::assertSame(1, $store->disable($scope, 'sub-pg', 0)->revision);
        } finally {
            $root->statement('DROP SCHEMA IF EXISTS api_p1411_tenant_test CASCADE');
        }
    }

    public function test_projector_exports_only_the_public_allow_list(): void
    {
        $topic = $this->catalogue->assertSchema('orders.placed', 1, ['id' => 'string']);
        $envelope = new MessageEnvelope(
            messageId: 'message-1',
            source: 'orders',
            name: 'orders.placed',
            contractVersion: 1,
            occurredAt: new DateTimeImmutable('2026-09-25T12:00:00+00:00'),
            scope: ['account' => 'account-1'],
            payload: new MessagePayload(['id' => 'order-1', 'secret' => 'hidden']),
            piiClass: PiiClass::Personal,
            correlationId: 'correlation-1',
        );
        $projected = (new AllowListProjector)->project($envelope, $topic);

        self::assertSame(['id' => 'order-1'], $projected);
        self::assertArrayNotHasKey('secret', $projected);
    }

    private function catalogue(bool $secret = false): WebhookTopicCatalogue
    {
        $contracts = [
            new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks'),
            new MessageContract('billing.issued', 1, MessageClass::IntegrationEvent, 'billing/api', PiiClass::None, 'webhooks'),
        ];

        if ($secret) {
            $contracts[] = new MessageContract('vault.opened', 1, MessageClass::IntegrationEvent, 'vault/api', PiiClass::None, 'webhooks');
        }

        $topics = [
            $this->topic('orders.placed', 'personal', 'orders.placed'),
            $this->topic('billing.issued', 'none', 'billing.issued'),
        ];

        if ($secret) {
            $topics[] = $this->topic('vault.opened', 'secret', 'vault.opened');
        }

        return new WebhookTopicCatalogue(MessagingFixtures::contracts($contracts), $topics, [
            'orders.placed' => new AllowListProjector,
            'billing.issued' => new AllowListProjector,
            'vault.opened' => new AllowListProjector,
        ]);
    }

    private function topic(string $name, string $piiClass, string $projector): WebhookTopic
    {
        $schema = ['id' => 'string'];

        return new WebhookTopic($name, 1, 'v1', $schema, hash('sha256', JsonCanonical::encode($schema)), $piiClass, $projector);
    }

    private function subscription(string $id, string $topic = 'orders.placed', string $pii = 'personal'): WebhookSubscription
    {
        $now = $this->clock->now();

        return new WebhookSubscription($id, 0, 'v1', $topic, 1, 'https://hooks.example/receiver', 'active', $pii, ['current'], $now, $now);
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
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
    }
}

final class AllowListProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        $data = [];

        foreach ($topic->schema as $field => $type) {
            $value = $message->payload->toArray()[$field] ?? null;

            if (! is_string($value)) {
                throw new InvalidArgumentException("Webhook field [{$field}] is not {$type}.");
            }

            $data[$field] = $value;
        }

        return $data;
    }
}
