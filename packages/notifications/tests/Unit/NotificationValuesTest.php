<?php

declare(strict_types=1);

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Digest\Data\DigestWindow;
use CoreX\Notifications\Inbox\Data\EntityReference;
use CoreX\Notifications\Inbox\Data\InboxQuery;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\UnreadCount;
use CoreX\Notifications\Preferences\Data\NotificationPreference;
use CoreX\Notifications\Preferences\Data\PreferenceInput;

function notificationValueInput(array $overrides = []): NotificationInput
{
    return new NotificationInput(...array_merge([
        'requestKey' => 'request-1',
        'eventType' => 'document.ready',
        'recipient' => new RecipientReference(kind: 'user', id: 'user-1'),
        'templateKey' => 'document-ready',
        'payload' => JsonDocuments::capture([]),
        'related' => null,
    ], $overrides));
}

it('accepts the boundary values of every notification value object', function (): void {
    $window = new DigestWindow(new DateTimeImmutable('2026-01-01T00:00:00Z'), new DateTimeImmutable('2026-01-01T01:00:00Z'), 'hourly');

    expect(new InboxQuery(before: null, limit: 1))->limit->toBe(1)
        ->and(new InboxQuery(before: null, limit: 100))->limit->toBe(100)
        ->and(new EntityReference(str_repeat('h', 128), str_repeat('i', 128)))->handle->toHaveLength(128)
        ->and(UnreadCount::exact(0)->count)->toBe(0)
        ->and(UnreadCount::unavailable('count.timeout')->reason)->toBe('count.timeout')
        ->and(notificationValueInput(['channels' => ['in_app', 'mail'], 'source' => 'crm:deal.won'])->channels)->toBe(['in_app', 'mail'])
        ->and($window->period)->toBe('hourly')
        ->and(new PreferenceInput('document.ready', ['in_app'], 'daily', 'v1'))->digest->toBe('daily')
        ->and(new NotificationPreference('v1', ['in_app'], 'off'))->digest->toBe('off');
});

it('rejects invalid notification values', function (Closure $build): void {
    $build();
})->throws(InvalidArgumentException::class)->with([
    'inbox limit 0' => [fn () => new InboxQuery(before: null, limit: 0)],
    'inbox limit 101' => [fn () => new InboxQuery(before: null, limit: 101)],
    'empty entity handle' => [fn () => new EntityReference('', 'id')],
    'overlong entity id' => [fn () => new EntityReference('document', str_repeat('i', 129))],
    'negative unread count' => [fn () => UnreadCount::exact(-1)],
    'uppercase unavailable reason' => [fn () => UnreadCount::unavailable('Timeout')],
    'empty request key' => [fn () => notificationValueInput(['requestKey' => ''])],
    'overlong template key' => [fn () => notificationValueInput(['templateKey' => str_repeat('t', 129)])],
    'uppercase source' => [fn () => notificationValueInput(['source' => 'Crm'])],
    'channel map' => [fn () => notificationValueInput(['channels' => ['primary' => 'in_app']])],
    'duplicate channels' => [fn () => notificationValueInput(['channels' => ['in_app', 'in_app']])],
    'too many channels' => [fn () => notificationValueInput(['channels' => array_map(static fn (int $i): string => "c{$i}", range(1, 21))])],
    'uppercase channel' => [fn () => notificationValueInput(['channels' => ['InApp']])],
    'weekly digest window' => [fn () => new DigestWindow(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-08'), 'weekly')],
    'empty digest window' => [fn () => new DigestWindow(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-01'), 'daily')],
    'preference input without version' => [fn () => new PreferenceInput('document.ready', ['in_app'], 'daily', '')],
    'preference input unknown digest' => [fn () => new PreferenceInput('document.ready', ['in_app'], 'weekly', 'v1')],
    'preference input without channels' => [fn () => new PreferenceInput('document.ready', [], 'daily', 'v1')],
    'preference without version' => [fn () => new NotificationPreference('', ['in_app'], 'daily')],
    'preference with duplicate channels' => [fn () => new NotificationPreference('v1', ['in_app', 'in_app'], 'daily')],
]);
