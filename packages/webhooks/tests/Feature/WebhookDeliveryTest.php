<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use CoreX\Webhooks\Internal\PermitPublicEgressPolicy;
use CoreX\Webhooks\Internal\SqlWebhookDeliveryStore;
use CoreX\Webhooks\Internal\WebhookDeliveryWorker;
use CoreX\Webhooks\Internal\WebhookRetryPolicy;
use CoreX\Webhooks\Laravel\WebhookOptionsParser;
use CoreX\Webhooks\Tests\TestCase;
use DateInterval;
use Illuminate\Database\Connection;

final class WebhookDeliveryTest extends TestCase
{
    use WebhookDeliveryFixture;

    public function test_response_matrix_is_fenced_and_retry_is_bounded(): void
    {
        $cases = [
            'success' => [new WebhookHttpResult(204, null, null, 4), 'succeeded', null],
            'gone' => [new WebhookHttpResult(410, null, null, 4), 'dead', 'endpoint_gone'],
            'redirect' => [new WebhookHttpResult(302, null, null, 4), 'dead', 'redirect_forbidden'],
            'permanent' => [new WebhookHttpResult(422, null, null, 4), 'dead', 'http_permanent'],
            'rate limit' => [new WebhookHttpResult(429, '120', null, 4), 'retry', 'http_429'],
            'server' => [new WebhookHttpResult(503, null, null, 4), 'retry', 'http_5xx'],
            'timeout' => [new WebhookHttpResult(null, null, WebhookFailureReason::NetworkFailure, 10_000), 'retry', 'network_failure'],
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
            inbox: MessagingFixtures::inbox($this->connection, $this->clock),
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
            egress: new PermitPublicEgressPolicy,
            scopeCodec: app(DataScopeCodec::class),
        );
    }

    private function store(?callable $random = null): SqlWebhookDeliveryStore
    {
        return new SqlWebhookDeliveryStore(
            connection: $this->connection,
            contexts: new GuardedTenantContextRestorer($this->contexts),
            clock: $this->clock,
            retry: new WebhookRetryPolicy(WebhookOptionsParser::fromArray([]), $random),
            leaseSeconds: 30,
        );
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
