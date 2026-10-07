<?php

declare(strict_types=1);

namespace CoreX\Notifications\Preferences\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Exceptions\NotificationAccessDenied;
use CoreX\Notifications\Inbox\Exceptions\NotificationOperationConflict;
use CoreX\Notifications\Preferences\Contracts\PreferenceRepository;
use CoreX\Notifications\Preferences\Data\NotificationPreference;
use CoreX\Notifications\Preferences\Data\PreferenceInput;
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Settings\Data\SettingsScope;
use Illuminate\Database\ConnectionInterface;

final readonly class SettingsPreferenceRepository implements PreferenceRepository
{
    private const string NAMESPACE = 'corex.notifications';

    public function __construct(
        private ConnectionInterface $connection,
        private SettingsRepository $settings,
    ) {}

    public function get(NotificationContext $context, string $eventType): NotificationPreference
    {
        $recipient = $this->recipient($context);
        $state = $this->connection->table('ntf_recipient_state')
            ->where('kind', $recipient['kind'])
            ->where('id', $recipient['id'])
            ->first();
        $value = JsonDocuments::decode($this->settings->get(namespace: self::NAMESPACE, key: $eventType, scope: $this->scope($recipient['id']), default: JsonDocuments::capture($this->defaultPreference())));

        return $this->preference(value: $value, version: Value::text($state === null ? '1' : Value::text($state->preference_revision)));
    }

    public function set(NotificationContext $context, PreferenceInput $input): NotificationPreference
    {
        $recipient = $this->recipient($context);

        return $this->connection->transaction(function () use ($recipient, $input): NotificationPreference {
            $this->connection->table('ntf_recipient_state')->insertOrIgnore([
                'kind' => $recipient['kind'],
                'id' => $recipient['id'],
                'preference_revision' => '1',
            ]);
            $state = $this->connection->table('ntf_recipient_state')
                ->where('kind', $recipient['kind'])
                ->where('id', $recipient['id'])
                ->lockForUpdate()
                ->firstOrFail();
            $version = Value::text($state->preference_revision);

            if (! hash_equals(Value::text($version), $input->expectedVersion)) {
                throw new NotificationOperationConflict('Notification preference version conflicts with the current value.');
            }

            $next = (string) (Value::integer($version) + 1);
            $value = ['channels' => $input->channels, 'digest' => $input->digest];
            $this->settings->set(namespace: self::NAMESPACE, key: $input->eventType, value: JsonDocuments::capture($value), scope: $this->scope($recipient['id']));
            $this->connection->table('ntf_recipient_state')
                ->where('kind', $recipient['kind'])
                ->where('id', $recipient['id'])
                ->update(['preference_revision' => $next]);

            return new NotificationPreference(version: $next, channels: $input->channels, digest: $input->digest);
        });
    }

    private function preference(mixed $value, string $version): NotificationPreference
    {
        if (! is_array($value) || ! isset($value['channels'], $value['digest']) || ! is_array($value['channels']) || ! is_string($value['digest'])) {
            throw new NotificationOperationConflict('Notification preference is malformed.');
        }

        $channels = [];
        foreach ($value['channels'] as $channel) {
            if (! is_string($channel)) {
                throw new NotificationOperationConflict('Notification preference channels are malformed.');
            }

            $channels[] = $channel;
        }

        return new NotificationPreference(version: $version, channels: $channels, digest: $value['digest']);
    }

    /** @return array{channels: list<string>, digest: string} */
    private function defaultPreference(): array
    {
        return ['channels' => ['in_app'], 'digest' => 'instant'];
    }

    /** @return array{kind: string, id: string} */
    private function recipient(NotificationContext $context): array
    {
        if ($context->actorId === null || $context->actorId === '') {
            throw new NotificationAccessDenied('Notification preferences require an authenticated recipient.');
        }

        return ['kind' => $context->actorKind, 'id' => $context->actorId];
    }

    private function scope(string $recipientId): SettingsScope
    {
        return SettingsScope::user($recipientId);
    }
}
