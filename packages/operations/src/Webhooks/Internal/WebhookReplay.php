<?php

declare(strict_types=1);

namespace CoreX\Api\Internal\Webhook;

use CoreX\Api\ApiError;
use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Enums\IdempotencyRequirement;
use CoreX\Api\Enums\OperationMode;
use CoreX\Api\Internal\OrderingRegistry;
use CoreX\Api\Internal\SignedCursorCodec;
use CoreX\Api\Operation;
use CoreX\Audit\Contracts\AuditEntry;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Enums\ActorType;
use CoreX\Enums\ErrorCategory;
use CoreX\Exceptions\ScopeContextMismatch;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Psr\Clock\ClockInterface;

/** @internal Authorized same-delivery replay with finite cycles and range selection. */
final readonly class WebhookReplay
{
    private const string RangeOrdering = 'webhook-replay-range.created.v1';

    private const array RangeFields = ['id'];

    public function __construct(
        private Connection $connection,
        private TenantContextRestorer $contexts,
        private WebhookTopicCatalogue $topics,
        private ClockInterface $clock,
        private SignedCursorCodec $cursors,
        private AuditLogger $audit,
    ) {}

    /** @return array<string, mixed> */
    public function one(
        DataScope $scope,
        string $deliveryId,
        bool $explicit,
        ?string $auditReason,
    ): array {
        $this->assertScope($scope);

        return $this->connection->transaction(function () use ($scope, $deliveryId, $explicit, $auditReason): array {
            $row = $this->scopedDeliveries($scope)->where('id', $deliveryId)->lockForUpdate()->first();

            if ($row === null) {
                throw new ApiError('resource_not_found', 404, 'Webhook delivery does not exist.', category: ErrorCategory::NotFound);
            }

            return $this->replayLocked($scope, $row, $explicit, $auditReason);
        }, $this->transactionAttempts());
    }

    /**
     * Parent operation idempotency stores this complete selection/outcome; this method never widens one invocation.
     *
     * @return array{selected: list<string>, requeued: int, skipped: list<array{id: string, code: string}>, conflicts: int, next: string|null}
     */
    public function range(
        DataScope $scope,
        string $clientId,
        string $subscriptionId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $max,
        ?string $cursor,
        string $auditReason,
    ): array {
        $this->assertScope($scope);
        $this->assertRange($clientId, $subscriptionId, $from, $to, $max, $auditReason);
        $filters = [
            'client_id' => $clientId,
            'from' => $from->format('Y-m-d\TH:i:s.uP'),
            'subscription_id' => $subscriptionId,
            'to' => $to->format('Y-m-d\TH:i:s.uP'),
        ];
        $registry = self::rangeOrdering();
        $upper = $to < $this->clock->now() ? $to : $this->clock->now();
        $afterCreated = null;
        $afterId = null;

        if ($cursor !== null) {
            $position = $this->cursors->decode(
                $cursor,
                $registry,
                $scope->accountId,
                $scope->workspaceId,
                'v1',
                'webhook-deliveries.range-replay',
                $filters,
                self::RangeFields,
                self::RangeOrdering,
            );
            [$encodedUpper, $afterCreated, $afterId] = $position->values;
            $upper = new DateTimeImmutable((string) $encodedUpper);
        }

        $query = $this->scopedDeliveries($scope)
            ->where('subscription_id', $subscriptionId)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $upper);

        if ($afterCreated !== null && $afterId !== null) {
            $query->where(static function (Builder $query) use ($afterCreated, $afterId): void {
                $instant = new DateTimeImmutable((string) $afterCreated);
                $query->where('created_at', '>', $instant)
                    ->orWhere(static function (Builder $query) use ($instant, $afterId): void {
                        $query->where('created_at', $instant)->where('id', '>', (string) $afterId);
                    });
            });
        }

        $rows = $query->orderBy('created_at')->orderBy('id')->limit($max + 1)->get();
        $hasMore = $rows->count() > $max;
        $page = $rows->take($max)->values();
        $selected = [];
        $skipped = [];
        $requeued = 0;
        $conflicts = 0;

        foreach ($page as $row) {
            $id = (string) $row->id;
            $selected[] = $id;

            try {
                $this->one($scope, $id, true, $auditReason);
                $requeued++;
            } catch (ApiError $error) {
                if ($error->errorDescriptor()->category === ErrorCategory::Conflict) {
                    $conflicts++;

                    continue;
                }

                $skipped[] = ['id' => $id, 'code' => $error->errorCode];
            }
        }

        $last = $hasMore ? $page->last() : null;
        $next = $last === null ? null : $this->cursors->encode(
            registry: $registry,
            accountId: $scope->accountId,
            workspaceId: $scope->workspaceId,
            apiMajor: 'v1',
            operationId: 'webhook-deliveries.range-replay',
            filters: $filters,
            fields: self::RangeFields,
            orderingId: self::RangeOrdering,
            values: [
                $upper->format('Y-m-d\TH:i:s.uP'),
                $this->instant($last->created_at)->format('Y-m-d\TH:i:s.uP'),
                (string) $last->id,
            ],
        );

        return compact('selected', 'requeued', 'skipped', 'conflicts', 'next');
    }

    /** @return array<string, mixed> */
    private function replayLocked(DataScope $scope, object $row, bool $explicit, ?string $auditReason): array
    {
        $now = $this->clock->now();
        $status = (string) $row->status;

        if ($status === 'sending' && $row->lease_until !== null && $this->instant($row->lease_until) > $now) {
            throw new ApiError('conflict', 409, 'Webhook delivery has an active lease.', category: ErrorCategory::Conflict);
        }

        if (! in_array($status, ['dead', 'succeeded'], true)) {
            throw new ApiError('conflict', 409, 'Only a terminal webhook delivery can be replayed.', category: ErrorCategory::Conflict);
        }

        if ((int) $row->replay_count >= 3) {
            throw new ApiError('conflict', 409, 'Webhook replay cycle limit is three.', category: ErrorCategory::Conflict);
        }

        if ($this->instant($row->expires_at) <= $now
            || $this->instant($row->created_at)->modify('+7 days') <= $now) {
            throw new ApiError('webhook_replay_expired', 410, 'Webhook replay body has expired.', category: ErrorCategory::Conflict);
        }

        if ($status === 'succeeded' && (! $explicit || ! $this->validReason($auditReason))) {
            throw new ApiError('validation_failed', 422, 'Succeeded replay requires explicit confirmation and an audit reason.', category: ErrorCategory::Validation);
        }

        if ($auditReason !== null && ! $this->validReason($auditReason)) {
            throw new ApiError('validation_failed', 422, 'Webhook replay audit reason is invalid.', category: ErrorCategory::Validation);
        }

        $subscription = $this->connection->table('sys_api_webhook_subscriptions')
            ->where('id', (string) $row->subscription_id)
            ->first();

        if ($subscription === null
            || (string) $subscription->status !== 'active'
            || (int) $subscription->revision !== (int) $row->subscription_revision
            || ! hash_equals((string) $subscription->endpoint_url, (string) $row->endpoint_snapshot)
            || ! hash_equals((string) $subscription->topic, (string) $row->topic)
            || (int) $subscription->topic_version !== (int) $row->topic_version
            || ! hash_equals((string) $subscription->major, (string) $row->major)) {
            throw new ApiError('webhook_endpoint_changed', 409, 'Webhook destination is no longer current.', category: ErrorCategory::Conflict);
        }

        try {
            $topic = $this->topics->get((string) $row->topic, (int) $row->topic_version);

            if (! hash_equals($topic->major, (string) $row->major)) {
                throw new ApiError('conflict', 409, 'Webhook topic is no longer available.', category: ErrorCategory::Conflict);
            }
        } catch (ApiError) {
            throw new ApiError('conflict', 409, 'Webhook topic is no longer available.', category: ErrorCategory::Conflict);
        }

        $changed = $this->connection->table('sys_api_webhook_deliveries')
            ->where('id', (string) $row->id)
            ->where('status', $status)
            ->where('replay_count', (int) $row->replay_count)
            ->whereNull('lease_owner')
            ->update([
                'status' => 'pending',
                'replay_count' => (int) $row->replay_count + 1,
                'lease_owner' => null,
                'lease_until' => null,
                'next_attempt_at' => $now,
                'finished_at' => null,
                'last_error' => null,
            ]);

        if ($changed !== 1) {
            throw new ApiError('conflict', 409, 'Webhook replay lost its compare-and-swap fence.', category: ErrorCategory::Conflict);
        }

        $context = [
            'previous_status' => $status,
            'replay_count' => (int) $row->replay_count + 1,
        ];

        if ($auditReason !== null) {
            $context['reason_sha256'] = hash('sha256', $auditReason);
            $context['reason_length'] = strlen($auditReason);
        }

        $this->audit->record(new AuditEntry(
            action: 'webhook.delivery.replayed',
            actorType: ActorType::ApiKey,
            actorId: $scope->actorId,
            entityType: 'webhook_delivery',
            entityId: (string) $row->id,
            context: $context,
            occurredAt: $now,
            workspaceId: $scope->workspaceId,
        ));

        return [
            'attempt' => (int) $row->attempt,
            'body_hash' => (string) $row->body_hash,
            'id' => (string) $row->id,
            'replay_count' => (int) $row->replay_count + 1,
            'status' => 'pending',
            'subscription_id' => (string) $row->subscription_id,
        ];
    }

    private function assertRange(
        string $clientId,
        string $subscriptionId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $max,
        string $auditReason,
    ): void {
        $seconds = $to->getTimestamp() - $from->getTimestamp();

        if ($clientId === '' || strlen($clientId) > 128 || $subscriptionId === '' || strlen($subscriptionId) > 64
            || $from->getOffset() !== 0 || $to->getOffset() !== 0 || $seconds <= 0 || $seconds > 86_400
            || $max < 1 || $max > 100 || ! $this->validReason($auditReason)) {
            throw new ApiError('validation_failed', 422, 'Webhook replay range is invalid.', category: ErrorCategory::Validation);
        }
    }

    private function validReason(?string $reason): bool
    {
        return is_string($reason) && $reason !== '' && strlen($reason) <= 500
            && preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $reason) !== 1;
    }

    private function assertScope(DataScope $scope): void
    {
        $context = $this->contexts->restore($scope);
        $actual = $context->isBoxed()
            ? 'boxed:'.$context->account->id
            : $this->connection->getDriverName().':'.$this->connection->getDatabaseName();

        if (! hash_equals($scope->connectionIdentity, $actual) || ! hash_equals($scope->accountId, $context->account->id)) {
            throw ScopeContextMismatch::connection($scope->connectionIdentity, $actual);
        }
    }

    private function scopedDeliveries(DataScope $scope): Builder
    {
        $query = $this->connection->table('sys_api_webhook_deliveries');
        $accountPath = $this->connection->getDriverName() === 'pgsql'
            ? "scope->>'account_id'"
            : "json_extract(scope, '$.account_id')";
        $workspacePath = $this->connection->getDriverName() === 'pgsql'
            ? "scope->>'workspace_id'"
            : "json_extract(scope, '$.workspace_id')";
        $query->whereRaw("{$accountPath} = ?", [$scope->accountId]);

        if ($scope->workspaceId === null) {
            $query->whereRaw("{$workspacePath} IS NULL");
        } else {
            $query->whereRaw("{$workspacePath} = ?", [$scope->workspaceId]);
        }

        return $query;
    }

    private static function rangeOrdering(): OrderingRegistry
    {
        return OrderingRegistry::fromOperations([new Operation(
            module: 'corex/operations',
            id: 'webhook-deliveries.range-replay',
            version: 'v1',
            method: 'POST',
            path: '/webhook-deliveries/replay',
            ability: 'webhooks.replay',
            handler: WebhookReplayCursorOperationHandler::class,
            requestSchema: [],
            responseSchema: [],
            inputRules: [],
            successStatus: 200,
            mode: OperationMode::Command,
            requiredScopes: ['webhooks.replay'],
            idempotency: IdempotencyRequirement::Required,
            pagination: [
                'ordering' => self::RangeOrdering,
                'direction' => 'asc',
                'fields' => ['upper_bound', 'created_at', 'id'],
                'types' => ['string', 'string', 'string'],
                'unique' => 'id',
            ],
            effectMode: 'tenant_transactional',
        )]);
    }

    private function transactionAttempts(): int
    {
        return $this->connection->getDriverName() === 'pgsql' ? 3 : 1;
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

/** @internal Metadata-only handler class for the shared P13 cursor registry. */
final class WebhookReplayCursorOperationHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return [];
    }
}
