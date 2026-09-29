<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\DataScope;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Operations\Errors\ApiError;
use CoreX\Operations\Validation\JsonCanonical;
use CoreX\Webhooks\Contracts\WebhookSubscriptionStore;
use CoreX\Webhooks\Data\WebhookSubscription;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use InvalidArgumentException;
use Override;
use Psr\Clock\ClockInterface;

/** @internal Tenant-local webhook subscription authority. */
final readonly class SqlWebhookSubscriptionStore implements WebhookSubscriptionStore
{
    public const int CAP = 100;

    private const string Table = 'sys_api_webhook_subscriptions';

    public function __construct(
        private Connection $connection,
        private TenantContextRestorer $contexts,
        private WebhookTopicCatalogue $catalogue,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function create(
        DataScope $scope,
        WebhookSubscription $subscription,
        bool $sensitiveAbility,
        bool $policyAllows,
    ): WebhookSubscription {
        $this->assertScope($scope);
        $topic = $this->catalogue->get($subscription->topic, $subscription->topicVersion);
        $this->catalogue->admit($topic, $sensitiveAbility, $policyAllows);

        if ($subscription->maxPiiClass !== $topic->piiClass || $subscription->major !== $topic->major) {
            throw new ApiError('unknown_message_contract', 422, 'The subscription does not match the declared topic.', category: ErrorCategory::Validation);
        }

        return $this->connection->transaction(function () use ($subscription): WebhookSubscription {
            if ((int) $this->connection->table(self::Table)->count() >= self::CAP) {
                throw new ApiError('webhook_subscription_cap', 422, 'The webhook subscription cap is 100.', category: ErrorCategory::Validation);
            }

            $now = $this->clock->now();
            $this->connection->table(self::Table)->insert([
                'id' => $subscription->id,
                'revision' => 0,
                'major' => $subscription->major,
                'topic' => $subscription->topic,
                'topic_version' => $subscription->topicVersion,
                'endpoint_url' => $subscription->endpointUrl,
                'status' => 'active',
                'max_pii_class' => $subscription->maxPiiClass,
                'key_refs' => JsonCanonical::encode($subscription->keyRefs),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $this->requireRow($subscription->id);
        }, $this->transactionAttempts());
    }

    #[Override]
    public function update(
        DataScope $scope,
        string $id,
        int $expectedRevision,
        string $endpointUrl,
        array $keyRefs,
        bool $sensitiveAbility,
        bool $policyAllows,
    ): WebhookSubscription {
        $this->assertScope($scope);
        $this->assertRevision($expectedRevision);
        $current = $this->requireRow($id);
        $topic = $this->catalogue->get($current->topic, $current->topicVersion);
        $this->catalogue->admit($topic, $sensitiveAbility, $policyAllows);
        $replacement = new WebhookSubscription(
            id: $current->id,
            revision: $current->revision,
            major: $current->major,
            topic: $current->topic,
            topicVersion: $current->topicVersion,
            endpointUrl: $endpointUrl,
            status: $current->status,
            maxPiiClass: $current->maxPiiClass,
            keyRefs: $keyRefs,
            createdAt: $current->createdAt,
            updatedAt: $current->updatedAt,
        );

        return $this->connection->transaction(function () use ($id, $expectedRevision, $replacement): WebhookSubscription {
            $updated = $this->connection->table(self::Table)
                ->where('id', $id)
                ->where('revision', $expectedRevision)
                ->update([
                    'endpoint_url' => $replacement->endpointUrl,
                    'key_refs' => JsonCanonical::encode($replacement->keyRefs),
                    'revision' => $expectedRevision + 1,
                    'updated_at' => $this->clock->now(),
                ]);

            if ($updated !== 1) {
                throw new ApiError('conflict', 409, 'The webhook subscription revision is stale.', category: ErrorCategory::Conflict);
            }

            return $this->requireRow($id);
        }, $this->transactionAttempts());
    }

    #[Override]
    public function find(DataScope $scope, string $id): ?WebhookSubscription
    {
        $this->assertScope($scope);

        $row = $this->connection->table(self::Table)->where('id', $id)->first();

        return $row === null ? null : $this->subscription($row);
    }

    #[Override]
    public function disable(DataScope $scope, string $id, int $expectedRevision): WebhookSubscription
    {
        $this->assertScope($scope);
        $this->assertRevision($expectedRevision);

        return $this->connection->transaction(function () use ($id, $expectedRevision): WebhookSubscription {
            $updated = $this->connection->table(self::Table)
                ->where('id', $id)
                ->where('revision', $expectedRevision)
                ->where('status', 'active')
                ->update([
                    'status' => 'disabled',
                    'revision' => $expectedRevision + 1,
                    'updated_at' => $this->clock->now(),
                ]);

            if ($updated !== 1) {
                throw new ApiError('conflict', 409, 'The webhook subscription revision is stale.', category: ErrorCategory::Conflict);
            }

            return $this->requireRow($id);
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

    private function assertRevision(int $expectedRevision): void
    {
        if ($expectedRevision < 0) {
            throw new InvalidArgumentException('Webhook expected revision must be non-negative.');
        }
    }

    private function requireRow(string $id): WebhookSubscription
    {
        $row = $this->connection->table(self::Table)->where('id', $id)->first();

        if ($row === null) {
            throw new ApiError('not_found', 404, 'The webhook subscription does not exist.', category: ErrorCategory::NotFound);
        }

        return $this->subscription($row);
    }

    private function subscription(object $row): WebhookSubscription
    {
        /** @var list<string> $keyRefs */
        $keyRefs = JsonCanonical::decode((string) $row->key_refs);

        return new WebhookSubscription(
            id: (string) $row->id,
            revision: (int) $row->revision,
            major: (string) $row->major,
            topic: (string) $row->topic,
            topicVersion: (int) $row->topic_version,
            endpointUrl: (string) $row->endpoint_url,
            status: (string) $row->status,
            maxPiiClass: (string) $row->max_pii_class,
            keyRefs: $keyRefs,
            createdAt: $this->instant($row->created_at),
            updatedAt: $this->instant($row->updated_at),
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

    private function transactionAttempts(): int
    {
        return $this->connection->getDriverName() === 'pgsql' ? 3 : 1;
    }
}
