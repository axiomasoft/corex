<?php

declare(strict_types=1);

namespace CoreX\Notifications\Digest\Internal;

use CoreX\Context\Data\TenantContext;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Digest\Data\DigestWindow;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Wakeup\Contracts\DueWakeupSource;
use CoreX\Wakeup\Data\SweepResult;
use CoreX\Wakeup\Data\WakeupWindow;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;

final class NotificationDigestDueSource implements DueWakeupSource
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly DigestBuilder $digests,
        private readonly DigestWakeupHandler $handler,
    ) {}

    public function nextDue(TenantContext $context): ?DateTimeImmutable
    {
        $due = $this->connection->table('ntf_digest_runs')->min('period_end');

        return $due === null ? null : $this->date($due);
    }

    public function sweep(TenantContext $context, DateTimeImmutable $now, int $limit): SweepResult
    {
        $runs = $this->connection->table('ntf_digest_runs')
            ->where('period_end', '<=', $now)
            ->orderBy('period_end')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $completed = 0;

        foreach ($runs as $run) {
            $window = new DigestWindow(start: $this->date($run->period_start), end: $this->date($run->period_end), period: $run->period);
            $digest = $this->digests->build(new NotificationContext(tenant: $context, actorKind: $run->recipient_kind, actorId: $run->recipient_id), $window);

            if ($digest === null) {
                continue;
            }

            ($this->handler)(new DigestTick(tenant: $context, window: new WakeupWindow(start: $window->start, end: $window->end, period: $window->period), requestKey: "notification.digest:{$digest->id}"));
            $completed++;
        }

        return new SweepResult(claimed: count($runs), completed: $completed, retried: 0, failed: 0, skipped: count($runs) - $completed, remainingDue: count($runs) === $limit, nextFireAt: $this->nextDue($context));
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable((string) $value);
    }
}
