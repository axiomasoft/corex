<?php

declare(strict_types=1);

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Digest\Data\DigestWindow;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\EntityReference;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\NotificationSubject;
use CoreX\Notifications\Preferences\Contracts\PreferenceRepository;
use CoreX\Notifications\Preferences\Data\NotificationPreference;
use CoreX\Notifications\Runtime\Internal\DigestTick;
use CoreX\Notifications\Runtime\Internal\DigestWakeupAdapter;
use CoreX\Notifications\Runtime\Internal\NotificationDigestDueSource;
use CoreX\Notifications\Tests\TestCase;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupDelivery;
use CoreX\Scheduling\Data\WakeupIdentity;
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
    $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal($context);
    $request = new WakeupRequest($scope, 'notification.digest.v1', 'notification-digest/1', 'digest:2026-09-16T09', new DateTimeImmutable('2026-09-16T10:00:00Z'), $window, JsonDocuments::capture([]));
    $adapter = new DigestWakeupAdapter(function (DigestTick $tick) use (&$seen): void {
        $seen = $tick;
    });

    $adapter->handle(new WakeupDelivery($request, $context, 1, 1, 'digest-tick'));

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

it('quarantines a version mismatch without invoking the adapter', function (): void {
    $seen = [];
    bindRecordingDigestAdapter($seen);
    $registry = resolve(wakeup_bridge_registry());
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
    resolve(NotificationCenter::class)->notify(digestContext(), new NotificationInput(
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

    $digest = resolve(DigestBuilder::class)->build(digestContext(), new DigestWindow(
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

    $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal(digestContext()->tenant);
    resolve(WakeupScheduler::class)->schedule(new WakeupRequest(
        $scope,
        'notification.digest.v1',
        'notification-digest/1',
        $requestKey,
        $now,
        new WakeupWindow($now->modify('-1 hour'), $now, 'hourly'),
        JsonDocuments::capture(['run_id' => 'legacy-test']),
    ));
    DB::table('sys_wakeup_requests')->where('request_key', $requestKey)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
}

it('keeps real frozen digests behind durable admission and cancellation', function (string $mode): void {
    $digest = freezeDigest('real-admission');
    $preferences = Mockery::mock(PreferenceRepository::class);
    $preferences->shouldReceive('get')->andReturn(new NotificationPreference('1', ['in_app'], 'hourly'));
    app()->forgetScopedInstances();
    app()->instance(PreferenceRepository::class, $preferences);
    $key = 'notification.digest:'.$digest->id;
    $row = DB::table('sys_wakeup_requests')->where('request_key', $key)->first();

    if ($mode === 'lease') {
        DB::table('sys_wakeup_requests')->where('request_key', $key)->update(['status' => 'running', 'lease_until' => new DateTimeImmutable('+1 hour')]);
    } elseif ($mode === 'retry') {
        DB::table('sys_wakeup_requests')->where('request_key', $key)->update(['status' => 'retry-scheduled', 'attempt' => 1, 'retry_at' => new DateTimeImmutable('+1 hour')]);
    } elseif ($mode === 'cancelled') {
        resolve(WakeupScheduler::class)->cancel(resolve(DataScopeFactory::class)->forCurrentPrincipal(digestContext()->tenant), new WakeupIdentity('notification.digest.v1', $key));
    } elseif ($mode !== 'valid') {
        $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);

        if ($mode === 'missing') {
            $payload = $payload['data'];
        } else {
            $payload['_corex']['extension']['version'] = 'notification-digest/0';
        }
        DB::table('sys_wakeup_requests')->where('request_key', $key)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
    }
    wakeup_bridge_sweep(digestContext()->tenant, 10);
    wakeup_bridge_sweep(digestContext()->tenant, 10);
    expect(DB::table('ntf_deliveries')->where('digest_run_id', $digest->id)->count())->toBe($mode === 'valid' ? 1 : 0)
        ->and(DB::table('ntf_digest_runs')->where('id', $digest->id)->value('state'))->toBe($mode === 'valid' ? 'admitted' : 'frozen');
})->with(['mismatch', 'missing', 'cancelled', 'lease', 'retry', 'valid']);

it('refuses to recover a digest whose stored work provenance was replaced', function (): void {
    $digest = freezeDigest('tampered-provenance');
    DB::table('sys_wakeup_requests')->delete();
    DB::table('ntf_digest_runs')->where('id', $digest->id)->update(['scope_seal' => str_repeat('0', 64)]);

    $result = resolve(NotificationDigestDueSource::class)->sweep(digestContext()->tenant, new DateTimeImmutable, 10);

    expect($result->failed)->toBe(1)
        ->and($result->retried)->toBe(0)
        ->and(DB::table('sys_wakeup_requests')->count())->toBe(0)
        ->and(DB::table('ntf_deliveries')->where('digest_run_id', $digest->id)->count())->toBe(0);
});

it('recovers the concrete frozen run after terminal history longer than the source limit', function (): void {
    $old = freezeDigest('history');
    DB::table('ntf_digest_runs')->where('id', $old->id)->update(['state' => 'skipped']);
    $history = (array) DB::table('ntf_digest_runs')->where('id', $old->id)->first();
    $history['id'] = (string) Str::uuid7();
    $history['batch_seq'] = 1;
    $history['state'] = 'admitted';
    DB::table('ntf_digest_runs')->insert($history);
    $new = freezeDigest('due-after-history');
    DB::table('ntf_digest_runs')->where('state', '!=', 'frozen')->update(['period_start' => new DateTimeImmutable('-3 hours'), 'period_end' => new DateTimeImmutable('-2 hours')]);
    DB::table('sys_wakeup_requests')->delete();
    $preferences = Mockery::mock(PreferenceRepository::class);
    $preferences->shouldReceive('get')->andReturn(new NotificationPreference('1', ['in_app'], 'hourly'));
    app()->forgetScopedInstances();
    app()->instance(PreferenceRepository::class, $preferences);
    $source = resolve(NotificationDigestDueSource::class);
    $result = $source->sweep(digestContext()->tenant, new DateTimeImmutable, 1);
    expect($result->completed)->toBe(0)
        ->and(DB::table('sys_wakeup_requests')->where('request_key', 'notification.digest:'.$new->id)->count())->toBe(1)
        ->and(DB::table('sys_wakeup_requests')->where('request_key', 'notification.digest:'.$old->id)->count())->toBe(0);
    wakeup_bridge_sweep(digestContext()->tenant, 1);
    expect(DB::table('ntf_deliveries')->where('digest_run_id', $new->id)->count())->toBe(1)
        ->and($source->nextDue(digestContext()->tenant))->toBeNull();
});
