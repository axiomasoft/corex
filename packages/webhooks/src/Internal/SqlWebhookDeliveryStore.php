<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\DataScope;
use CoreX\Operations\Validation\JsonCanonical;
use CoreX\Webhooks\Contracts\WebhookDeliveryStore;
use CoreX\Webhooks\Data\WebhookDelivery;
use CoreX\Webhooks\Data\WebhookHttpResult;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Override;
use Psr\Clock\ClockInterface;
use RuntimeException;

/** @internal Tenant-local webhook delivery rows. Network send stays outside this store. */
final readonly class SqlWebhookDeliveryStore implements WebhookDeliveryStore
{
    private const string Table = 'sys_api_webhook_deliveries';

    public function __construct(
        private Connection $connection,
        private TenantContextRestorer $contexts,
        private ClockInterface $clock,
        private WebhookRetryPolicy $retry,
        private int $leaseSeconds = 30,
    ) {
        if ($leaseSeconds < 11) {
            throw new InvalidArgumentException('Webhook lease must exceed the ten-second HTTP timeout.');
        }
    }

    #[Override]
    public function find(DataScope $scope, string $id): ?WebhookDelivery
    {
        $this->assertScope($scope);
        $row = $this->connection->table(self::Table)->where('id', $id)->first();

        return $row === null ? null : $this->delivery($row);
    }

    #[Override]
    public function forEvent(DataScope $scope, string $eventId): array
    {
        $this->assertScope($scope);
        $rows = $this->connection->table(self::Table)->where('event_id', $eventId)->orderBy('id')->get();
        $deliveries = [];

        foreach ($rows as $row) {
            $deliveries[] = $this->delivery($row);
        }

        return $deliveries;
    }

    public function claim(DataScope $scope, string $worker): ?WebhookDelivery
    {
        $this->assertScope($scope);
        $this->assertIdentifier($worker, 'worker');

        return $this->connection->transaction(function () use ($worker): ?WebhookDelivery {
            $now = $this->clock->now();
            $this->recoverExpired($now);
            $query = $this->connection->table(self::Table)
                ->whereIn('status', ['pending', 'retry'])
                ->where('next_attempt_at', '<=', $now)
                ->whereNull('lease_owner')
                ->orderBy('next_attempt_at')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(1);
            $this->lockClaimQuery($query);
            $row = $query->first();

            if ($row === null) {
                return null;
            }

            $attempt = (int) $row->attempt + 1;
            $leaseUntil = $now->add(new DateInterval("PT{$this->leaseSeconds}S"));
            $changed = $this->connection->table(self::Table)
                ->where('id', (string) $row->id)
                ->where('status', (string) $row->status)
                ->where('attempt', (int) $row->attempt)
                ->whereNull('lease_owner')
                ->update([
                    'status' => 'sending',
                    'attempt' => $attempt,
                    'lease_owner' => $worker,
                    'lease_until' => $leaseUntil,
                    'last_error' => null,
                ]);

            if ($changed !== 1) {
                throw new RuntimeException('Webhook claim lost its compare-and-swap fence.');
            }

            $this->connection->table('sys_api_webhook_attempts')->insert([
                'id' => substr(hash('sha256', $row->id.'|'.$attempt), 0, 32),
                'delivery_id' => $row->id,
                'attempt' => $attempt,
                'lease_owner' => $worker,
                'started_at' => $now,
                'finished_at' => null,
                'outcome' => 'pending',
                'http_status' => null,
                'duration_ms' => null,
                'error_code' => null,
            ]);

            return $this->delivery($this->requiredRow((string) $row->id));
        }, $this->transactionAttempts());
    }

    /** @return list<string>|null */
    public function signingKeyRefs(DataScope $scope, WebhookDelivery $delivery): ?array
    {
        $this->assertScope($scope);

        return $this->connection->transaction(function () use ($delivery): ?array {
            $now = $this->clock->now();
            $row = $this->connection->table(self::Table)
                ->where('id', $delivery->id)
                ->where('status', 'sending')
                ->where('lease_owner', $delivery->leaseOwner)
                ->where('attempt', $delivery->attempt)
                ->where('lease_until', '>', $now)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return null;
            }

            $subscription = $this->connection->table('sys_api_webhook_subscriptions')
                ->where('id', $delivery->subscriptionId)
                ->first();

            if ($subscription === null
                || (string) $subscription->status !== 'active'
                || (int) $subscription->revision !== $delivery->subscriptionRevision
                || ! hash_equals((string) $subscription->endpoint_url, $delivery->endpointSnapshot)
                || ! hash_equals((string) $subscription->topic, $delivery->topic)
                || (int) $subscription->topic_version !== $delivery->topicVersion) {
                $this->rejectClaim($delivery, 'webhook_endpoint_changed', $now);

                return null;
            }

            $decoded = JsonCanonical::decode((string) $subscription->key_refs);

            if (! is_array($decoded) || $decoded === [] || count($decoded) > 2
                || array_filter($decoded, is_string(...)) !== $decoded) {
                $this->rejectClaim($delivery, 'signing_unavailable', $now);

                return null;
            }

            /** @var list<string> $decoded */
            return $decoded;
        }, $this->transactionAttempts());
    }

    public function finish(WebhookDelivery $delivery, WebhookHttpResult $result): bool
    {
        if ($delivery->leaseOwner === null) {
            throw new InvalidArgumentException('Webhook finish requires a leased delivery.');
        }

        return $this->connection->transaction(function () use ($delivery, $result): bool {
            $now = $this->clock->now();
            $row = $this->connection->table(self::Table)
                ->where('id', $delivery->id)
                ->where('status', 'sending')
                ->where('lease_owner', $delivery->leaseOwner)
                ->where('attempt', $delivery->attempt)
                ->where('lease_until', '>', $now)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return false;
            }

            $current = $this->delivery($row);
            $decision = $this->retry->decide($current, $result, $now);
            $terminal = $decision['status'] !== 'retry';
            $changed = $this->connection->table(self::Table)
                ->where('id', $delivery->id)
                ->where('status', 'sending')
                ->where('lease_owner', $delivery->leaseOwner)
                ->where('attempt', $delivery->attempt)
                ->where('lease_until', '>', $now)
                ->update([
                    'status' => $decision['status'],
                    'lease_owner' => null,
                    'lease_until' => null,
                    'next_attempt_at' => $decision['next'],
                    'finished_at' => $terminal ? $now : null,
                    'last_error' => $decision['error'],
                ]);

            if ($changed !== 1) {
                return false;
            }

            $this->finishAttempt($delivery, $result, $decision['status'], $decision['error'], $now);

            if ($result->status === 410) {
                $this->disableGoneSubscription($delivery, $now);
            }

            return true;
        }, $this->transactionAttempts());
    }

    private function assertScope(DataScope $scope): void
    {
        $context = $this->contexts->restore($scope);
        $actual = $context->isBoxed()
            ? 'boxed:'.$context->account->id
            : $this->connection->getDriverName().':'.$this->connection->getDatabaseName();

        if (! hash_equals($scope->connectionIdentity, $actual) || ! hash_equals($scope->accountId, $context->account->id)) {
            throw ScopeContextMismatch::connection(expected: $scope->connectionIdentity, actual: $actual);
        }
    }

    private function recoverExpired(DateTimeImmutable $now): void
    {
        $query = $this->connection->table(self::Table)
            ->where('status', 'sending')
            ->where('lease_until', '<=', $now)
            ->orderBy('id');
        $query->lockForUpdate();

        foreach ($query->get() as $row) {
            $delivery = $this->delivery($row);
            $horizon = $delivery->createdAt->add(new DateInterval('PT72H'));
            $dead = $delivery->attempt >= 12 || $now >= $horizon;
            $error = $dead ? 'retry_exhausted' : 'ack_unknown';
            $this->connection->table('sys_api_webhook_attempts')
                ->where('delivery_id', $delivery->id)
                ->where('attempt', $delivery->attempt)
                ->where('lease_owner', $delivery->leaseOwner)
                ->where('outcome', 'pending')
                ->update([
                    'finished_at' => $now,
                    'outcome' => 'unknown',
                    'error_code' => 'ack_unknown',
                ]);
            $this->connection->table(self::Table)
                ->where('id', $delivery->id)
                ->where('status', 'sending')
                ->where('lease_owner', $delivery->leaseOwner)
                ->where('attempt', $delivery->attempt)
                ->where('lease_until', '<=', $now)
                ->update([
                    'status' => $dead ? 'dead' : 'retry',
                    'lease_owner' => null,
                    'lease_until' => null,
                    'next_attempt_at' => $now,
                    'finished_at' => $dead ? $now : null,
                    'last_error' => $error,
                ]);
        }
    }

    private function rejectClaim(WebhookDelivery $delivery, string $error, DateTimeImmutable $now): void
    {
        $this->connection->table(self::Table)
            ->where('id', $delivery->id)
            ->where('status', 'sending')
            ->where('lease_owner', $delivery->leaseOwner)
            ->where('attempt', $delivery->attempt)
            ->update([
                'status' => 'dead',
                'lease_owner' => null,
                'lease_until' => null,
                'finished_at' => $now,
                'last_error' => $error,
            ]);
        $this->connection->table('sys_api_webhook_attempts')
            ->where('delivery_id', $delivery->id)
            ->where('attempt', $delivery->attempt)
            ->where('lease_owner', $delivery->leaseOwner)
            ->where('outcome', 'pending')
            ->update([
                'finished_at' => $now,
                'outcome' => 'dead',
                'error_code' => $error,
            ]);
    }

    private function finishAttempt(
        WebhookDelivery $delivery,
        WebhookHttpResult $result,
        string $outcome,
        ?string $error,
        DateTimeImmutable $now,
    ): void {
        $this->connection->table('sys_api_webhook_attempts')
            ->where('delivery_id', $delivery->id)
            ->where('attempt', $delivery->attempt)
            ->where('lease_owner', $delivery->leaseOwner)
            ->where('outcome', 'pending')
            ->update([
                'finished_at' => $now,
                'outcome' => $outcome,
                'http_status' => $result->status,
                'duration_ms' => $result->durationMs,
                'error_code' => $error,
            ]);
    }

    private function disableGoneSubscription(WebhookDelivery $delivery, DateTimeImmutable $now): void
    {
        $this->connection->table('sys_api_webhook_subscriptions')
            ->where('id', $delivery->subscriptionId)
            ->where('status', 'active')
            ->update([
                'status' => 'disabled',
                'revision' => $this->connection->raw('revision + 1'),
                'updated_at' => $now,
            ]);
        $this->connection->table(self::Table)
            ->where('subscription_id', $delivery->subscriptionId)
            ->whereIn('status', ['pending', 'retry'])
            ->update([
                'status' => 'dead',
                'finished_at' => $now,
                'last_error' => 'endpoint_gone',
            ]);
    }

    private function lockClaimQuery(Builder $query): void
    {
        if ($this->connection->getDriverName() === 'pgsql') {
            $query->lock('for update skip locked');

            return;
        }

        $query->lockForUpdate();
    }

    private function requiredRow(string $id): object
    {
        return $this->connection->table(self::Table)->where('id', $id)->first()
            ?? throw new RuntimeException('Webhook delivery disappeared during a locked transition.');
    }

    private function assertIdentifier(string $value, string $name): void
    {
        if ($value === '' || strlen($value) > 64 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            throw new InvalidArgumentException("Webhook {$name} is invalid.");
        }
    }

    private function transactionAttempts(): int
    {
        return $this->connection->getDriverName() === 'pgsql' ? 3 : 1;
    }

    private function delivery(object $row): WebhookDelivery
    {
        /** @var array<string, scalar|null> $scope */
        $scope = JsonCanonical::decode((string) $row->scope);

        return new WebhookDelivery(
            id: (string) $row->id,
            subscriptionId: (string) $row->subscription_id,
            subscriptionRevision: (int) $row->subscription_revision,
            eventId: (string) $row->event_id,
            topic: (string) $row->topic,
            topicVersion: (int) $row->topic_version,
            major: (string) $row->major,
            endpointSnapshot: (string) $row->endpoint_snapshot,
            scope: $scope,
            body: (string) $row->body,
            bodyHash: (string) $row->body_hash,
            status: (string) $row->status,
            attempt: (int) $row->attempt,
            replayCount: (int) $row->replay_count,
            leaseOwner: $row->lease_owner !== null ? (string) $row->lease_owner : null,
            leaseUntil: $row->lease_until !== null ? $this->instant($row->lease_until) : null,
            nextAttemptAt: $this->instant($row->next_attempt_at),
            createdAt: $this->instant($row->created_at),
            finishedAt: $row->finished_at !== null ? $this->instant($row->finished_at) : null,
            expiresAt: $this->instant($row->expires_at),
            lastError: $row->last_error !== null ? (string) $row->last_error : null,
        );
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable((string) $value);
    }
}
