<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Data\Data\DataScope;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

/**
 * Clock-controlled webhook body and attempt history cleanup.
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

    private const string Deliveries = 'sys_api_webhook_deliveries';

    private const string Attempts = 'sys_api_webhook_attempts';

    /** @param Closure(): ProviderEffectLedger $providerLedger */
    public function __construct(
        private Connection $connection,
        private CurrentWorkScope $contexts,
        private ClockInterface $clock,
        private Closure $providerLedger,
        private int $bodyRetentionSeconds = self::DefaultBodySeconds,
        private int $historyRetentionSeconds = self::DefaultHistorySeconds,
        private int $replayHorizonSeconds = self::DefaultBodySeconds,
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

        return $this->connection->transaction(fn (): array => $this->pruneAdmitted(scope: $scope, limit: $limit));
    }

    /** @return array{bodies: int, attempts: int, inbox: int} */
    private function pruneAdmitted(DataScope $scope, int $limit): array
    {
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
            ->get(['id', 'effect_key', 'endpoint_snapshot', 'provider_ticket']);
        $bodyIds = $this->settledDeliveryIds(scope: $scope, rows: $bodyIds);
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
            ->get(['id', 'effect_key', 'endpoint_snapshot', 'provider_ticket']);
        $historyIds = $this->settledDeliveryIds(scope: $scope, rows: $historyIds);
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
            'inbox' => 0,
        ];
    }

    /**
     * @param  iterable<object>  $rows
     * @return list<string>
     */
    private function settledDeliveryIds(DataScope $scope, iterable $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (DatabaseValue::field(row: $row, name: 'provider_ticket') !== null) {
                $effect = new ProviderEffectRef(
                    accountId: $scope->accountId, accountIncarnation: $scope->accountIncarnation, workspaceId: $scope->workspaceId, owner: WebhookWork::Owner,
                    providerNamespace: 'webhook.'.hash('sha256', DatabaseValue::text(DatabaseValue::field(row: $row, name: 'endpoint_snapshot'))), effectKey: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'effect_key')),
                );
                $status = ($this->providerLedger)()->inspect($effect);

                if ($status === null || ! in_array($status->state, ['reconciled', 'isolated'], true)) {
                    continue;
                }
            }

            $ids[] = DatabaseValue::text(DatabaseValue::field(row: $row, name: 'id'));
        }

        return $ids;
    }

    private function assertLimit(int $limit): void
    {
        if ($limit < 1 || $limit > self::BatchLimit) {
            throw new InvalidArgumentException('Webhook retention batch must be between 1 and 100.');
        }
    }

    private function assertScope(DataScope $scope): void
    {
        $this->contexts->assert(scope: $scope, connection: $this->connection);
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
        return WebhookDeliveryScope::constrain(query: $this->connection->table(self::Deliveries), scope: $scope);
    }
}
