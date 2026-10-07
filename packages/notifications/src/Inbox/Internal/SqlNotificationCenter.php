<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\CancelResult;
use CoreX\Notifications\Inbox\Data\EntityReference;
use CoreX\Notifications\Inbox\Data\InboxQuery;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\NotificationRef;
use CoreX\Notifications\Inbox\Data\NotificationSubject;
use CoreX\Notifications\Inbox\Exceptions\NotificationAccessDenied;
use CoreX\Notifications\Inbox\Exceptions\NotificationOperationConflict;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use JsonException;
use Psr\Clock\ClockInterface;

final class SqlNotificationCenter implements NotificationCenter
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly NotificationPolicy $policy,
        private readonly ClockInterface $clock,
    ) {}

    public function notify(NotificationContext $context, NotificationInput $input): NotificationRef
    {
        $subject = new NotificationSubject(notificationId: null, recipient: $input->recipient, related: $input->related);

        if (! $this->policy->allows($context, NotificationAction::Notify, $subject)) {
            throw new NotificationAccessDenied('Notification delivery is denied.');
        }

        $payload = $this->canonicalMap(JsonDocuments::map($input->payload));
        $related = $input->related === null ? null : ['handle' => $input->related->handle, 'id' => $input->related->id];
        $digest = hash('sha256', $input->requestKey."\0".$input->eventType."\0".$input->templateKey."\0".$this->json($payload)."\0".$this->json($related));

        try {
            return $this->connection->transaction(function () use ($context, $input, $subject, $payload, $related, $digest): NotificationRef {
                $existing = $this->connection->table('ntf_notifications')
                    ->where('recipient_kind', $input->recipient->kind)
                    ->where('recipient_id', $input->recipient->id)
                    ->where('request_key', $input->requestKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if (! hash_equals(Value::text($existing->request_digest), $digest)) {
                        throw new NotificationOperationConflict('Notification request key conflicts with different content.');
                    }

                    return $this->reference($existing);
                }

                if (! $this->policy->allows($context, NotificationAction::Notify, $subject)) {
                    throw new NotificationAccessDenied('Notification delivery is denied.');
                }

                $id = (string) Str::uuid7();
                $message = [
                    'event_type' => $input->eventType,
                    'template_key' => $input->templateKey,
                    'payload' => $payload,
                    'recipient' => ['kind' => $input->recipient->kind, 'id' => $input->recipient->id],
                    'related' => $related,
                ];
                $messageJson = $this->json($message);
                $now = $this->clock->now();

                $this->connection->table('ntf_notifications')->insert([
                    'id' => $id,
                    'recipient_kind' => $input->recipient->kind,
                    'recipient_id' => $input->recipient->id,
                    'request_key' => $input->requestKey,
                    'request_digest' => $digest,
                    'event_type' => $input->eventType,
                    'template_key' => $input->templateKey,
                    'payload' => $this->json($payload),
                    'related' => $related === null ? null : $this->json($related),
                    'revision' => '1',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->connection->table('ntf_deliveries')->insert([
                    'id' => (string) Str::uuid7(),
                    'notification_id' => $id,
                    'digest_run_id' => null,
                    'channel' => 'in_app',
                    'delivery_key' => hash('sha256', $id."\0in_app"),
                    'state' => 'pending',
                    'claim_epoch' => '0',
                    'attempts' => 0,
                    'next_attempt' => $now,
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

                return new NotificationRef(
                    id: $id,
                    recipient: $input->recipient,
                    eventType: $input->eventType,
                    revision: '1',
                    readAt: null,
                    cancelledAt: null,
                    related: $input->related,
                );
            });
        } catch (QueryException $exception) {
            $existing = $this->connection->table('ntf_notifications')
                ->where('recipient_kind', $input->recipient->kind)
                ->where('recipient_id', $input->recipient->id)
                ->where('request_key', $input->requestKey)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            if (! hash_equals(Value::text($existing->request_digest), $digest)) {
                throw new NotificationOperationConflict('Notification request key conflicts with different content.');
            }

            return $this->reference($existing);
        }
    }

    public function cancel(NotificationContext $context, string $notificationId): CancelResult
    {
        return $this->connection->transaction(function () use ($context, $notificationId): CancelResult {
            $notification = $this->connection->table('ntf_notifications')->where('id', $notificationId)->lockForUpdate()->first();

            if ($notification === null) {
                return new CancelResult(cancelled: false, alreadyDispatched: []);
            }

            $subject = $this->subject($notification);

            if (! $this->policy->allows($context, NotificationAction::Cancel, $subject)) {
                throw new NotificationAccessDenied('Notification cancellation is denied.');
            }

            if ($notification->cancelled_at !== null) {
                return new CancelResult(cancelled: false, alreadyDispatched: $this->dispatchedChannels($notificationId));
            }

            $now = $this->clock->now();
            $this->connection->table('ntf_notifications')->where('id', $notificationId)->update([
                'cancelled_at' => $now,
                'revision' => (string) (Value::integer($notification->revision) + 1),
                'updated_at' => $now,
            ]);
            $this->connection->table('ntf_deliveries')
                ->where('notification_id', $notificationId)
                ->whereIn('state', ['pending', 'failed'])
                ->update(['state' => 'cancelled', 'next_attempt' => null]);

            return new CancelResult(cancelled: true, alreadyDispatched: $this->dispatchedChannels($notificationId));
        });
    }

    public function markRead(NotificationContext $context, string $notificationId): void
    {
        $this->connection->transaction(function () use ($context, $notificationId): void {
            $notification = $this->connection->table('ntf_notifications')->where('id', $notificationId)->lockForUpdate()->first();

            if ($notification === null) {
                throw new NotificationAccessDenied('Notification is unavailable.');
            }

            if (! $this->policy->allows($context, NotificationAction::Read, $this->subject($notification))) {
                throw new NotificationAccessDenied('Notification read is denied.');
            }

            if ($notification->read_at === null) {
                $this->connection->table('ntf_notifications')->where('id', $notificationId)->update([
                    'read_at' => $this->clock->now(),
                    'revision' => (string) (Value::integer($notification->revision) + 1),
                    'updated_at' => $this->clock->now(),
                ]);
            }
        });
    }

    /** @return list<NotificationRef> */
    public function inbox(NotificationContext $context, InboxQuery $query): array
    {
        $recipient = $this->selfRecipient($context);
        $rows = $this->connection->table('ntf_notifications')
            ->where('recipient_kind', $recipient->kind)
            ->where('recipient_id', $recipient->id)
            ->when($query->before !== null, static fn (Builder $builder) => $builder->where('id', '<', $query->before))
            ->orderByDesc('id')
            ->limit($query->limit)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            if ($this->policy->allows($context, NotificationAction::Read, $this->subject($row))) {
                $result[] = $this->reference($row);
            }
        }

        return $result;
    }

    public function unreadCount(NotificationContext $context): int
    {
        $recipient = $this->selfRecipient($context);

        return $this->connection->table('ntf_notifications')
            ->where('recipient_kind', $recipient->kind)
            ->where('recipient_id', $recipient->id)
            ->whereNull('read_at')
            ->whereNull('cancelled_at')
            ->count();
    }

    private function selfRecipient(NotificationContext $context): RecipientReference
    {
        if ($context->actorId === null || $context->actorId === '') {
            throw new NotificationAccessDenied('Notification inbox requires an authenticated recipient.');
        }

        return new RecipientReference(kind: $context->actorKind, id: $context->actorId);
    }

    private function subject(object $notification): NotificationSubject
    {
        return new NotificationSubject(
            notificationId: Value::nullableText(Value::field($notification, 'id')),
            recipient: new RecipientReference(kind: Value::text(Value::field($notification, 'recipient_kind')), id: Value::text(Value::field($notification, 'recipient_id'))),
            related: $this->related(Value::field($notification, 'related')),
        );
    }

    private function reference(object $notification): NotificationRef
    {
        return new NotificationRef(
            id: Value::text(Value::field($notification, 'id')),
            recipient: new RecipientReference(kind: Value::text(Value::field($notification, 'recipient_kind')), id: Value::text(Value::field($notification, 'recipient_id'))),
            eventType: Value::text(Value::field($notification, 'event_type')),
            revision: Value::text(Value::field($notification, 'revision')),
            readAt: $this->date(Value::field($notification, 'read_at')),
            cancelledAt: $this->date(Value::field($notification, 'cancelled_at')),
            related: $this->related(Value::field($notification, 'related')),
        );
    }

    private function related(mixed $related): ?EntityReference
    {
        if ($related === null) {
            return null;
        }

        try {
            $decoded = is_string($related) ? json_decode($related, true, flags: JSON_THROW_ON_ERROR) : $related;
        } catch (JsonException $exception) {
            throw new NotificationOperationConflict('Notification relation is invalid.', previous: $exception);
        }

        if (! is_array($decoded) || ! isset($decoded['handle'], $decoded['id']) || ! is_string($decoded['handle']) || ! is_string($decoded['id'])) {
            throw new NotificationOperationConflict('Notification relation is invalid.');
        }

        return new EntityReference(handle: $decoded['handle'], id: $decoded['id']);
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable(Value::text($value));
    }

    /** @return list<string> */
    private function dispatchedChannels(string $notificationId): array
    {
        return array_values($this->connection->table('ntf_deliveries')
            ->where('notification_id', $notificationId)
            ->whereIn('state', ['sent', 'unknown', 'claimed'])
            ->orderBy('channel')
            ->pluck('channel')->map(Value::text(...))
            ->all());
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private function canonicalMap(array $value): array
    {
        if (array_is_list($value)) {
            throw new NotificationOperationConflict('Notification payload must be an object.');
        }

        ksort($value);
        $canonical = [];
        foreach ($value as $key => $item) {
            if (! is_string($key) || $key === '') {
                throw new NotificationOperationConflict('Notification payload contains an unsupported value.');
            }

            $canonical[$key] = $this->canonicalValue($item);
        }

        return $canonical;
    }

    /** @return array<array-key, mixed>|bool|int|string|null */
    private function canonicalValue(mixed $value): array|bool|int|string|null
    {
        if (is_null($value) || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            throw new NotificationOperationConflict('Notification payload contains an unsupported value.');
        }

        if (! array_is_list($value)) {
            return $this->canonicalMap($value);
        }

        return array_map(fn (mixed $item): null|bool|int|string|array => $this->canonicalValue($item), $value);
    }

    /** @param array<string, mixed>|null $value */
    private function json(?array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
