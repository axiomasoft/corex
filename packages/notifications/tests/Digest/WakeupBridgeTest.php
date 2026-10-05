<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Digest\Data\DigestWindow;
use CoreX\Notifications\Digest\Internal\DigestTick;
use CoreX\Notifications\Digest\Internal\DigestWakeupAdapter;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\EntityReference;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\NotificationSubject;
use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Notifications\Tests\TestCase;
use CoreX\Scheduling\Data\WakeupDelivery;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Scheduling\Data\WakeupWindow;
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Settings\Data\SettingsScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(TestCase::class);

final class AllowDigestPolicy implements NotificationPolicy
{
    public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool
    {
        return true;
    }
}

final class DigestBridgeSettingsRepository implements SettingsRepository
{
    public function get(string $namespace, string $key, ?SettingsScope $scope = null, ?JsonDocument $default = null): JsonDocument
    {
        return JsonDocuments::capture($default);
    }

    public function set(string $namespace, string $key, JsonDocument $value, ?SettingsScope $scope = null): void {}

    public function forget(string $namespace, string $key, ?SettingsScope $scope = null): void {}

    public function all(string $namespace, ?SettingsScope $scope = null): array
    {
        return [];
    }
}

it('maps a typed digest wakeup request without a workflow dependency', function (): void {
    $seen = null;
    $context = new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'digest-test', 'active', [], []));
    $window = new WakeupWindow(new DateTimeImmutable('2026-09-16T09:00:00Z'), new DateTimeImmutable('2026-09-16T10:00:00Z'), 'hourly');
    $request = new WakeupRequest($context, 'notification.digest.v1', 'digest:2026-09-16T09', new DateTimeImmutable('2026-09-16T10:00:00Z'), $window, JsonDocuments::capture([]));
    $adapter = new DigestWakeupAdapter(function (DigestTick $tick) use (&$seen): void {
        $seen = $tick;
    });

    $adapter->handle(new WakeupDelivery($request, 1, 1));

    expect($seen)->toBeInstanceOf(DigestTick::class)
        ->and($seen->requestKey)->toBe('digest:2026-09-16T09')
        ->and($seen->window->period)->toBe('hourly');
});

it('stamps new digest wakeups with notification-digest/1', function (): void {
    $digest = freezeDigest('stamp-canonical');
    $stored = json_decode(
        (string) DB::table('sys_wakeup_requests')->where('request_key', "notification.digest:{$digest->id}")->value('payload'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($stored['_corex']['schema'])->toBe(wakeup_bridge_schema())
        ->and($stored['_corex']['extension']['point'])->toBe(wakeup_bridge_point())
        ->and($stored['_corex']['extension']['id'])->toBe('notification.digest.v1')
        ->and($stored['_corex']['extension']['version'])->toBe('notification-digest/1')
        ->and($stored['data'])->toBe(['run_id' => $digest->id]);
});

it('invokes the adapter on exact version match with the unwrapped payload DTO', function (): void {
    $seen = [];
    bindRecordingDigestAdapter($seen);
    $digest = freezeDigest('exact-match');

    $result = wakeup_bridge_sweep(digestContext()->tenant, 10);

    expect($result->completed)->toBeGreaterThanOrEqual(1)
        ->and($seen)->toHaveCount(1)
        ->and($seen[0])->toBeInstanceOf(DigestTick::class)
        ->and($seen[0]->requestKey)->toBe("notification.digest:{$digest->id}")
        ->and($seen[0]->window->period)->toBe('hourly');
});

it('denies legacy unstamped rows by default without invoking the adapter', function (): void {
    $seen = [];
    bindRecordingDigestAdapter($seen);
    insertLegacyDigestWakeup(requestKey: 'legacy-denied', payload: ['run_id' => 'legacy-run']);

    $result = wakeup_bridge_sweep(digestContext()->tenant, 10);

    expect($result->failed)->toBe(1)
        ->and($seen)->toBe([])
        ->and(DB::table('sys_wakeup_requests')->where('request_key', 'legacy-denied')->value('failure_code'))->toBe('handler-stamp-missing');
});

it('admits an explicit bounded legacy fixture for this definition only', function (): void {
    $seen = [];
    app()['config']->set('corex-notifications.digest.accept_legacy_unstamped', true);
    app()->forgetInstance(NotificationsOptions::class);
    app()->forgetInstance('corex.notifications.digest-wakeup-handler');
    app()->forgetInstance(wakeup_bridge_registry());
    bindRecordingDigestAdapter($seen);
    insertLegacyDigestWakeup(requestKey: 'legacy-admitted', payload: ['run_id' => 'legacy-run']);

    $result = wakeup_bridge_sweep(digestContext()->tenant, 10);

    expect($result->completed)->toBe(1)
        ->and($seen)->toHaveCount(1)
        ->and($seen[0]->requestKey)->toBe('legacy-admitted');
});

it('quarantines a version mismatch without invoking the adapter', function (): void {
    $seen = [];
    bindRecordingDigestAdapter($seen);
    $registry = app(wakeup_bridge_registry());
    $definition = $registry->definition('notification.digest.v1');
    $envelope = $registry->envelope(definition: $definition, data: ['run_id' => 'mismatch-run']);
    $envelope['_corex']['extension']['version'] = 'notification-digest/0';
    insertLegacyDigestWakeup(requestKey: 'version-mismatch', payload: $envelope);

    $result = wakeup_bridge_sweep(digestContext()->tenant, 10);

    expect($result->failed)->toBe(1)
        ->and($seen)->toBe([])
        ->and(DB::table('sys_wakeup_requests')->where('request_key', 'version-mismatch')->value('failure_code'))->toBe('handler-version-mismatch');
});

function digestContext(): NotificationContext
{
    return new NotificationContext(
        tenant: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'notification-test', 'active', [], [])),
        actorKind: 'user',
        actorId: '11111111-1111-7111-8111-111111111112',
    );
}

function freezeDigest(string $requestKey): object
{
    app()->instance(NotificationPolicy::class, new AllowDigestPolicy);
    app()->instance(SettingsRepository::class, new DigestBridgeSettingsRepository);
    app()->forgetScopedInstances();
    app(NotificationCenter::class)->notify(digestContext(), new NotificationInput(
        requestKey: $requestKey,
        eventType: 'document.ready',
        recipient: new RecipientReference(kind: 'user', id: '11111111-1111-7111-8111-111111111112'),
        templateKey: 'document-ready',
        payload: JsonDocuments::capture(['message' => $requestKey]),
        related: new EntityReference(handle: 'document', id: 'document-1'),
    ));
    $end = new DateTimeImmutable('-1 minute');
    $start = $end->modify('-1 hour');
    DB::table('ntf_notifications')->update(['created_at' => $start->modify('+1 minute')]);

    $digest = app(DigestBuilder::class)->build(digestContext(), new DigestWindow(
        start: $start,
        end: $end,
        period: 'hourly',
    ));

    expect($digest)->not->toBeNull();

    return $digest;
}

/** @param list<DigestTick> $seen */
function bindRecordingDigestAdapter(array &$seen): void
{
    app()->instance(SettingsRepository::class, new DigestBridgeSettingsRepository);
    app()->instance(DigestWakeupAdapter::class, new DigestWakeupAdapter(function (DigestTick $tick) use (&$seen): void {
        $seen[] = $tick;
    }));
    app()->forgetInstance(wakeup_bridge_registry());
    app()->forgetScopedInstances();
}

/** @param array<array-key, mixed> $payload */
function insertLegacyDigestWakeup(string $requestKey, array $payload): void
{
    $now = new DateTimeImmutable('-1 minute');

    DB::table('sys_wakeup_requests')->insert([
        'id' => (string) Str::uuid7(),
        'workspace_scope_key' => 'account',
        'kind' => 'notification.digest.v1',
        'request_key' => $requestKey,
        'request_digest' => hash('sha256', $requestKey),
        'fire_at' => $now,
        'window_start' => $now->modify('-1 hour'),
        'window_end' => $now,
        'period' => 'hourly',
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        'status' => 'pending',
        'generation' => 0,
        'attempt' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}
