<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Pagination\Contracts\CursorCodec;
use CoreX\Operations\Pagination\Contracts\OrderingRegistry;
use CoreX\Operations\Pagination\Laravel\OrderingFactory;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Webhooks\Errors\Enums\WebhooksErrorCode;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Packman\Errors\Enums\ErrorCategory;
use Psr\Clock\ClockInterface;

/** @internal Authorized replay creating a distinct delivery with finite cycles and range selection. */
#[Internal]
final readonly class WebhookReplay
{
    private const string RangeOrdering = 'webhook-replay-range.created.v1';

    private const array RangeFields = ['id'];

    public function __construct(
        private Connection $connection,
        private CurrentWorkScope $contexts,
        private WebhookTopicCatalogue $topics,
        private ClockInterface $clock,
        private CursorCodec $cursors,
        private AuditLogger $audit,
        private StoredWorkIssuer $issuer,
        private DataScopeCodec $scopes,
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
            $this->assertScope($scope);
            $row = $this->scopedDeliveries($scope)->where('id', $deliveryId)->lockForUpdate()->first();

            if ($row === null) {
                throw new ApiError(errorCode: OperationsErrorCode::ResourceNotFound, status: 404, message: 'Webhook delivery does not exist.', category: ErrorCategory::NotFound);
            }

            return $this->replayLocked(scope: $scope, row: $row, explicit: $explicit, auditReason: $auditReason);
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
        $this->assertRange(clientId: $clientId, subscriptionId: $subscriptionId, from: $from, to: $to, max: $max, auditReason: $auditReason);
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
                cursor: $cursor,
                registry: $registry,
                accountId: $scope->accountId,
                workspaceId: $scope->workspaceId,
                apiMajor: 'v1',
                operationId: 'webhook-deliveries.range-replay',
                filters: $filters,
                fields: self::RangeFields,
                orderingId: self::RangeOrdering,
            );
            [$encodedUpper, $afterCreated, $afterId] = $position->values;
            $upper = new DateTimeImmutable((string) $encodedUpper);
        }

        $query = $this->scopedDeliveries($scope)
            ->where('subscription_id', $subscriptionId)->whereNull('replay_of')
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

        $rows = $query->oldest()->orderBy('id')->limit($max + 1)->get();
        $hasMore = $rows->count() > $max;
        $page = $rows->take($max)->values();
        $selected = [];
        $skipped = [];
        $requeued = 0;
        $conflicts = 0;

        foreach ($page as $row) {
            $id = DatabaseValue::text(DatabaseValue::field(row: $row, name: 'id'));
            $selected[] = $id;

            try {
                $this->one(scope: $scope, deliveryId: $id, explicit: true, auditReason: $auditReason);
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
                $this->instant(DatabaseValue::field(row: $last, name: 'created_at'))->format('Y-m-d\TH:i:s.uP'),
                DatabaseValue::text(DatabaseValue::field(row: $last, name: 'id')),
            ],
        );

        return ['selected' => $selected, 'requeued' => $requeued, 'skipped' => $skipped, 'conflicts' => $conflicts, 'next' => $next];
    }

    /** @return array<string, mixed> */
    private function replayLocked(DataScope $scope, object $row, bool $explicit, ?string $auditReason): array
    {
        $now = $this->clock->now();
        $status = DatabaseValue::text(DatabaseValue::field(row: $row, name: 'status'));

        if ($status === 'sending' && DatabaseValue::field(row: $row, name: 'lease_until') !== null && $this->instant(DatabaseValue::field(row: $row, name: 'lease_until')) > $now) {
            throw new ApiError(errorCode: OperationsErrorCode::Conflict, status: 409, message: 'Webhook delivery has an active lease.', category: ErrorCategory::Conflict);
        }

        if (! in_array($status, ['dead', 'succeeded'], true)) {
            throw new ApiError(errorCode: OperationsErrorCode::Conflict, status: 409, message: 'Only a terminal webhook delivery can be replayed.', category: ErrorCategory::Conflict);
        }

        if (DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'replay_count')) >= 3) {
            throw new ApiError(errorCode: OperationsErrorCode::Conflict, status: 409, message: 'Webhook replay cycle limit is three.', category: ErrorCategory::Conflict);
        }

        if ($this->instant(DatabaseValue::field(row: $row, name: 'expires_at')) <= $now
            || $this->instant(DatabaseValue::field(row: $row, name: 'created_at'))->modify('+7 days') <= $now) {
            throw new ApiError(errorCode: WebhooksErrorCode::WebhookReplayExpired, status: 410, message: 'Webhook replay body has expired.', category: ErrorCategory::Conflict);
        }

        if ($status === 'succeeded' && (! $explicit || ! $this->validReason($auditReason))) {
            throw new ApiError(errorCode: OperationsErrorCode::ValidationFailed, status: 422, message: 'Succeeded replay requires explicit confirmation and an audit reason.', category: ErrorCategory::Validation);
        }

        if ($auditReason !== null && ! $this->validReason($auditReason)) {
            throw new ApiError(errorCode: OperationsErrorCode::ValidationFailed, status: 422, message: 'Webhook replay audit reason is invalid.', category: ErrorCategory::Validation);
        }

        $subscription = $this->connection->table('sys_api_webhook_subscriptions')
            ->where('id', DatabaseValue::text(DatabaseValue::field(row: $row, name: 'subscription_id')))
            ->first();

        if ($subscription === null
            || DatabaseValue::text($subscription->status) !== 'active'
            || DatabaseValue::integer($subscription->revision) !== DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'subscription_revision'))
            || ! hash_equals(DatabaseValue::text($subscription->endpoint_url), DatabaseValue::text(DatabaseValue::field(row: $row, name: 'endpoint_snapshot')))
            || ! hash_equals(DatabaseValue::text($subscription->topic), DatabaseValue::text(DatabaseValue::field(row: $row, name: 'topic')))
            || DatabaseValue::integer($subscription->topic_version) !== DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'topic_version'))
            || ! hash_equals(DatabaseValue::text($subscription->major), DatabaseValue::text(DatabaseValue::field(row: $row, name: 'major')))) {
            throw new ApiError(errorCode: WebhooksErrorCode::WebhookEndpointChanged, status: 409, message: 'Webhook destination is no longer current.', category: ErrorCategory::Conflict);
        }

        try {
            $topic = $this->topics->get(name: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'topic')), version: DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'topic_version')));

            if (! hash_equals($topic->major, DatabaseValue::text(DatabaseValue::field(row: $row, name: 'major')))) {
                throw new ApiError(errorCode: OperationsErrorCode::Conflict, status: 409, message: 'Webhook topic is no longer available.', category: ErrorCategory::Conflict);
            }
        } catch (ApiError) {
            throw new ApiError(errorCode: OperationsErrorCode::Conflict, status: 409, message: 'Webhook topic is no longer available.', category: ErrorCategory::Conflict);
        }

        if (! hash_equals(DatabaseValue::text(DatabaseValue::field($row, 'body_hash')), hash('sha256', DatabaseValue::text(DatabaseValue::field($row, 'body'))))) {
            throw new ApiError(errorCode: OperationsErrorCode::Conflict, status: 409, message: 'Webhook replay body no longer matches its receipt.', category: ErrorCategory::Conflict);
        }

        $original = DatabaseValue::text(DatabaseValue::field($row, 'id'));

        if (DatabaseValue::field($row, 'replay_of') !== null) {
            throw new ApiError(errorCode: OperationsErrorCode::Conflict, status: 409, message: 'Replay requires the original webhook delivery.', category: ErrorCategory::Conflict);
        }

        $attempt = DatabaseValue::integer(DatabaseValue::field($row, 'replay_count'));
        $changed = $this->scopedDeliveries($scope)->where('id', $original)->where('status', $status)
            ->where('replay_count', $attempt)->whereNull('lease_owner')
            ->update(['replay_count' => $attempt + 1]);

        if ($changed !== 1) {
            throw new ApiError(errorCode: OperationsErrorCode::Conflict, status: 409, message: 'Webhook replay lost its compare-and-swap fence.', category: ErrorCategory::Conflict);
        }

        $id = (string) Str::uuid7();
        $subscriptionId = DatabaseValue::text(DatabaseValue::field($row, 'subscription_id'));
        $source = DatabaseValue::text(DatabaseValue::field($row, 'event_source'));
        $event = DatabaseValue::text(DatabaseValue::field($row, 'event_id'));
        $key = WebhookWork::key($scope, $subscriptionId, $source, $event, $id);
        $digest = WebhookWork::digest(
            DatabaseValue::text(DatabaseValue::field($row, 'endpoint_snapshot')), DatabaseValue::text(DatabaseValue::field($row, 'body_hash')),
            DatabaseValue::text(DatabaseValue::field($row, 'topic')), DatabaseValue::integer(DatabaseValue::field($row, 'topic_version')), DatabaseValue::integer(DatabaseValue::field($row, 'subscription_revision')), DatabaseValue::text(DatabaseValue::field($row, 'major')),
        );
        $proof = $this->issuer->issue($scope, WebhookWork::Owner, WebhookWork::reference($id, $key, $digest));
        $this->connection->table('sys_api_webhook_deliveries')->insert([
            ...WebhookDeliveryScope::logical($scope),
            ...WebhookDeliveryScope::fence($scope),
            'id' => $id,
            'subscription_id' => $subscriptionId,
            'subscription_revision' => DatabaseValue::field($row, 'subscription_revision'),
            'event_id' => $event,
            'event_source' => $source,
            'replay_id' => $id,
            'replay_of' => $original,
            'effect_key' => $key,
            'scope_seal' => $proof->seal,
            'topic' => DatabaseValue::field($row, 'topic'),
            'topic_version' => DatabaseValue::field($row, 'topic_version'),
            'major' => DatabaseValue::field($row, 'major'),
            'endpoint_snapshot' => DatabaseValue::field($row, 'endpoint_snapshot'),
            'scope' => JsonCanonical::encode($this->scopes->encode($scope)),
            'body' => DatabaseValue::field($row, 'body'),
            'body_hash' => DatabaseValue::field($row, 'body_hash'),
            'status' => 'pending',
            'attempt' => 0,
            'replay_count' => 0,
            'next_attempt_at' => $now,
            'created_at' => $now,
            'expires_at' => DatabaseValue::field($row, 'expires_at'),
        ]);

        $context = [
            'previous_status' => $status,
            'replay_id' => $id,
            'replay_count' => DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'replay_count')) + 1,
        ];

        if ($auditReason !== null) {
            $context['reason_sha256'] = hash('sha256', $auditReason);
            $context['reason_length'] = strlen($auditReason);
        }

        $this->audit->record(new AuditEntry(
            action: 'webhook.delivery.replayed',
            actorType: ActorType::tryFrom($scope->principalKind) ?? ActorType::System,
            actorId: $scope->principalId,
            entityType: 'webhook_delivery',
            entityId: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'id')),
            context: JsonDocuments::capture($context),
            occurredAt: $now,
            workspaceId: $scope->workspaceId,
        ));

        return [
            'attempt' => 0,
            'body_hash' => DatabaseValue::text(DatabaseValue::field($row, 'body_hash')),
            'id' => $id,
            'replay_of' => $original,
            'replay_count' => $attempt + 1,
            'status' => 'pending',
            'subscription_id' => $subscriptionId,
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
            throw new ApiError(errorCode: OperationsErrorCode::ValidationFailed, status: 422, message: 'Webhook replay range is invalid.', category: ErrorCategory::Validation);
        }
    }

    private function validReason(?string $reason): bool
    {
        return is_string($reason) && $reason !== '' && strlen($reason) <= 500
            && preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $reason) !== 1;
    }

    private function assertScope(DataScope $scope): void
    {
        $this->contexts->assert($scope, $this->connection);
    }

    private function scopedDeliveries(DataScope $scope): Builder
    {
        return WebhookDeliveryScope::constrain($this->connection->table('sys_api_webhook_deliveries'), $scope);
    }

    private static function rangeOrdering(): OrderingRegistry
    {
        return OrderingFactory::fromOperations([new Operation(
            module: 'axiomasoft/corex-operations',
            id: 'webhook-deliveries.range-replay',
            version: 'v1',
            method: 'POST',
            path: '/webhook-deliveries/replay',
            ability: 'webhooks.replay',
            handler: WebhookReplayCursorOperationHandler::class,
            requestSchema: JsonDocuments::capture([]),
            responseSchema: JsonDocuments::capture([]),
            inputRules: [],
            successStatus: 200,
            mode: OperationMode::Command,
            requiredScopes: ['webhooks.replay'],
            idempotency: IdempotencyRequirement::Required,
            pagination: JsonDocuments::nullable([
                'ordering' => self::RangeOrdering,
                'direction' => 'asc',
                'fields' => ['upper_bound', 'created_at', 'id'],
                'types' => ['string', 'string', 'string'],
                'unique' => 'id',
            ]),
            effectMode: 'tenant_transactional',
        )]);
    }

    /** @return int<1, 3> */
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

        return new DateTimeImmutable(DatabaseValue::text($value));
    }
}

/** @internal Metadata-only handler class for the shared P13 cursor registry. */
#[Internal]
final class WebhookReplayCursorOperationHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return [];
    }
}
