<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Exceptions\NotificationOperationConflict;
use CoreX\Notifications\Preferences\Contracts\PreferenceRepository;
use CoreX\Notifications\Preferences\Data\NotificationPreference;
use CoreX\Notifications\Preferences\Data\PreferenceInput;
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Settings\Data\SettingsScope;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SettingsPreferenceRepository implements PreferenceRepository
{
    private const string NAMESPACE = 'corex.notifications';

    public function __construct(
        private ConnectionInterface $connection,
        private SettingsRepository $settings,
        private NotificationScope $scopes,
        private CurrentWorkScope $current,
    ) {}

    public function get(NotificationContext $context, string $eventType): NotificationPreference
    {
        $scope = $this->scopes->of($context);
        $this->current->assert($scope, $this->connection);
        $recipient = $this->recipient($scope);
        $state = $this->connection->table('ntf_recipient_state')
            ->where($recipient)
            ->first();
        $value = JsonDocuments::decode($this->settings->get(namespace: $this->namespace($scope), key: $eventType, scope: SettingsScope::account(), default: JsonDocuments::capture($this->defaultPreference())));

        return $this->preference(value: $value, version: NotificationValue::text($state === null ? '1' : NotificationValue::text($state->preference_revision)));
    }

    public function set(NotificationContext $context, PreferenceInput $input): NotificationPreference
    {
        $scope = $this->scopes->of($context);
        $recipient = $this->recipient($scope);

        return $this->connection->transaction(function () use ($scope, $recipient, $input): NotificationPreference {
            ReferenceWrites::enlist($this->connection, $scope, 'axiomasoft/corex-notifications', 'preference', ['recipient' => $recipient, 'preference' => $input]);
            $this->current->assert($scope, $this->connection);
            $this->connection->table('ntf_recipient_state')->insertOrIgnore($recipient + ['preference_revision' => '1']);
            $state = $this->connection->table('ntf_recipient_state')->where($recipient)->lockForUpdate()->firstOrFail();
            $version = NotificationValue::text($state->preference_revision);

            if (! hash_equals(NotificationValue::text($version), $input->expectedVersion)) {
                throw new NotificationOperationConflict('Notification preference version conflicts with the current value.');
            }

            $next = (string) (NotificationValue::integer($version) + 1);
            $value = ['channels' => $input->channels, 'digest' => $input->digest];
            $this->settings->set(namespace: $this->namespace($scope), key: $input->eventType, value: JsonDocuments::capture($value), scope: SettingsScope::account());
            $this->connection->table('ntf_recipient_state')
                ->where($recipient)
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

    /** @return array<string, string> */
    private function recipient(DataScope $scope): array
    {
        return [...NotificationScope::logical($scope), 'kind' => $scope->principalKind, 'id' => $scope->principalId];
    }

    private function namespace(DataScope $scope): string
    {
        return self::NAMESPACE.'.'.hash('sha256', json_encode($this->recipient($scope), JSON_THROW_ON_ERROR));
    }
}
