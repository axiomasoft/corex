<?php

declare(strict_types=1);

namespace CoreX\Notifications\Digest\Internal;

use CoreX\Context\Data\TenantContext;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Scheduling\Contracts\DueWakeupSource;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\SweepResult;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Scheduling\Data\WakeupWindow;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class NotificationDigestDueSource implements DueWakeupSource
{
    public function __construct(
        private ConnectionInterface $connection,
        private WakeupScheduler $scheduler,
    ) {}

    public function nextDue(TenantContext $context): ?DateTimeImmutable
    {
        $due = $this->connection->table('ntf_digest_runs')->where('state', 'frozen')->min('period_end');

        return $due === null ? null : $this->date($due);
    }

    public function sweep(TenantContext $context, DateTimeImmutable $now, int $limit): SweepResult
    {
        $runs = $this->connection->table('ntf_digest_runs')
            ->where('state', 'frozen')
            ->where('period_end', '<=', $now)
            ->orderBy('period_end')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $recovered = $failed = 0;

        foreach ($runs as $run) {
            // Recreate only a missing immutable request for this concrete run.
            // Existing cancelled, leased, retried or quarantined requests retain
            // their admission state; only the durable sweeper invokes handlers.
            $result = $this->scheduler->schedule(new WakeupRequest(
                context: $context, kind: 'notification.digest.v1', requestKey: 'notification.digest:'.Value::text($run->id),
                fireAt: $this->date($run->period_end),
                window: new WakeupWindow(start: $this->date($run->period_start), end: $this->date($run->period_end), period: Value::text($run->period)),
                payload: JsonDocuments::capture(['run_id' => Value::text($run->id)]),
            ));
            $recovered += $result->status === 'created' ? 1 : 0;
            $failed += $result->status === 'conflict' ? 1 : 0;
        }

        return new SweepResult(claimed: count($runs), completed: 0, retried: $recovered, failed: $failed, skipped: count($runs) - $recovered - $failed, remainingDue: count($runs) === $limit, nextFireAt: $this->nextDue($context));
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable(Value::text($value));
    }
}
