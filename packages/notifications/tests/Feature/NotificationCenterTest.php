<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\DeliveryOutcome;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Delivery\Internal\DeliveryCoordinator;
use CoreX\Notifications\Delivery\Internal\DeliveryStore;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Digest\Data\DigestWindow;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\EntityReference;
use CoreX\Notifications\Inbox\Data\InboxQuery;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\NotificationSubject;
use CoreX\Notifications\Inbox\Exceptions\NotificationAccessDenied;
use CoreX\Notifications\Inbox\Exceptions\NotificationOperationConflict;
use CoreX\Notifications\Inbox\Internal\DenyNotificationPolicy;
use CoreX\Notifications\Preferences\Contracts\PreferenceRepository;
use CoreX\Notifications\Preferences\Data\PreferenceInput;
use CoreX\Notifications\Tests\TestCase;
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Settings\Data\SettingsScope;
use Illuminate\Support\Facades\DB;

uses(TestCase::class);

final class AllowAllNotificationPolicy implements NotificationPolicy
{
    public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool
    {
        return true;
    }
}

final class InMemorySettingsRepository implements SettingsRepository
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function get(string $namespace, string $key, ?SettingsScope $scope = null, ?JsonDocument $default = null): JsonDocument
    {
        return JsonDocuments::capture($this->values["{$namespace}:{$key}"] ?? $default);
    }

    public function set(string $namespace, string $key, JsonDocument $value, ?SettingsScope $scope = null): void
    {
        $this->values["{$namespace}:{$key}"] = $value;
    }

    public function forget(string $namespace, string $key, ?SettingsScope $scope = null): void
    {
        unset($this->values["{$namespace}:{$key}"]);
    }

    public function all(string $namespace, ?SettingsScope $scope = null): array
    {
        return [];
    }
}

final class FixedTenantContextResolver implements TenantContextResolver
{
    public function current(): TenantContext
    {
        return notificationContext()->tenant;
    }
}

function notificationContext(): NotificationContext
{
    return new NotificationContext(
        tenant: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'notification-test', 'active', [], [])),
        actorKind: 'user',
        actorId: '11111111-1111-7111-8111-111111111112',
    );
}

function notificationInput(string $requestKey, string $message = 'hello'): NotificationInput
{
    return new NotificationInput(
        requestKey: $requestKey,
        eventType: 'document.ready',
        recipient: new RecipientReference(kind: 'user', id: '11111111-1111-7111-8111-111111111112'),
        templateKey: 'document-ready',
        payload: JsonDocuments::capture(['message' => $message]),
        related: new EntityReference(handle: 'document', id: 'document-1'),
    );
}

function allowNotifications(): void
{
    app()->instance(NotificationPolicy::class, new AllowAllNotificationPolicy);
    app()->forgetScopedInstances();
}

function notificationPreferences(): PreferenceRepository
{
    app()->instance(SettingsRepository::class, new InMemorySettingsRepository);
    app()->forgetScopedInstances();

    return app(PreferenceRepository::class);
}

it('persists a tenant-local inbox intent and replays an identical request', function (): void {
    allowNotifications();
    $center = app(NotificationCenter::class);

    $created = $center->notify(notificationContext(), notificationInput('request-1'));
    $replayed = $center->notify(notificationContext(), notificationInput('request-1'));

    expect($replayed->id)->toBe($created->id)
        ->and(DB::table('ntf_notifications')->count())->toBe(1)
        ->and(DB::table('ntf_deliveries')->where('notification_id', $created->id)->value('state'))->toBe('pending')
        ->and($center->unreadCount(notificationContext()))->toBe(1);

    $center->markRead(notificationContext(), $created->id);

    expect($center->unreadCount(notificationContext()))->toBe(0)
        ->and($center->inbox(notificationContext(), new InboxQuery(before: null)))->toHaveCount(1);
});

it('does not admit notification work before a consumer policy allows it', function (): void {
    app()->instance(NotificationPolicy::class, new DenyNotificationPolicy);
    app()->forgetScopedInstances();

    expect(fn (): mixed => app(NotificationCenter::class)->notify(notificationContext(), notificationInput('denied')))
        ->toThrow(NotificationAccessDenied::class);
});

it('rejects a replay key with different notification bytes', function (): void {
    allowNotifications();
    $center = app(NotificationCenter::class);
    $center->notify(notificationContext(), notificationInput('conflict', 'first'));

    expect(fn (): mixed => $center->notify(notificationContext(), notificationInput('conflict', 'second')))
        ->toThrow(NotificationOperationConflict::class);
});

it('cancels pending deliveries but reports irrevocable dispatched channels', function (): void {
    allowNotifications();
    $center = app(NotificationCenter::class);
    $notification = $center->notify(notificationContext(), notificationInput('cancel'));

    $cancelled = $center->cancel(notificationContext(), $notification->id);

    expect($cancelled->cancelled)->toBeTrue()
        ->and($cancelled->alreadyDispatched)->toBe([])
        ->and(DB::table('ntf_deliveries')->where('notification_id', $notification->id)->value('state'))->toBe('cancelled');
});

it('rejects a stale delivery completion after a newer claim epoch', function (): void {
    allowNotifications();
    $notification = app(NotificationCenter::class)->notify(notificationContext(), notificationInput('claim-epoch'));
    $id = DB::table('ntf_deliveries')->where('notification_id', $notification->id)->value('id');
    $deliveries = app(DeliveryStore::class);

    $first = $deliveries->claim($id);
    DB::table('ntf_deliveries')->where('id', $id)->update(['state' => 'pending', 'lease_until' => null]);
    $second = $deliveries->claim($id);

    expect($first)->not->toBeNull()->and($second)->not->toBeNull();
    $deliveries->complete($first, new DeliveryOutcome(kind: 'accepted', providerRef: 'old', retryAfter: null, reason: 'old-worker'));

    expect(DB::table('ntf_deliveries')->where('id', $id)->value('state'))->toBe('claimed')
        ->and(DB::table('ntf_deliveries')->where('id', $id)->value('claim_epoch'))->toBe(2);

    $deliveries->complete($second, new DeliveryOutcome(kind: 'accepted', providerRef: 'new', retryAfter: null, reason: 'current-worker'));

    expect(DB::table('ntf_deliveries')->where('id', $id)->value('state'))->toBe('sent')
        ->and(DB::table('ntf_deliveries')->where('id', $id)->value('provider_ref'))->toBe('new');
});

it('does not claim a delivery before its next attempt time', function (): void {
    allowNotifications();
    $notification = app(NotificationCenter::class)->notify(notificationContext(), notificationInput('next-attempt'));
    $id = DB::table('ntf_deliveries')->where('notification_id', $notification->id)->value('id');
    $deliveries = app(DeliveryStore::class);

    DB::table('ntf_deliveries')->where('id', $id)->update(['next_attempt' => now()->addHour()]);
    expect($deliveries->claim($id))->toBeNull();

    DB::table('ntf_deliveries')->where('id', $id)->update(['next_attempt' => now()->subMinute()]);
    expect($deliveries->claim($id))->not->toBeNull();
});

it('adapts per-recipient preferences through Settings with an optimistic revision', function (): void {
    $preferences = notificationPreferences();
    $context = notificationContext();

    expect($preferences->get($context, 'document.ready')->channels)->toBe(['in_app']);

    $updated = $preferences->set($context, new PreferenceInput(
        eventType: 'document.ready',
        channels: ['in_app'],
        digest: 'daily',
        expectedVersion: '1',
    ));

    expect($updated->version)->toBe('2')
        ->and($preferences->get($context, 'document.ready')->digest)->toBe('daily')
        ->and(fn (): mixed => $preferences->set($context, new PreferenceInput(
            eventType: 'document.ready',
            channels: ['in_app'],
            digest: 'instant',
            expectedVersion: '1',
        )))->toThrow(NotificationOperationConflict::class);
});

it('admits an in-app delivery through the registered driver only after the durable claim', function (): void {
    allowNotifications();
    app()->instance(TenantContextResolver::class, new FixedTenantContextResolver);
    app()->forgetScopedInstances();
    $notification = app(NotificationCenter::class)->notify(notificationContext(), notificationInput('coordinator'));
    $id = DB::table('ntf_deliveries')->where('notification_id', $notification->id)->value('id');

    app(DeliveryCoordinator::class)->deliver($id);

    expect(DB::table('ntf_deliveries')->where('id', $id)->value('state'))->toBe('sent')
        ->and(DB::table('ntf_deliveries')->where('id', $id)->value('provider_ref'))->not->toBeNull();
});

it('freezes at most one hundred notifications in a half-open digest window', function (): void {
    allowNotifications();
    app(NotificationCenter::class)->notify(notificationContext(), notificationInput('digest-window'));
    $now = new DateTimeImmutable('now');

    $digest = app(DigestBuilder::class)->build(notificationContext(), new DigestWindow(
        start: $now->modify('-1 minute'),
        end: $now->add(new DateInterval('PT1M')),
        period: 'hourly',
    ));

    expect($digest)->not->toBeNull()
        ->and(DB::table('ntf_digest_runs')->where('id', $digest->id)->value('state'))->toBe('frozen')
        ->and(DB::table('ntf_digest_items')->where('run_id', $digest->id)->count())->toBe(1)
        ->and(DB::table('sys_wakeup_requests')->where('request_key', "notification.digest:{$digest->id}")->count())->toBe(1);
});

it('admits a closed digest through the shared wakeup runner and freezes residual batches', function (): void {
    allowNotifications();
    $preferences = notificationPreferences();
    $preferences->set(notificationContext(), new PreferenceInput(
        eventType: 'document.ready',
        channels: ['in_app'],
        digest: 'hourly',
        expectedVersion: '1',
    ));
    $center = app(NotificationCenter::class);

    foreach (range(1, 101) as $number) {
        $center->notify(notificationContext(), notificationInput("digest-residual-{$number}"));
    }
    DB::table('ntf_notifications')->update(['created_at' => now()->subSeconds(90)]);
    $window = new DigestWindow(
        start: new DateTimeImmutable('-2 minutes'),
        end: new DateTimeImmutable('-1 minute'),
        period: 'hourly',
    );
    $first = app(DigestBuilder::class)->build(notificationContext(), $window);

    expect($first)->not->toBeNull()
        ->and(DB::table('ntf_digest_items')->where('run_id', $first->id)->count())->toBe(100);

    wakeup_bridge_sweep(notificationContext()->tenant, 100);

    expect(DB::table('ntf_digest_runs')->where('id', $first->id)->value('state'))->toBe('admitted')
        ->and(DB::table('ntf_deliveries')->where('digest_run_id', $first->id)->value('state'))->toBe('pending')
        ->and(DB::table('ntf_digest_runs')->count())->toBe(2)
        ->and(DB::table('ntf_digest_items')->count())->toBe(101);
});

it('keeps an admitted digest effect when a later preference change disables future digests', function (): void {
    allowNotifications();
    $preferences = notificationPreferences();
    $preferences->set(notificationContext(), new PreferenceInput(
        eventType: 'document.ready',
        channels: ['in_app'],
        digest: 'hourly',
        expectedVersion: '1',
    ));
    $notification = app(NotificationCenter::class)->notify(notificationContext(), notificationInput('digest-admission'));
    DB::table('ntf_notifications')->where('id', $notification->id)->update(['created_at' => now()->subSeconds(90)]);
    $digest = app(DigestBuilder::class)->build(notificationContext(), new DigestWindow(
        start: new DateTimeImmutable('-2 minutes'),
        end: new DateTimeImmutable('-1 minute'),
        period: 'hourly',
    ));

    expect($digest)->not->toBeNull();
    wakeup_bridge_sweep(notificationContext()->tenant, 100);
    $deliveryId = DB::table('ntf_deliveries')->where('digest_run_id', $digest->id)->value('id');

    $preferences->set(notificationContext(), new PreferenceInput(
        eventType: 'document.ready',
        channels: ['in_app'],
        digest: 'off',
        expectedVersion: '2',
    ));
    app(DeliveryCoordinator::class)->deliver($deliveryId);

    expect(DB::table('ntf_deliveries')->where('id', $deliveryId)->value('state'))->toBe('sent')
        ->and(DB::table('ntf_digest_runs')->where('id', $digest->id)->value('state'))->toBe('sent');
});
