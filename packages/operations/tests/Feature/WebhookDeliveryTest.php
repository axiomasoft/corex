<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use CoreX\Api\Contracts\WebhookHttpTransport;
use CoreX\Api\Contracts\WebhookPayloadProjector;
use CoreX\Api\Data\ValidatedWebhookRequest;
use CoreX\Api\Data\WebhookHttpResult;
use CoreX\Api\Data\WebhookTopic;
use CoreX\Api\Internal\JsonCanonical;
use CoreX\Api\Internal\Webhook\HmacWebhookSigner;
use CoreX\Api\Internal\Webhook\SqlWebhookDeliveryStore;
use CoreX\Api\Internal\Webhook\WebhookDeliveryWorker;
use CoreX\Api\Internal\Webhook\WebhookKeyRing;
use CoreX\Api\Internal\Webhook\WebhookRetryPolicy;
use CoreX\Api\Internal\Webhook\WebhookTopicCatalogue;
use CoreX\Api\Internal\Webhook\WebhookUrlPolicy;
use CoreX\Api\Laravel\ApiSchemaRegistrar;
use CoreX\Api\Tests\Support\ContextFixture;
use CoreX\Api\Tests\TestCase;
use CoreX\Enums\MessageClass;
use CoreX\Enums\PiiClass;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Messaging\Internal\SqlInbox;
use CoreX\Support\Config;
use CoreX\Support\Data\DataScope;
use CoreX\Support\SecretRef;
use CoreX\Support\Time\FrozenClock;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\GuardedTenantContextRestorer;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

final class WebhookDeliveryTest extends TestCase
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
        $migration = require __DIR__.'/../../database/migrations/tenant/create_sys_api_webhook_tables.php';
        $migration->up();
        $this->connection->getSchemaBuilder()->create(Config::inboxMessagesTable(), function (Blueprint $table): void {
            $table->string('consumer', 120);
            $table->string('message_id', 64);
            $table->string('name', 160);
            $table->timestampTz('processed_at', precision: 6);
            $table->primary(['consumer', 'message_id']);
        });
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00.000000+00:00'));
        $this->contexts = new ContextFixture;
        $this->contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));
        $this->scope = new DataScope(
            accountId: 'account-1',
            workspaceId: null,
            connectionIdentity: 'sqlite::memory:',
            actorId: 'actor-1',
            restoreEpoch: hash('sha256', 'webhook-delivery-restore'),
        );
    }

    public function test_response_matrix_is_fenced_and_retry_is_bounded(): void
    {
        $cases = [
            'success' => [new WebhookHttpResult(204, null, null, 4), 'succeeded', null],
            'gone' => [new WebhookHttpResult(410, null, null, 4), 'dead', 'endpoint_gone'],
            'redirect' => [new WebhookHttpResult(302, null, null, 4), 'dead', 'redirect_forbidden'],
            'permanent' => [new WebhookHttpResult(422, null, null, 4), 'dead', 'http_permanent'],
            'rate limit' => [new WebhookHttpResult(429, '120', null, 4), 'retry', 'http_429'],
            'server' => [new WebhookHttpResult(503, null, null, 4), 'retry', 'http_5xx'],
            'timeout' => [new WebhookHttpResult(null, null, 'network_timeout', 10_000), 'retry', 'network_timeout'],
        ];

        foreach ($cases as $name => [$result, $status, $error]) {
            $id = 'delivery-'.str_replace(' ', '-', $name);
            $subscription = 'subscription-'.str_replace(' ', '-', $name);
            $this->seedDelivery($id, $subscription);
            $transport = new RecordingWebhookTransport($this->connection, [$result]);
            $finished = $this->worker($transport)->workOnce($this->scope, 'worker-'.$name);

            self::assertNotNull($finished, $name);
            self::assertSame($status, $finished->status, $name);
            self::assertSame($error, $finished->lastError, $name);
            self::assertSame(0, $transport->transactionLevels[0], $name.' sent inside a transaction');
            self::assertSame(1, $finished->attempt, $name);

            if ($status === 'retry') {
                self::assertGreaterThan($this->clock->now(), $finished->nextAttemptAt, $name);
            }
        }

        $gone = $this->connection->table('sys_api_webhook_subscriptions')
            ->where('id', 'subscription-gone')
            ->first();
        self::assertSame('disabled', $gone?->status);

        $attempts = $this->connection->table('sys_api_webhook_attempts')->get();
        self::assertCount(count($cases), $attempts);

        foreach ($attempts as $attempt) {
            self::assertFalse(property_exists($attempt, 'body'));
            self::assertFalse(property_exists($attempt, 'signature'));
            self::assertFalse(property_exists($attempt, 'secret'));
        }
    }

    public function test_retry_after_jitter_attempt_and_seventy_two_hour_horizons_are_finite(): void
    {
        $this->seedDelivery('delivery-retry-after', 'subscription-retry-after');
        $retryAfter = new RecordingWebhookTransport($this->connection, [
            new WebhookHttpResult(429, '120', null, 1),
        ]);
        $finished = $this->worker($retryAfter, static fn (int $min, int $max): int => 7)
            ->workOnce($this->scope, 'worker-retry-after');

        self::assertNotNull($finished);
        self::assertSame(
            $this->clock->now()->add(new DateInterval('PT120S'))->getTimestamp(),
            $finished->nextAttemptAt->getTimestamp(),
        );

        $this->seedDelivery('delivery-attempt-limit', 'subscription-attempt-limit', attempt: 11);
        $attemptLimit = $this->worker(new RecordingWebhookTransport($this->connection, [
            new WebhookHttpResult(503, null, null, 1),
        ]))->workOnce($this->scope, 'worker-attempt-limit');
        self::assertNotNull($attemptLimit);
        self::assertSame('dead', $attemptLimit->status);
        self::assertSame('retry_exhausted', $attemptLimit->lastError);

        $this->seedDelivery(
            'delivery-time-limit',
            'subscription-time-limit',
            createdAt: $this->clock->now()->sub(new DateInterval('PT71H59M')),
        );
        $timeLimit = $this->worker(
            new RecordingWebhookTransport($this->connection, [new WebhookHttpResult(503, '120', null, 1)]),
            static fn (int $min, int $max): int => $max,
        )->workOnce($this->scope, 'worker-time-limit');
        self::assertNotNull($timeLimit);
        self::assertSame('dead', $timeLimit->status);
        self::assertSame('retry_horizon_exhausted', $timeLimit->lastError);
    }

    public function test_ack_loss_repeats_same_id_and_body_with_fresh_signature_and_one_receiver_effect(): void
    {
        $this->seedDelivery('delivery-ack-loss', 'subscription-ack-loss');
        $store = $this->store();
        $first = $store->claim($this->scope, 'worker-first');
        self::assertNotNull($first);
        $transport = new RecordingWebhookTransport(
            connection: $this->connection,
            results: [
                new WebhookHttpResult(204, null, null, 1),
                new WebhookHttpResult(204, null, null, 1),
            ],
            inbox: new SqlInbox($this->connection, $this->clock),
        );
        $keyRefs = $store->signingKeyRefs($this->scope, $first);
        self::assertSame(['current'], $keyRefs);
        $signed = $this->signer()->sign($first->id, $this->clock->now()->getTimestamp(), $first->body, $keyRefs);
        $request = $this->urlPolicy()->validate($first->endpointSnapshot, $first->body, $signed);
        $transport->send($request); // Receiver accepted; the sender loses the ACK before finish().

        $this->clock->advance(new DateInterval('PT31S'));
        $finished = $this->worker($transport)->workOnce($this->scope, 'worker-second');

        self::assertNotNull($finished);
        self::assertSame('succeeded', $finished->status);
        self::assertCount(2, $transport->requests);
        self::assertSame($transport->requests[0]->body, $transport->requests[1]->body);
        self::assertSame(
            $transport->requests[0]->signatureHeaders['webhook-id'],
            $transport->requests[1]->signatureHeaders['webhook-id'],
        );
        self::assertNotSame(
            $transport->requests[0]->signatureHeaders['webhook-timestamp'],
            $transport->requests[1]->signatureHeaders['webhook-timestamp'],
        );
        self::assertSame(1, $transport->effects);
        self::assertSame(
            ['unknown', 'succeeded'],
            $this->connection->table('sys_api_webhook_attempts')->orderBy('attempt')->pluck('outcome')->all(),
        );
    }

    private function worker(RecordingWebhookTransport $transport, ?callable $random = null): WebhookDeliveryWorker
    {
        return new WebhookDeliveryWorker(
            store: $this->store($random),
            signer: $this->signer(),
            urlPolicy: $this->urlPolicy(),
            transport: $transport,
            topics: $this->topics(),
            clock: $this->clock,
        );
    }

    private function store(?callable $random = null): SqlWebhookDeliveryStore
    {
        return new SqlWebhookDeliveryStore(
            connection: $this->connection,
            contexts: new GuardedTenantContextRestorer($this->contexts),
            clock: $this->clock,
            retry: new WebhookRetryPolicy($random),
            leaseSeconds: 30,
        );
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
        $projector = new DeliveryProjector;

        return new WebhookTopicCatalogue(
            new ArrayMessageContractRegistry([
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
            'scope' => JsonCanonical::encode(['account_id' => 'account-1']),
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
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-api.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
    }
}

final class RecordingWebhookTransport implements WebhookHttpTransport
{
    /** @var list<ValidatedWebhookRequest> */
    public array $requests = [];

    /** @var list<int> */
    public array $transactionLevels = [];

    public int $effects = 0;

    /** @param list<WebhookHttpResult> $results */
    public function __construct(
        private readonly Connection $connection,
        private array $results,
        private readonly ?Inbox $inbox = null,
    ) {}

    public function send(ValidatedWebhookRequest $request): WebhookHttpResult
    {
        $this->transactionLevels[] = $this->connection->transactionLevel();
        $this->requests[] = $request;

        if ($this->inbox !== null) {
            $this->connection->transaction(function () use ($request): void {
                if ($this->inbox?->claim(
                    'webhook.receiver.v1',
                    $request->signatureHeaders['webhook-id'],
                    'webhook.delivery',
                )) {
                    $this->effects++;
                }
            });
        }

        return array_shift($this->results) ?? new WebhookHttpResult(204, null, null, 1);
    }
}

final class DeliveryProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        return [
            'event_id' => $message->messageId,
            'type' => $message->name,
            'version' => $message->contractVersion,
            'occurred_at' => $message->occurredAt->format(DateTimeImmutable::ATOM),
            'data' => [],
            'correlation_id' => $message->correlationId,
        ];
    }
}
