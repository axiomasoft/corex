<?php

declare(strict_types=1);

namespace CoreX\Api\Internal\Webhook;

use CoreX\Api\Contracts\WebhookDeliveryStore;
use CoreX\Api\Data\WebhookDelivery;
use CoreX\Api\Internal\JsonCanonical;
use CoreX\Exceptions\ScopeContextMismatch;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Override;

/** @internal Tenant-local webhook delivery rows. Network send stays outside this store. */
final readonly class SqlWebhookDeliveryStore implements WebhookDeliveryStore
{
    private const string Table = 'sys_api_webhook_deliveries';

    public function __construct(
        private Connection $connection,
        private TenantContextRestorer $contexts,
    ) {}

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
