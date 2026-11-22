<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Digest\Data\DigestWindow;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Preferences\Contracts\PreferenceRepository;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final readonly class DigestWakeupHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private CurrentWorkScope $currentScope,
        private DataScopeCodec $scopeCodec,
        private PreferenceRepository $preferences,
        private DeliveryIntents $deliveries,
        private DigestBuilder $digests,
    ) {}

    public function __invoke(DigestTick $tick): void
    {
        $this->currentScope->assert(scope: $tick->scope, connection: $this->connection);
        $scopeKey = hash('sha256', json_encode($this->scopeCodec->encode($tick->scope), JSON_THROW_ON_ERROR));
        $prefix = 'notification.digest:';

        if (! str_starts_with($tick->requestKey, $prefix)) {
            throw new RuntimeException('Digest wakeup key is invalid.');
        }

        $runId = substr($tick->requestKey, strlen($prefix));

        if ($runId === '') {
            throw new RuntimeException('Digest wakeup key does not identify a run.');
        }

        $this->connection->transaction(function () use ($runId, $scopeKey, $tick): void {
            $this->currentScope->assert(scope: $tick->scope, connection: $this->connection);
            $run = $this->connection->table('ntf_digest_runs')->where('id', $runId)->where('scope_key', $scopeKey)->lockForUpdate()->first();

            if ($run === null || $run->state !== 'frozen'
                || $run->recipient_kind !== $tick->scope->principalKind
                || $run->recipient_id !== $tick->scope->principalId) {
                return;
            }

            $recipient = new RecipientReference(kind: NotificationValue::text($run->recipient_kind), id: NotificationValue::text($run->recipient_id));
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
                if ($this->preferences->get(context: $context, eventType: NotificationValue::text($item->event_type))->digest === $run->period) {
                    $notificationIds[] = $item->id;
                }
            }

            // Freeze the next bounded batch atomically with this decision. A retry
            // sees the terminal run and cannot create a second continuation.
            $this->digests->build(context: $context, window: new DigestWindow(
                start: $tick->window->start,
                end: $tick->window->end,
                period: $tick->window->period,
            ));

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
            $this->deliveries->add(
                scope: $tick->scope,
                notificationId: null,
                runId: $runId,
                channel: 'in_app',
                effectKey: hash('sha256', $runId."\0in_app"),
                message: $messageJson,
            );
            $this->connection->table('ntf_digest_runs')->where('id', $runId)->where('state', 'frozen')->update(['state' => 'admitted']);
        });
    }
}
