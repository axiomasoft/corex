<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Webhooks\Contracts\WebhookDeliveryStore;
use CoreX\Webhooks\Data\WebhookDelivery;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Enums\WebhookFailureReason;
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

            $attempt = DatabaseValue::integer(DatabaseValue::field($row, 'attempt')) + 1;
            $leaseUntil = $now->add(new DateInterval("PT{$this->leaseSeconds}S"));
            $changed = $this->connection->table(self::Table)
                ->where('id', DatabaseValue::text(DatabaseValue::field($row, 'id')))
                ->where('status', DatabaseValue::text(DatabaseValue::field($row, 'status')))
                ->where('attempt', DatabaseValue::integer(DatabaseValue::field($row, 'attempt')))
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
                'id' => substr(hash('sha256', DatabaseValue::text(DatabaseValue::field($row, 'id')).'|'.$attempt), 0, 32),
                'delivery_id' => DatabaseValue::field($row, 'id'),
                'attempt' => $attempt,
                'lease_owner' => $worker,
                'started_at' => $now,
                'finished_at' => null,
                'outcome' => 'pending',
                'http_status' => null,
                'duration_ms' => null,
                'error_code' => null,
            ]);

            return $this->delivery($this->requiredRow(DatabaseValue::text(DatabaseValue::field($row, 'id'))));
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
                || DatabaseValue::text($subscription->status) !== 'active'
                || DatabaseValue::integer($subscription->revision) !== $delivery->subscriptionRevision
                || ! hash_equals(DatabaseValue::text($subscription->endpoint_url), $delivery->endpointSnapshot)
                || ! hash_equals(DatabaseValue::text($subscription->topic), $delivery->topic)
                || DatabaseValue::integer($subscription->topic_version) !== $delivery->topicVersion) {
                $this->rejectClaim($delivery, WebhookFailureReason::EndpointChanged, $now);

                return null;
            }

            $decoded = JsonCanonical::decode(DatabaseValue::text($subscription->key_refs));

            if (! is_array($decoded) || ! array_is_list($decoded) || $decoded === [] || count($decoded) > 2
                || array_filter($decoded, is_string(...)) !== $decoded) {
                $this->rejectClaim($delivery, WebhookFailureReason::SigningUnavailable, $now);

                return null;
            }

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
                    'last_error' => $decision['error']?->value,
                ]);

            if ($changed !== 1) {
                return false;
            }

            $this->finishAttempt($delivery, $result, $decision['status'], $decision['error'], $now);

            if ($result->status === 410 && $decision['error'] === WebhookFailureReason::EndpointGone) {
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
            $failure = $this->retry->budgetFailure($delivery, $now);
            $dead = $failure !== null;
            $error = $failure ?? WebhookFailureReason::AckUnknown;
            $this->connection->table('sys_api_webhook_attempts')
                ->where('delivery_id', $delivery->id)
                ->where('attempt', $delivery->attempt)
                ->where('lease_owner', $delivery->leaseOwner)
                ->where('outcome', 'pending')
                ->update([
                    'finished_at' => $now,
                    'outcome' => 'unknown',
                    'error_code' => WebhookFailureReason::AckUnknown->value,
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
                    'last_error' => $error->value,
                ]);
        }
    }

    private function rejectClaim(WebhookDelivery $delivery, WebhookFailureReason $error, DateTimeImmutable $now): void
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
                'last_error' => $error->value,
            ]);
        $this->connection->table('sys_api_webhook_attempts')
            ->where('delivery_id', $delivery->id)
            ->where('attempt', $delivery->attempt)
            ->where('lease_owner', $delivery->leaseOwner)
            ->where('outcome', 'pending')
            ->update([
                'finished_at' => $now,
                'outcome' => 'dead',
                'error_code' => $error->value,
            ]);
    }

    private function finishAttempt(
        WebhookDelivery $delivery,
        WebhookHttpResult $result,
        string $outcome,
        ?WebhookFailureReason $error,
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
                'error_code' => $error?->value,
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
                'last_error' => WebhookFailureReason::EndpointGone->value,
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

    /** @return int<1, 3> */
    private function transactionAttempts(): int
    {
        return $this->connection->getDriverName() === 'pgsql' ? 3 : 1;
    }

    private function delivery(object $row): WebhookDelivery
    {
        $decodedScope = JsonCanonical::decode(DatabaseValue::text(DatabaseValue::field($row, 'scope')));

        if (! is_array($decodedScope)) {
            throw new RuntimeException('Stored webhook scope must be an object.');
        }
        $scope = [];
        foreach ($decodedScope as $key => $value) {
            if (! is_string($key) || (! is_scalar($value) && $value !== null)) {
                throw new RuntimeException('Stored webhook scope must contain named scalar fields.');
            }
            $scope[$key] = $value;
        }

        return new WebhookDelivery(
            id: DatabaseValue::text(DatabaseValue::field($row, 'id')),
            subscriptionId: DatabaseValue::text(DatabaseValue::field($row, 'subscription_id')),
            subscriptionRevision: DatabaseValue::integer(DatabaseValue::field($row, 'subscription_revision')),
            eventId: DatabaseValue::text(DatabaseValue::field($row, 'event_id')),
            topic: DatabaseValue::text(DatabaseValue::field($row, 'topic')),
            topicVersion: DatabaseValue::integer(DatabaseValue::field($row, 'topic_version')),
            major: DatabaseValue::text(DatabaseValue::field($row, 'major')),
            endpointSnapshot: DatabaseValue::text(DatabaseValue::field($row, 'endpoint_snapshot')),
            scope: $scope,
            body: DatabaseValue::text(DatabaseValue::field($row, 'body')),
            bodyHash: DatabaseValue::text(DatabaseValue::field($row, 'body_hash')),
            status: DatabaseValue::text(DatabaseValue::field($row, 'status')),
            attempt: DatabaseValue::integer(DatabaseValue::field($row, 'attempt')),
            replayCount: DatabaseValue::integer(DatabaseValue::field($row, 'replay_count')),
            leaseOwner: DatabaseValue::field($row, 'lease_owner') !== null ? DatabaseValue::text(DatabaseValue::field($row, 'lease_owner')) : null,
            leaseUntil: DatabaseValue::field($row, 'lease_until') !== null ? $this->instant(DatabaseValue::field($row, 'lease_until')) : null,
            nextAttemptAt: $this->instant(DatabaseValue::field($row, 'next_attempt_at')),
            createdAt: $this->instant(DatabaseValue::field($row, 'created_at')),
            finishedAt: DatabaseValue::field($row, 'finished_at') !== null ? $this->instant(DatabaseValue::field($row, 'finished_at')) : null,
            expiresAt: $this->instant(DatabaseValue::field($row, 'expires_at')),
            lastError: DatabaseValue::field($row, 'last_error') !== null ? DatabaseValue::text(DatabaseValue::field($row, 'last_error')) : null,
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

        return new DateTimeImmutable(DatabaseValue::text($value));
    }
}
