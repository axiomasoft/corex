<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Webhooks\Contracts\WebhookEgressPolicy;
use CoreX\Webhooks\Contracts\WebhookSubscriptionStore;
use CoreX\Webhooks\Data\WebhookDestination;
use CoreX\Webhooks\Data\WebhookSubscription;
use CoreX\Webhooks\Errors\Enums\WebhooksErrorCode;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use InvalidArgumentException;
use Override;
use Packman\Attributes\Internal;
use Packman\Errors\Enums\ErrorCategory;
use Psr\Clock\ClockInterface;
use RuntimeException;

/** @internal Tenant-local webhook subscription authority. */
#[Internal]
final readonly class SqlWebhookSubscriptionStore implements WebhookSubscriptionStore
{
    public const int CAP = 100;

    private const string Table = 'sys_api_webhook_subscriptions';

    public function __construct(
        private Connection $connection,
        private TenantContextRestorer $contexts,
        private WebhookTopicCatalogue $catalogue,
        private ClockInterface $clock,
        private WebhookEgressPolicy $egress,
    ) {}

    #[Override]
    public function create(
        DataScope $scope,
        WebhookSubscription $subscription,
        bool $sensitiveAbility,
        bool $policyAllows,
    ): WebhookSubscription {
        $this->assertScope($scope);
        $topic = $this->catalogue->get(name: $subscription->topic, version: $subscription->topicVersion);
        $this->catalogue->admit(topic: $topic, sensitiveAbility: $sensitiveAbility, policyAllows: $policyAllows);

        if ($subscription->maxPiiClass !== $topic->piiClass || $subscription->major !== $topic->major) {
            throw new ApiError(errorCode: OperationsErrorCode::UnknownMessageContract, status: 422, message: 'The subscription does not match the declared topic.', category: ErrorCategory::Validation);
        }

        $this->assertEgress(scope: $scope, url: $subscription->endpointUrl);

        return $this->connection->transaction(function () use ($subscription): WebhookSubscription {
            if ($this->connection->table(self::Table)->count() >= self::CAP) {
                throw new ApiError(errorCode: WebhooksErrorCode::WebhookSubscriptionCap, status: 422, message: 'The webhook subscription cap is 100.', category: ErrorCategory::Validation);
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
        $topic = $this->catalogue->get(name: $current->topic, version: $current->topicVersion);
        $this->catalogue->admit(topic: $topic, sensitiveAbility: $sensitiveAbility, policyAllows: $policyAllows);
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

        $this->assertEgress(scope: $scope, url: $replacement->endpointUrl);

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
                throw new ApiError(errorCode: OperationsErrorCode::Conflict, status: 409, message: 'The webhook subscription revision is stale.', category: ErrorCategory::Conflict);
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
                throw new ApiError(errorCode: OperationsErrorCode::Conflict, status: 409, message: 'The webhook subscription revision is stale.', category: ErrorCategory::Conflict);
            }

            return $this->requireRow($id);
        }, $this->transactionAttempts());
    }

    private function assertEgress(DataScope $scope, string $url): void
    {
        if (! $this->egress->allows(scope: $scope, destination: WebhookDestination::fromUrl($url))) {
            throw new ApiError(errorCode: WebhooksErrorCode::WebhookEgressDenied, status: 422, message: 'Webhook destination is denied by tenant egress policy.', category: ErrorCategory::Validation);
        }
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
            throw new ApiError(errorCode: OperationsErrorCode::NotFound, status: 404, message: 'The webhook subscription does not exist.', category: ErrorCategory::NotFound);
        }

        return $this->subscription($row);
    }

    private function subscription(object $row): WebhookSubscription
    {
        $decoded = JsonCanonical::decode(DatabaseValue::text(DatabaseValue::field(row: $row, name: 'key_refs')));

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException('Stored webhook key references must be a list.');
        }
        $keyRefs = [];
        foreach ($decoded as $keyRef) {
            if (! is_string($keyRef)) {
                throw new RuntimeException('Stored webhook key references must contain strings.');
            }
            $keyRefs[] = $keyRef;
        }

        return new WebhookSubscription(
            id: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'id')),
            revision: DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'revision')),
            major: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'major')),
            topic: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'topic')),
            topicVersion: DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'topic_version')),
            endpointUrl: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'endpoint_url')),
            status: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'status')),
            maxPiiClass: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'max_pii_class')),
            keyRefs: $keyRefs,
            createdAt: $this->instant(DatabaseValue::field(row: $row, name: 'created_at')),
            updatedAt: $this->instant(DatabaseValue::field(row: $row, name: 'updated_at')),
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

    /** @return int<1, 3> */
    private function transactionAttempts(): int
    {
        return $this->connection->getDriverName() === 'pgsql' ? 3 : 1;
    }
}
