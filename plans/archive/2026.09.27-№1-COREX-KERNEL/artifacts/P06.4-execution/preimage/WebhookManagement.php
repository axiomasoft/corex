<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Pagination\Contracts\CursorCodec;
use CoreX\Operations\Pagination\Contracts\OrderingRegistry;
use CoreX\Operations\Pagination\Laravel\CursorPageBuilder;
use CoreX\Operations\Pagination\Laravel\OrderingFactory;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Webhooks\Contracts\WebhookSubscriptionStore;
use CoreX\Webhooks\Data\WebhookSubscription;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/** @internal Public-safe tenant webhook management and history projection. */
final readonly class WebhookManagement
{
    private const string DeliveryOrdering = 'webhook-deliveries.created.v1';

    private const array DeliveryFields = [
        'attempt', 'body_hash', 'created_at', 'event_id', 'finished_at', 'id', 'last_error',
        'major', 'replay_count', 'status', 'subscription_id', 'topic', 'topic_version',
    ];

    public function __construct(
        private Connection $connection,
        private TenantContextRestorer $contexts,
        private WebhookSubscriptionStore $subscriptions,
        private CursorCodec $cursors,
    ) {}

    /** @return array<string, mixed> */
    public function create(
        DataScope $scope,
        WebhookSubscription $subscription,
        bool $sensitiveAbility,
        bool $policyAllows,
    ): array {
        return $this->subscription($this->subscriptions->create(
            $scope,
            $subscription,
            $sensitiveAbility,
            $policyAllows,
        ));
    }

    /** @param  list<string>  $keyRefs
     * @return array<string, mixed>
     */
    public function update(
        DataScope $scope,
        string $id,
        int $expectedRevision,
        string $endpointUrl,
        array $keyRefs,
        bool $sensitiveAbility,
        bool $policyAllows,
    ): array {
        return $this->subscription($this->subscriptions->update(
            $scope,
            $id,
            $expectedRevision,
            $endpointUrl,
            $keyRefs,
            $sensitiveAbility,
            $policyAllows,
        ));
    }

    /** @return array<string, mixed> */
    public function disable(DataScope $scope, string $id, int $expectedRevision): array
    {
        return $this->subscription($this->subscriptions->disable($scope, $id, $expectedRevision));
    }

    /** @return list<array<string, mixed>> */
    public function subscriptions(DataScope $scope): array
    {
        $this->assertScope($scope);

        return $this->connection->table('sys_api_webhook_subscriptions')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => $this->subscriptionRow($row))
            ->all();
    }

    /**
     * @param  array{status?: string, subscription_id?: string}  $filters
     * @return array{items: list<array<string, mixed>>, next: string|null}
     */
    public function deliveries(
        DataScope $scope,
        string $clientId,
        array $filters = [],
        ?string $cursor = null,
        int $limit = 50,
    ): array {
        $this->assertScope($scope);
        $this->assertClient($clientId);
        $this->assertDeliveryFilters($filters);

        if ($limit < 1 || $limit > 100) {
            throw new ApiError(OperationsErrorCode::ValidationFailed, 422, 'Webhook history limit must be between 1 and 100.', category: ErrorCategory::Validation);
        }

        $cursorFilters = ['client_id' => $clientId, ...$filters];
        $registry = self::deliveryOrdering();
        $query = $this->scopedDeliveries($scope);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['subscription_id'])) {
            $query->where('subscription_id', $filters['subscription_id']);
        }

        if ($cursor !== null) {
            $position = $this->cursors->decode(
                $cursor,
                $registry,
                $scope->accountId,
                $scope->workspaceId,
                'v1',
                'webhook-deliveries.list',
                $cursorFilters,
                self::DeliveryFields,
                self::DeliveryOrdering,
            );
            [$createdAt, $id] = $position->values;
            $query->where(static function (Builder $query) use ($createdAt, $id): void {
                $query->where('created_at', '>', new DateTimeImmutable((string) $createdAt))
                    ->orWhere(static function (Builder $query) use ($createdAt, $id): void {
                        $query->where('created_at', new DateTimeImmutable((string) $createdAt))
                            ->where('id', '>', (string) $id);
                    });
            });
        }

        $rows = $query->orderBy('created_at')->orderBy('id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $pageRows = $rows->take($limit)->values();
        $items = $pageRows->map(fn (object $row): array => $this->deliveryRow($row))->all();
        $last = $hasMore ? $pageRows->last() : null;
        $page = CursorPageBuilder::assemble(
            items: array_values($items),
            nextValues: $last === null ? null : [$this->instant($last->created_at)->format('Y-m-d\TH:i:s.uP'), (string) $last->id],
            codec: $this->cursors,
            registry: $registry,
            accountId: $scope->accountId,
            workspaceId: $scope->workspaceId,
            apiMajor: 'v1',
            operationId: 'webhook-deliveries.list',
            filters: $cursorFilters,
            fields: self::DeliveryFields,
            orderingId: self::DeliveryOrdering,
        );

        return ['items' => JsonDocuments::maps($page->items), 'next' => $page->next];
    }

    /** @return array<string, mixed>|null */
    public function delivery(DataScope $scope, string $id): ?array
    {
        $this->assertScope($scope);
        $row = $this->scopedDeliveries($scope)->where('id', $id)->first();

        return $row === null ? null : $this->deliveryRow($row);
    }

    /** @return list<array<string, mixed>> */
    public function attempts(DataScope $scope, string $id): array
    {
        if ($this->delivery($scope, $id) === null) {
            throw new ApiError(OperationsErrorCode::ResourceNotFound, 404, 'Webhook delivery does not exist.', category: ErrorCategory::NotFound);
        }

        return $this->connection->table('sys_api_webhook_attempts')
            ->where('delivery_id', $id)
            ->orderBy('attempt')
            ->get()
            ->map(fn (object $row): array => [
                'attempt' => (int) $row->attempt,
                'duration_ms' => $row->duration_ms === null ? null : (int) $row->duration_ms,
                'error_code' => $row->error_code === null ? null : (string) $row->error_code,
                'finished_at' => $row->finished_at === null ? null : $this->instant($row->finished_at)->format('Y-m-d\TH:i:s.uP'),
                'http_status' => $row->http_status === null ? null : (int) $row->http_status,
                'outcome' => (string) $row->outcome,
                'started_at' => $this->instant($row->started_at)->format('Y-m-d\TH:i:s.uP'),
            ])
            ->all();
    }

    public static function deliveryOrdering(): OrderingRegistry
    {
        return OrderingFactory::fromOperations([new Operation(
            module: 'axiomasoft/corex-operations',
            id: 'webhook-deliveries.list',
            version: 'v1',
            method: 'GET',
            path: '/webhook-deliveries',
            ability: 'webhooks.manage',
            handler: WebhookCursorOperationHandler::class,
            requestSchema: JsonDocuments::capture([]),
            responseSchema: JsonDocuments::capture([]),
            inputRules: [],
            successStatus: 200,
            mode: OperationMode::Query,
            requiredScopes: ['webhooks.manage'],
            idempotency: IdempotencyRequirement::Forbidden,
            pagination: JsonDocuments::nullable([
                'ordering' => self::DeliveryOrdering,
                'direction' => 'asc',
                'fields' => ['created_at', 'id'],
                'types' => ['string', 'string'],
                'unique' => 'id',
            ]),
        )]);
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

    private function assertClient(string $clientId): void
    {
        if ($clientId === '' || strlen($clientId) > 128 || preg_match('/[\x00-\x1f\x7f]/', $clientId) === 1) {
            throw new InvalidArgumentException('Webhook client identity is invalid.');
        }
    }

    /** @param array<string, mixed> $filters */
    private function assertDeliveryFilters(array $filters): void
    {
        if (array_diff(array_keys($filters), ['status', 'subscription_id']) !== []) {
            throw new ApiError(OperationsErrorCode::ValidationFailed, 422, 'Webhook history filter is not declared.', category: ErrorCategory::Validation);
        }

        if (isset($filters['status']) && (! is_string($filters['status'])
            || ! in_array($filters['status'], ['pending', 'sending', 'retry', 'succeeded', 'dead'], true))) {
            throw new ApiError(OperationsErrorCode::ValidationFailed, 422, 'Webhook status filter is invalid.', category: ErrorCategory::Validation);
        }

        if (isset($filters['subscription_id']) && (! is_string($filters['subscription_id']) || $filters['subscription_id'] === '')) {
            throw new ApiError(OperationsErrorCode::ValidationFailed, 422, 'Webhook subscription filter is invalid.', category: ErrorCategory::Validation);
        }
    }

    /** @return array<string, mixed> */
    private function subscription(WebhookSubscription $subscription): array
    {
        return [
            'created_at' => $subscription->createdAt->format('Y-m-d\TH:i:s.uP'),
            'endpoint' => $this->safeEndpoint($subscription->endpointUrl),
            'id' => $subscription->id,
            'key_ref_count' => count($subscription->keyRefs),
            'major' => $subscription->major,
            'max_pii_class' => $subscription->maxPiiClass,
            'revision' => $subscription->revision,
            'status' => $subscription->status,
            'topic' => $subscription->topic,
            'topic_version' => $subscription->topicVersion,
            'updated_at' => $subscription->updatedAt->format('Y-m-d\TH:i:s.uP'),
        ];
    }

    /** @return array<string, mixed> */
    private function subscriptionRow(object $row): array
    {
        $refs = JsonCanonical::decode((string) $row->key_refs);

        return [
            'created_at' => $this->instant($row->created_at)->format('Y-m-d\TH:i:s.uP'),
            'endpoint' => $this->safeEndpoint((string) $row->endpoint_url),
            'id' => (string) $row->id,
            'key_ref_count' => is_array($refs) ? count($refs) : 0,
            'major' => (string) $row->major,
            'max_pii_class' => (string) $row->max_pii_class,
            'revision' => (int) $row->revision,
            'status' => (string) $row->status,
            'topic' => (string) $row->topic,
            'topic_version' => (int) $row->topic_version,
            'updated_at' => $this->instant($row->updated_at)->format('Y-m-d\TH:i:s.uP'),
        ];
    }

    /** @return array<string, mixed> */
    private function deliveryRow(object $row): array
    {
        return [
            'attempt' => (int) $row->attempt,
            'body_hash' => (string) $row->body_hash,
            'created_at' => $this->instant($row->created_at)->format('Y-m-d\TH:i:s.uP'),
            'event_id' => (string) $row->event_id,
            'finished_at' => $row->finished_at === null ? null : $this->instant($row->finished_at)->format('Y-m-d\TH:i:s.uP'),
            'id' => (string) $row->id,
            'last_error' => $row->last_error === null ? null : (string) $row->last_error,
            'major' => (string) $row->major,
            'replay_count' => (int) $row->replay_count,
            'status' => (string) $row->status,
            'subscription_id' => (string) $row->subscription_id,
            'topic' => (string) $row->topic,
            'topic_version' => (int) $row->topic_version,
        ];
    }

    private function safeEndpoint(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return 'redacted';
        }

        return $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '');
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
final class WebhookCursorOperationHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return [];
    }
}
