<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\Messaging\Laravel\InboxRetention;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use UnexpectedValueException;

/**
 * Clock-controlled webhook body, history and inbox cleanup.
 *
 * Terminal bodies expire with the replay horizon. Attempt history expires
 * later. Delivery tombstones and the consumer inbox stay paired so an old
 * event cannot materialize a new send.
 *
 * @internal
 */
#[Internal]
final readonly class WebhookRetention
{
    public const int DefaultBodySeconds = 604_800;

    public const int DefaultHistorySeconds = 2_592_000;

    public const int BatchLimit = 100;

    public const string Consumer = 'api.webhooks.v1';

    private const string Deliveries = 'sys_api_webhook_deliveries';

    private const string Attempts = 'sys_api_webhook_attempts';

    private const string Inbox = 'sys_inbox_messages';

    public function __construct(
        private Connection $connection,
        private TenantContextRestorer $contexts,
        private ClockInterface $clock,
        private int $bodyRetentionSeconds = self::DefaultBodySeconds,
        private int $historyRetentionSeconds = self::DefaultHistorySeconds,
        private int $replayHorizonSeconds = self::DefaultBodySeconds,
        private string $inboxTable = self::Inbox,
    ) {
        if ($this->bodyRetentionSeconds < 1
            || $this->historyRetentionSeconds < 1
            || $this->replayHorizonSeconds < 1) {
            throw new InvalidArgumentException('Webhook retention windows must be positive.');
        }

        if ($this->bodyRetentionSeconds > $this->historyRetentionSeconds) {
            throw new InvalidArgumentException('Webhook body retention must not exceed history retention.');
        }

        if ($this->replayHorizonSeconds !== $this->bodyRetentionSeconds) {
            throw new InvalidArgumentException('Webhook replay horizon and body retention must move together.');
        }
    }

    /**
     * @return array{bodies: int, attempts: int, inbox: int}
     */
    public function prune(DataScope $scope, int $limit = self::BatchLimit): array
    {
        $this->assertLimit($limit);
        $this->assertScope($scope);
        $now = $this->clock->now();
        $bodyCutoff = $now->sub(new DateInterval('PT'.$this->bodyRetentionSeconds.'S'));
        $historyCutoff = $now->sub(new DateInterval('PT'.$this->historyRetentionSeconds.'S'));

        $bodyIds = $this->terminal(scope: $scope, now: $now)
            ->where('body', '!=', '')
            ->where(function (Builder $query) use ($bodyCutoff, $now): void {
                $query->where('expires_at', '<=', $now)
                    ->orWhere('created_at', '<=', $bodyCutoff);
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();
        $bodies = $bodyIds === []
            ? 0
            : $this->connection->table(self::Deliveries)->whereIn('id', $bodyIds)->update([
                'body' => '',
                'expires_at' => $now,
            ]);

        $historyIds = $this->terminal(scope: $scope, now: $now)
            ->where('created_at', '<=', $historyCutoff)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();
        $attemptIds = $historyIds === []
            ? []
            : $this->connection->table(self::Attempts)
                ->whereIn('delivery_id', $historyIds)
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id')
                ->all();
        $attempts = $attemptIds === []
            ? 0
            : $this->connection->table(self::Attempts)->whereIn('id', $attemptIds)->delete();

        return [
            'bodies' => $bodies,
            'attempts' => $attempts,
            'inbox' => $this->pruneInbox($limit),
        ];
    }

    public function pruneInbox(int $limit = self::BatchLimit): int
    {
        $this->assertLimit($limit);

        if (! $this->connection->getSchemaBuilder()->hasTable($this->inboxTable)) {
            return 0;
        }

        $ids = $this->connection->table($this->inboxTable.' as retained_inbox')
            ->where('consumer', self::Consumer)
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from(self::Deliveries);

                if ($this->connection->getDriverName() === 'pgsql') {
                    $query->whereRaw('sys_api_webhook_deliveries.event_id = retained_inbox.message_id::text');

                    return;
                }

                $query->whereColumn(self::Deliveries.'.event_id', 'retained_inbox.message_id');
            })
            ->orderBy('message_id')
            ->limit($limit)
            ->pluck('message_id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        return new InboxRetention(connection: $this->connection, table: $this->inboxTable)
            ->forget(consumer: self::Consumer, messageIds: array_map(
                static fn (mixed $id): string => is_string($id) ? $id : throw new UnexpectedValueException('Inbox message ID must be a string.'),
                array_values($ids),
            ));
    }

    private function assertLimit(int $limit): void
    {
        if ($limit < 1 || $limit > self::BatchLimit) {
            throw new InvalidArgumentException('Webhook retention batch must be between 1 and 100.');
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

    private function terminal(DataScope $scope, DateTimeImmutable $now): Builder
    {
        return $this->scoped($scope)
            ->whereIn('status', ['succeeded', 'dead'])
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('lease_until')->orWhere('lease_until', '<=', $now);
            });
    }

    private function scoped(DataScope $scope): Builder
    {
        $query = $this->connection->table(self::Deliveries);
        $accountPath = $this->connection->getDriverName() === 'pgsql'
            ? "scope->>'account'"
            : "json_extract(scope, '$.account')";
        $workspacePath = $this->connection->getDriverName() === 'pgsql'
            ? "scope->>'workspace'"
            : "json_extract(scope, '$.workspace')";
        $query->whereRaw($accountPath.' = ?', [$scope->accountId]);

        if ($scope->workspaceId === null) {
            $query->whereRaw($workspacePath.' IS NULL');
        } else {
            $query->whereRaw($workspacePath.' = ?', [$scope->workspaceId]);
        }

        return $query;
    }
}
