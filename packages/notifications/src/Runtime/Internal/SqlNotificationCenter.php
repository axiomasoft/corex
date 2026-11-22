<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\CancelResult;
use CoreX\Notifications\Inbox\Data\InboxPage;
use CoreX\Notifications\Inbox\Data\InboxQuery;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\NotificationRef;
use CoreX\Notifications\Inbox\Data\NotificationSubject;
use CoreX\Notifications\Inbox\Data\UnreadCount;
use CoreX\Notifications\Inbox\Exceptions\NotificationAccessDenied;
use CoreX\Notifications\Inbox\Exceptions\NotificationOperationConflict;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class SqlNotificationCenter implements NotificationCenter
{
    private const int ScanLimit = 1000;

    public function __construct(
        private ConnectionInterface $connection,
        private NotificationPolicy $policy,
        private ClockInterface $clock,
        private NotificationScope $scopes,
        private CurrentWorkScope $current,
        private NotificationVisibility $visibility,
        private DeliveryIntents $deliveries,
        private StringEncrypter $encryption,
    ) {}

    public function notify(NotificationContext $context, NotificationInput $input): NotificationRef
    {
        $scope = $this->scopes->of($context);
        $subject = new NotificationSubject(notificationId: null, recipient: $input->recipient, related: $input->related);
        $payload = $this->canonicalMap(JsonDocuments::map($input->payload));
        $related = $input->related === null ? null : ['handle' => $input->related->handle, 'id' => $input->related->id];
        $identity = [
            ...NotificationScope::logical($scope),
            'recipient_kind' => $input->recipient->kind,
            'recipient_id' => $input->recipient->id,
            'source' => $input->source,
            'event_id' => $input->requestKey,
            'template_action' => $input->templateKey,
        ];
        $channels = $input->channels;
        sort($channels, SORT_STRING);
        $digest = hash('sha256', $this->json([
            'intent' => $identity,
            'event_type' => $input->eventType,
            'payload' => $payload,
            'related' => $related,
            'channels' => $channels,
        ]));

        return $this->connection->transaction(function () use ($context, $scope, $input, $subject, $payload, $related, $channels, $identity, $digest): NotificationRef {
            $this->current->assert(scope: $scope, connection: $this->connection);
            ReferenceWrites::enlist(connection: $this->connection, scope: $scope, producer: 'axiomasoft/corex-notifications', kind: 'notify', witness: ['source' => $identity, 'related' => $related, 'channels' => $channels]);

            if (! $this->policy->allows(context: $context, action: NotificationAction::Notify, subject: $subject)) {
                throw new NotificationAccessDenied('Notification delivery is denied.');
            }

            $id = (string) Str::uuid7();
            $now = $this->clock->now();
            $columns = [
                ...$identity,
                'id' => $id,
                'request_digest' => $digest,
                'event_type' => $input->eventType,
                'payload' => $this->json($payload),
                'related' => $related === null ? null : $this->json($related),
                'revision' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $inserted = $this->connection->affectingStatement(
                'insert into ntf_notifications ('.implode(', ', array_keys($columns)).') values ('
                .implode(', ', array_fill(0, count($columns), '?')).') on conflict ('
                .implode(', ', array_keys($identity)).') do nothing',
                array_values($columns),
            );
            $row = $this->connection->table('ntf_notifications')->where($identity)->firstOrFail();

            if (! hash_equals(NotificationValue::text($row->request_digest), $digest)) {
                throw new NotificationOperationConflict('Notification request key conflicts with different content.');
            }

            if ($inserted === 1) {
                $message = $this->json([
                    'event_type' => $input->eventType,
                    'template_key' => $input->templateKey,
                    'payload' => $payload,
                    'recipient' => ['kind' => $input->recipient->kind, 'id' => $input->recipient->id],
                    'related' => $related,
                ]);

                foreach ($channels as $channel) {
                    $key = hash('sha256', $this->json(['delivery' => $identity, 'channel' => $channel]));
                    $this->deliveries->add(scope: $scope, notificationId: $id, runId: null, channel: $channel, effectKey: $key, message: $message);
                }
            }

            return $this->reference($row);
        });
    }

    public function cancel(NotificationContext $context, string $notificationId): CancelResult
    {
        $scope = $this->scopes->of($context);

        return $this->connection->transaction(function () use ($context, $scope, $notificationId): CancelResult {
            $this->current->assert(scope: $scope, connection: $this->connection);
            $notification = $this->notifications($scope)->where('id', $notificationId)->lockForUpdate()->first();

            if ($notification === null) {
                return new CancelResult(cancelled: false, alreadyDispatched: []);
            }

            if (! $this->policy->allows(context: $context, action: NotificationAction::Cancel, subject: $this->visibility->subject($notification))) {
                throw new NotificationAccessDenied('Notification cancellation is denied.');
            }

            if ($notification->cancelled_at !== null) {
                return new CancelResult(cancelled: false, alreadyDispatched: $this->dispatchedChannels($notificationId));
            }

            $now = $this->clock->now();
            $this->notifications($scope)->where('id', $notificationId)->update([
                'cancelled_at' => $now,
                'revision' => (string) (NotificationValue::integer($notification->revision) + 1),
                'updated_at' => $now,
            ]);
            $this->connection->table('ntf_deliveries')
                ->where('notification_id', $notificationId)
                ->whereIn('state', ['pending', 'retry-scheduled', 'claimed'])
                ->update(['state' => 'cancelled', 'next_attempt' => null, 'lease_until' => null, 'claim_token' => null]);

            return new CancelResult(cancelled: true, alreadyDispatched: $this->dispatchedChannels($notificationId));
        });
    }

    public function markRead(NotificationContext $context, string $notificationId): void
    {
        $scope = $this->scopes->of($context);

        $this->connection->transaction(function () use ($context, $scope, $notificationId): void {
            $this->current->assert(scope: $scope, connection: $this->connection);
            $query = $this->recipient($scope)->where('id', $notificationId);
            $notification = $query->lockForUpdate()->first();

            if ($notification === null || ! $this->visibility->allows(context: $context, subject: $this->visibility->subject($notification))) {
                throw new NotificationAccessDenied('Notification is unavailable.');
            }

            if ($notification->read_at === null) {
                $query->update([
                    'read_at' => $this->clock->now(),
                    'revision' => (string) (NotificationValue::integer($notification->revision) + 1),
                    'updated_at' => $this->clock->now(),
                ]);
            }
        });
    }

    public function inbox(NotificationContext $context, InboxQuery $query): InboxPage
    {
        $scope = $this->scopes->of($context);
        $this->current->assert(scope: $scope, connection: $this->connection);
        $before = $this->decodeCursor(scope: $scope, cursor: $query->before);
        $rows = $this->recipient($scope)
            ->when($before !== null, static fn (Builder $builder) => $builder->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(self::ScanLimit + 1)
            ->get();

        $result = [];
        $last = null;
        foreach ($rows as $index => $row) {
            if ($index === self::ScanLimit || count($result) === $query->limit) {
                return new InboxPage(items: $result, cursor: $last === null ? null : $this->cursor(scope: $scope, id: $last));
            }

            $last = NotificationValue::text($row->id);

            if ($this->visibility->allows(context: $context, subject: $this->visibility->subject($row))) {
                $result[] = $this->reference($row);
            }
        }

        return new InboxPage(items: $result, cursor: null);
    }

    public function unreadCount(NotificationContext $context): UnreadCount
    {
        $scope = $this->scopes->of($context);
        $this->current->assert(scope: $scope, connection: $this->connection);
        $rows = $this->recipient($scope)->whereNull('read_at')->limit(self::ScanLimit + 1)->get();

        if ($rows->count() > self::ScanLimit) {
            return UnreadCount::unavailable('visibility-scan-limit');
        }

        $count = 0;
        foreach ($rows as $row) {
            if ($this->visibility->allows(context: $context, subject: $this->visibility->subject($row))) {
                $count++;
            }
        }

        return UnreadCount::exact($count);
    }

    private function notifications(DataScope $scope): Builder
    {
        return $this->connection->table('ntf_notifications')->where(NotificationScope::logical($scope));
    }

    private function recipient(DataScope $scope): Builder
    {
        return $this->notifications($scope)
            ->where('recipient_kind', $scope->principalKind)
            ->where('recipient_id', $scope->principalId)
            ->whereNull('cancelled_at');
    }

    private function cursor(DataScope $scope, string $id): string
    {
        return $this->encryption->encryptString($this->cursorLane($scope).':'.$id);
    }

    private function cursorLane(DataScope $scope): string
    {
        return hash('sha256', $this->json([...NotificationScope::logical($scope), ...NotificationScope::fence($scope), 'principal_kind' => $scope->principalKind, 'principal_id' => $scope->principalId]));
    }

    private function decodeCursor(DataScope $scope, ?string $cursor): ?string
    {
        if ($cursor === null) {
            return null;
        }

        if (strlen($cursor) > 1024) {
            throw new InvalidArgumentException('Notification inbox cursor is invalid.');
        }

        try {
            $decoded = $this->encryption->decryptString($cursor);
        } catch (DecryptException $exception) {
            throw new InvalidArgumentException('Notification inbox cursor is invalid.', previous: $exception);
        }

        if (preg_match('/\A([a-f0-9]{64}):([a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})\z/D', $decoded, $matches) !== 1
            || ! hash_equals($this->cursorLane($scope), $matches[1])) {
            throw new InvalidArgumentException('Notification inbox cursor is invalid.');
        }

        return $matches[2];
    }

    private function reference(object $row): NotificationRef
    {
        $subject = $this->visibility->subject($row);

        return new NotificationRef(
            id: NotificationValue::text(NotificationValue::field(row: $row, name: 'id')),
            recipient: $subject->recipient,
            eventType: NotificationValue::text(NotificationValue::field(row: $row, name: 'event_type')),
            revision: NotificationValue::text(NotificationValue::field(row: $row, name: 'revision')),
            readAt: $this->date(NotificationValue::field(row: $row, name: 'read_at')),
            cancelledAt: $this->date(NotificationValue::field(row: $row, name: 'cancelled_at')),
            related: $subject->related,
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable(NotificationValue::text($value));
    }

    /** @return list<string> */
    private function dispatchedChannels(string $notificationId): array
    {
        return array_values($this->connection->table('ntf_deliveries')
            ->where('notification_id', $notificationId)
            ->whereIn('state', ['accepted', 'completed', 'unknown', 'sending'])
            ->orderBy('channel')
            ->pluck('channel')->map(NotificationValue::text(...))
            ->all());
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private function canonicalMap(array $value): array
    {
        if ($value !== [] && array_is_list($value)) {
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

        return array_map($this->canonicalValue(...), $value);
    }

    /** @param array<string, mixed>|null $value */
    private function json(?array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
