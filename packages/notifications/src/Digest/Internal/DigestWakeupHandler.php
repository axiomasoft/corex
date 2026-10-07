<?php

declare(strict_types=1);

namespace CoreX\Notifications\Digest\Internal;

use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Preferences\Contracts\PreferenceRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;
use RuntimeException;

final readonly class DigestWakeupHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private PreferenceRepository $preferences,
        private ClockInterface $clock,
    ) {}

    public function __invoke(DigestTick $tick): void
    {
        $prefix = 'notification.digest:';

        if (! str_starts_with($tick->requestKey, $prefix)) {
            throw new RuntimeException('Digest wakeup key is invalid.');
        }

        $runId = substr($tick->requestKey, strlen($prefix));

        if ($runId === '') {
            throw new RuntimeException('Digest wakeup key does not identify a run.');
        }

        $this->connection->transaction(function () use ($runId, $tick): void {
            $run = $this->connection->table('ntf_digest_runs')->where('id', $runId)->lockForUpdate()->first();

            if ($run === null || $run->state !== 'frozen') {
                return;
            }

            $recipient = new RecipientReference(kind: Value::text($run->recipient_kind), id: Value::text($run->recipient_id));
            $context = new NotificationContext(tenant: $tick->tenant, actorKind: $recipient->kind, actorId: $recipient->id);
            $items = $this->connection->table('ntf_digest_items')
                ->join('ntf_notifications', 'ntf_notifications.id', '=', 'ntf_digest_items.notification_id')
                ->where('ntf_digest_items.run_id', $runId)
                ->where('ntf_digest_items.channel', 'in_app')
                ->whereNull('ntf_notifications.cancelled_at')
                ->orderBy('ntf_notifications.id')
                ->select(['ntf_notifications.id', 'ntf_notifications.event_type'])
                ->lockForUpdate()
                ->get();

            $notificationIds = [];
            foreach ($items as $item) {
                if ($this->preferences->get(context: $context, eventType: Value::text($item->event_type))->digest === $run->period) {
                    $notificationIds[] = $item->id;
                }
            }

            if ($notificationIds === []) {
                $this->connection->table('ntf_digest_runs')->where('id', $runId)->update(['state' => 'skipped']);

                return;
            }

            $message = [
                'event_type' => 'notification.digest',
                'template_key' => 'notification-digest',
                'recipient' => ['kind' => $recipient->kind, 'id' => $recipient->id],
                'notification_ids' => $notificationIds,
                'window' => [
                    'start' => $tick->window->start->format(DATE_ATOM),
                    'end' => $tick->window->end->format(DATE_ATOM),
                    'period' => $tick->window->period,
                ],
            ];
            $messageJson = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->connection->table('ntf_deliveries')->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'notification_id' => null,
                'digest_run_id' => $runId,
                'channel' => 'in_app',
                'delivery_key' => hash('sha256', $runId."\0in_app"),
                'state' => 'pending',
                'claim_epoch' => '0',
                'attempts' => 0,
                'next_attempt' => $this->clock->now(),
                'lease_until' => null,
                'admitted_revision' => '1',
                'provider_ref' => null,
                'error' => null,
                'sent_at' => null,
                'message' => $messageJson,
                'message_hash' => hash('sha256', $messageJson),
                'template_version' => '1',
                'policy_version' => '1',
                'driver_version' => '1',
            ]);
            $this->connection->table('ntf_digest_runs')->where('id', $runId)->where('state', 'frozen')->update(['state' => 'admitted']);
        });
    }
}
