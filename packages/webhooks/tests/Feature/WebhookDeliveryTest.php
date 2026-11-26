<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use Closure;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Pagination\Contracts\CursorCodec;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Contracts\WebhookProviderLookup;
use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Data\WebhookProviderReference;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use CoreX\Webhooks\Internal\PermitPublicEgressPolicy;
use CoreX\Webhooks\Internal\SqlWebhookDeliveryStore;
use CoreX\Webhooks\Internal\WebhookDeliveryWorker;
use CoreX\Webhooks\Internal\WebhookReplay;
use CoreX\Webhooks\Internal\WebhookRetryPolicy;
use CoreX\Webhooks\Internal\WebhookWork;
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
            'rate limit' => [new WebhookHttpResult(429, '120', null, 4, absenceProven: true), 'retry', 'http_429'],
            'server' => [new WebhookHttpResult(503, null, null, 4, absenceProven: true), 'retry', 'http_5xx'],
            'timeout' => [new WebhookHttpResult(null, null, WebhookFailureReason::NetworkFailure, 10_000), 'unknown', 'ack_unknown'],
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
            new WebhookHttpResult(429, '120', null, 1, absenceProven: true),
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
            new WebhookHttpResult(503, null, null, 1, absenceProven: true),
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
            new RecordingWebhookTransport($this->connection, [new WebhookHttpResult(503, '120', null, 1, absenceProven: true)]),
            static fn (int $min, int $max): int => $max,
        )->workOnce($this->scope, 'worker-time-limit');
        self::assertNotNull($timeLimit);
        self::assertSame('dead', $timeLimit->status);
        self::assertSame('retry_horizon_exhausted', $timeLimit->lastError);
    }

    public function test_ack_loss_is_reconciled_to_the_original_provider_effect_without_resend(): void
    {
        $this->seedDelivery('delivery-ack-loss', 'subscription-ack-loss');
        $transport = new RecordingWebhookTransport($this->connection, [
            new WebhookHttpResult(null, null, WebhookFailureReason::NetworkFailure, 1),
        ]);
        $worker = $this->worker($transport);
        $first = $worker->workOnce($this->scope, 'worker-first');

        self::assertNotNull($first);
        self::assertSame('unknown', $first->status);
        self::assertSame($first->effectKey, $transport->requests[0]->effectKey);
        self::assertNull($worker->workOnce($this->scope, 'worker-second'));
        self::assertFalse($worker->reconcile($this->scope, $first->id));

        $replay = new WebhookReplay(
            $this->connection, new CallerWorkScopeFixture($this->contexts), $this->topics(),
            $this->clock, resolve(CursorCodec::class), $this->createMock(AuditLogger::class),
            issuer: resolve(StoredWorkIssuer::class),
            scopes: resolve(DataScopeCodec::class),
        );

        try {
            $replay->one($this->scope, $first->id, true, 'Unconfirmed receipt');
            self::fail('Unknown webhook delivery created a fresh effect.');
        } catch (ApiError $error) {
            self::assertSame('operations.conflict', $error->errorCode);
        }

        $this->app->instance(WebhookProviderLookup::class, new readonly class($first->effectKey) implements WebhookProviderLookup
        {
            public function __construct(private string $expected) {}

            public function lookup(WebhookProviderReference $effect): ?ProviderObservation
            {
                return $effect->effectKey === $this->expected
                    ? new ProviderObservation(ProviderObservationKind::Confirmed, 'provider-1', 'test-receiver')
                    : null;
            }
        });
        self::assertTrue($worker->reconcile($this->scope, $first->id));
        $receipt = $this->store()->find($this->scope, $first->id);
        self::assertSame('succeeded', $receipt?->status);
        self::assertSame('provider-1', $receipt?->providerRef);
        self::assertCount(1, $transport->requests);
    }

    public function test_expired_sending_attempt_cannot_record_its_late_ack(): void
    {
        $this->seedDelivery('delivery-late-ack', 'subscription-late-ack');
        $transport = new RecordingWebhookTransport($this->connection, [
            new WebhookHttpResult(204, null, null, 1, 'provider-1'),
        ]);
        $transport->afterSend = function (): void {
            $this->clock->advance(new DateInterval('PT31S'));
            self::assertNull($this->store()->claim($this->scope, 'recovery-worker'));
        };
        $worker = $this->worker($transport);
        $late = $worker->workOnce($this->scope, 'original-worker');

        self::assertSame('unknown', $late?->status);
        self::assertNull($late?->providerRef);
        self::assertTrue($worker->reconcile($this->scope, 'delivery-late-ack'));
        $receipt = $this->store()->find($this->scope, 'delivery-late-ack');
        self::assertSame('succeeded', $receipt?->status);
        self::assertSame('provider-1', $receipt?->providerRef);
        self::assertCount(1, $transport->requests);
    }

    public function test_a_crash_after_provider_reserve_before_sending_can_recover_with_no_lookup(): void
    {
        $this->seedDelivery('delivery-before-send', 'subscription-before-send');
        $claim = $this->store()->claim($this->scope, 'lost-worker');
        self::assertNotNull($claim?->claimToken);
        self::assertNotNull($claim->leaseUntil);
        $effect = WebhookWork::effect($this->scope, $claim);
        resolve(ProviderEffectLedger::class)->reserve($this->scope, new ProviderAttempt(
            $effect, $claim->id, $claim->claimToken, $claim->attempt, $claim->leaseUntil, WebhookWork::deliveryDigest($claim),
        ));

        $this->clock->advance(new DateInterval('PT31S'));
        $transport = new RecordingWebhookTransport($this->connection, [new WebhookHttpResult(204, null, null, 1)]);
        $result = $this->worker($transport)->workOnce($this->scope, 'recovery-worker');

        self::assertSame('succeeded', $result?->status);
        self::assertSame(2, $result?->attempt);
        self::assertCount(1, $transport->requests);
        self::assertSame($effect->effectKey, $transport->requests[0]->effectKey);
    }

    public function test_authorized_replay_uses_a_new_signed_delivery_and_provider_effect(): void
    {
        $this->seedDelivery('delivery-replay', 'subscription-replay');
        $transport = new RecordingWebhookTransport($this->connection, [
            new WebhookHttpResult(204, null, null, 1, 'first-receipt'),
            new WebhookHttpResult(204, null, null, 1, 'replay-receipt'),
        ]);
        $worker = $this->worker($transport);
        $original = $worker->workOnce($this->scope, 'worker');
        self::assertSame('succeeded', $original?->status);
        $replay = new WebhookReplay(
            $this->connection, new CallerWorkScopeFixture($this->contexts), $this->topics(),
            $this->clock, resolve(CursorCodec::class), $this->createMock(AuditLogger::class),
            issuer: resolve(StoredWorkIssuer::class),
            scopes: resolve(DataScopeCodec::class),
        );
        $created = $replay->one($this->scope, 'delivery-replay', true, 'Approved incident resolution');
        $result = $worker->workOnce($this->scope, 'replay-worker');

        self::assertSame($created['id'], $result?->id);
        self::assertSame('delivery-replay', $result?->replayOf);
        self::assertSame('succeeded', $result?->status);
        self::assertSame('replay-receipt', $result->providerRef);
        self::assertSame('first-receipt', $this->store()->find($this->scope, 'delivery-replay')?->providerRef);
        self::assertSame($transport->requests[0]->body, $transport->requests[1]->body);
        self::assertNotSame($transport->requests[0]->effectKey, $transport->requests[1]->effectKey);
        self::assertNotSame($transport->requests[0]->signatureHeaders['webhook-id'], $transport->requests[1]->signatureHeaders['webhook-id']);
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
            scopeCodec: resolve(DataScopeCodec::class),
        );
    }

    private function store(?callable $random = null): SqlWebhookDeliveryStore
    {
        return new SqlWebhookDeliveryStore(
            connection: $this->connection,
            contexts: new CallerWorkScopeFixture($this->contexts),
            clock: $this->clock,
            retry: new WebhookRetryPolicy(WebhookOptionsParser::fromArray([]), $random),
            leaseSeconds: 30,
            receiptRecovery: static fn (): ProviderEffectRecovery => resolve(ProviderEffectRecovery::class),
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

    public ?Closure $afterSend = null;

    /** @param list<WebhookHttpResult> $results */
    public function __construct(
        private readonly Connection $connection,
        private array $results,
    ) {}

    public function send(ValidatedWebhookRequest $request): WebhookHttpResult
    {
        $this->transactionLevels[] = $this->connection->transactionLevel();
        $this->requests[] = $request;

        $this->effects++;
        ($this->afterSend ?? static function (): void {})();

        return array_shift($this->results) ?? new WebhookHttpResult(204, null, null, 1);
    }
}
