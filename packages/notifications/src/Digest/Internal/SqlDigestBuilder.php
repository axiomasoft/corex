<?php

declare(strict_types=1);

namespace CoreX\Notifications\Digest\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Digest\Data\DigestRef;
use CoreX\Notifications\Digest\Data\DigestWindow;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Exceptions\NotificationAccessDenied;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Scheduling\Data\WakeupWindow;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Packman\Config\Exceptions\InvalidConfiguration;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class SqlDigestBuilder implements DigestBuilder
{
    public function __construct(
        private ConnectionInterface $connection,
        private ?WakeupScheduler $wakeup,
        private bool $enabled,
        private ClockInterface $clock,
    ) {
        if ($this->enabled && $this->wakeup === null) {
            throw new InvalidConfiguration([
                'corex-notifications.digest.enabled requires a bound CoreX\Scheduling\Contracts\WakeupScheduler',
            ]);
        }
    }

    public function build(NotificationContext $context, DigestWindow $window): ?DigestRef
    {
        if ($context->actorId === null || $context->actorId === '') {
            throw new NotificationAccessDenied('Digest building requires an authenticated recipient.');
        }
        $recipient = new RecipientReference(kind: $context->actorKind, id: $context->actorId);

        return $this->connection->transaction(function () use ($context, $recipient, $window): ?DigestRef {
            $runs = $this->connection->table('ntf_digest_runs')->where('recipient_kind', $recipient->kind)->where('recipient_id', $recipient->id)->where('period', $window->period)->where('period_start', $window->start)->orderByDesc('batch_seq')->lockForUpdate()->get();
            $items = $this->connection->table('ntf_notifications')
                ->where('recipient_kind', $recipient->kind)
                ->where('recipient_id', $recipient->id)
                ->whereNull('cancelled_at')
                ->where('created_at', '>=', $window->start)
                ->where('created_at', '<', $window->end)
                ->whereNotExists(function (Builder $query): void {
                    $query->selectRaw('1')
                        ->from('ntf_digest_items')
                        ->whereColumn('ntf_digest_items.notification_id', 'ntf_notifications.id')
                        ->where('ntf_digest_items.channel', 'in_app');
                })
                ->orderBy('id')
                ->limit(100)
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                if ($runs->isEmpty()) {
                    return null;
                }

                $run = $runs->first();

                return new DigestRef(id: Value::text($run->id), recipient: $recipient, window: $window, batchSequence: Value::integer($run->batch_seq));
            }
            $ids = Value::strings($items->pluck('id')->all());
            $runId = (string) Str::uuid7();
            $batchSequence = $runs->isEmpty() ? 0 : Value::integer($runs->first()->batch_seq) + 1;
            $this->connection->table('ntf_digest_runs')->insert(['id' => $runId, 'recipient_kind' => $recipient->kind, 'recipient_id' => $recipient->id, 'period' => $window->period, 'period_start' => $window->start, 'period_end' => $window->end, 'batch_seq' => $batchSequence, 'membership_hash' => hash('sha256', implode("\0", $ids)), 'state' => 'frozen', 'created_at' => $this->clock->now()]);
            foreach ($ids as $id) {
                $this->connection->table('ntf_digest_items')->insert(['id' => (string) Str::uuid7(), 'run_id' => $runId, 'notification_id' => $id, 'channel' => 'in_app']);
            }

            if ($this->enabled) {
                $wakeup = $this->wakeup ?? throw new InvalidConfiguration([
                    'corex-notifications.digest.enabled requires a bound CoreX\Scheduling\Contracts\WakeupScheduler',
                ]);
                $wakeup->schedule(new WakeupRequest(
                    context: $context->tenant,
                    kind: 'notification.digest.v1',
                    requestKey: "notification.digest:{$runId}",
                    fireAt: $window->end,
                    window: new WakeupWindow(start: $window->start, end: $window->end, period: $window->period),
                    payload: JsonDocuments::capture(['run_id' => $runId]),
                ));
            }

            return new DigestRef(id: $runId, recipient: $recipient, window: $window, batchSequence: $batchSequence);
        });
    }
}
