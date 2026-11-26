<?php

declare(strict_types=1);

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\RecipientReference;
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
use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Notifications\Runtime\Internal\DeliveryReceiptProjector;
use CoreX\Notifications\Runtime\Internal\DeliveryStore;
use CoreX\Notifications\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

uses(TestCase::class);

function retentionContext(): NotificationContext
{
    return new NotificationContext(
        tenant: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'retention-test', 'active', [], [])),
        actorKind: 'user',
        actorId: '11111111-1111-7111-8111-111111111112',
    );
}

function retentionInput(string $key, ?string $message = null): NotificationInput
{
    return new NotificationInput(
        requestKey: $key,
        eventType: 'document.ready',
        recipient: new RecipientReference(kind: 'user', id: '11111111-1111-7111-8111-111111111112'),
        templateKey: 'document-ready',
        payload: JsonDocuments::capture(['message' => $message ?? $key]),
        related: new EntityReference(handle: 'document', id: 'document-1'),
    );
}

/** Notifies, then settles the notification as described, with its deliveries in $deliveryState. */
function retainedNotification(string $key, ?string $settled, string $deliveryState = 'completed', ?int $daysAgo = 120): string
{
    app()->instance(NotificationPolicy::class, new class implements NotificationPolicy
    {
        public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool
        {
            return true;
        }
    });
    app()->forgetScopedInstances();
    $id = resolve(NotificationCenter::class)->notify(retentionContext(), retentionInput($key))->id;
    DB::table('ntf_deliveries')->where('notification_id', $id)->update(['state' => $deliveryState]);

    if ($settled !== null) {
        DB::table('ntf_notifications')->where('id', $id)->update([$settled => now()->subDays($daysAgo ?? 0)]);
    }

    return $id;
}

/** @return list<string> */
function remainingNotifications(): array
{
    return DB::table('ntf_notifications')->whereNull('pruned_at')->orderBy('event_id')->pluck('event_id')->all();
}

it('compacts old read notifications and keeps every unread and recently settled notification', function (): void {
    foreach (['read_at', 'dismissed_at', 'cancelled_at'] as $column) {
        retainedNotification("old-{$column}", $column);
    }
    retainedNotification('old-unread', null);
    retainedNotification('recent-read', 'read_at', daysAgo: 30);
    $recent = retainedNotification('recent-cancellation', 'read_at');
    DB::table('ntf_notifications')->where('id', $recent)->update(['cancelled_at' => now()]);
    foreach (['permanently-failed', 'exhausted', 'skipped', 'cancelled'] as $state) {
        retainedNotification("old-{$state}", 'read_at', $state);
    }

    $this->artisan('corex:notifications:prune')->assertSuccessful()
        ->expectsOutputToContain('pruned 5');

    expect(remainingNotifications())->toBe(['old-cancelled_at', 'old-dismissed_at', 'old-unread', 'recent-cancellation', 'recent-read'])
        ->and(DB::table('ntf_deliveries')->whereNull('message')->count())->toBe(5);
});

it('preserves the original replay reference and rejects conflicting content after pruning', function (): void {
    $id = retainedNotification('replay', 'read_at');
    $center = resolve(NotificationCenter::class);
    $expected = $center->notify(retentionContext(), retentionInput('replay'));
    $delivery = DB::table('ntf_deliveries')->where('notification_id', $id)->firstOrFail();
    $this->artisan('corex:notifications:prune')->assertSuccessful();

    expect($center->notify(retentionContext(), retentionInput('replay')))->toEqual($expected)
        ->and(fn () => $center->notify(retentionContext(), retentionInput('replay', 'changed')))->toThrow(NotificationOperationConflict::class)
        ->and($center->inbox(retentionContext(), new InboxQuery(null))->items)->toBe([])
        ->and($center->unreadCount(retentionContext())->count)->toBe(0)
        ->and($center->cancel(retentionContext(), $id)->cancelled)->toBeFalse()
        ->and(fn () => $center->markRead(retentionContext(), $id))->toThrow(NotificationAccessDenied::class)
        ->and(DB::table('ntf_notifications')->where('id', $id)->value('payload'))->toBe('{}')
        ->and(DB::table('ntf_notifications')->where('id', $id)->value('related'))->toBeNull()
        ->and(DB::table('ntf_deliveries')->pluck('id')->all())->toBe([$delivery->id])
        ->and(DB::table('ntf_deliveries')->value('message_hash'))->toBe($delivery->message_hash);
    $start = now('UTC')->subHour()->toDateTimeImmutable();
    expect(resolve(DigestBuilder::class)->build(retentionContext(), new DigestWindow(
        start: $start, end: $start->modify('+1 day'), period: 'daily',
    )))->toBeNull();
    $this->artisan('corex:notifications:prune')->assertSuccessful()->expectsOutputToContain('pruned 0');
});

it('preserves provider confirmation and its idempotent replay across retention', function (): void {
    $id = retainedNotification('provider', 'read_at', 'accepted');
    $delivery = DB::table('ntf_deliveries')->where('notification_id', $id);
    $ticketId = '11111111-1111-7111-8111-111111111123';
    $delivery->update(['provider_namespace' => 'channel.email', 'target_reference' => 'recipient', 'provider_ticket' => $ticketId]);
    $row = $delivery->firstOrFail();
    $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal(retentionContext()->tenant);
    $status = new ProviderEffectStatus(
        effect: DeliveryStore::effectRef(scope: $scope, namespace: 'channel.email', key: $row->delivery_key),
        ticket: new AdmissionTicket($ticketId, $scope, 1, new OperationKey('notification-effect', 1), AdmissionKind::ProviderSend),
        observation: new ProviderObservation(ProviderObservationKind::Confirmed, 'confirmed-reference', 'provider'),
        state: 'observed', ownerRef: $row->id, claimToken: 'claim-token',
        digest: DeliveryStore::providerDigest(messageHash: $row->message_hash, namespace: 'channel.email', target: 'recipient'),
    );
    $this->mock(ProviderEffectRecovery::class)->shouldReceive('assertReceipt')->twice()
        ->with($status, DB::connection())->andReturn(retentionContext()->tenant);

    $this->artisan('corex:notifications:prune')->expectsOutputToContain('pruned 0')->assertSuccessful();
    $projector = resolve(DeliveryReceiptProjector::class);
    expect($projector->project($status))->toBe($row->id)->and($delivery->value('state'))->toBe('completed');
    $completed = $delivery->firstOrFail();

    $this->artisan('corex:notifications:prune')->expectsOutputToContain('pruned 1')->assertSuccessful();
    expect($projector->project($status))->toBe($row->id)
        ->and($delivery->firstOrFail())->toEqual($completed);
});

it('only prunes notifications in the verified logical scope and physical generation', function (): void {
    retainedNotification('local', 'read_at');
    foreach (['workspace_scope' => 'workspace:other', 'account_id' => 'other', 'account_incarnation' => 'previous'] as $column => $value) {
        $id = retainedNotification($column, 'read_at');
        DB::table('ntf_notifications')->where('id', $id)->update([$column => $value]);
    }
    $id = retainedNotification('old-generation', 'read_at');
    DB::table('ntf_deliveries')->where('notification_id', $id)->increment('binding_generation');
    $this->artisan('corex:notifications:prune')->assertSuccessful();
    expect(remainingNotifications())->toBe(['account_id', 'account_incarnation', 'old-generation', 'workspace_scope']);
});

it('keeps a settled notification while a delivery is unfinished', function (string $deliveryState): void {
    $id = retainedNotification('open', 'read_at', $deliveryState);

    $this->artisan('corex:notifications:prune')->assertSuccessful();

    expect(DB::table('ntf_notifications')->where('id', $id)->whereNull('pruned_at')->exists())->toBeTrue()
        ->and(DB::table('ntf_deliveries')->where('notification_id', $id)->value('state'))->toBe($deliveryState);
})->with(['pending', 'claimed', 'sending', 'retry-scheduled', 'unknown', 'quarantined', 'accepted', 'future-state']);

it('keeps a settled notification that belongs to a digest', function (): void {
    $id = retainedNotification('digested', 'read_at');
    $runId = '22222222-2222-7222-8222-222222222222';
    DB::table('ntf_digest_runs')->insert([
        'id' => $runId, 'account_id' => 'a', 'account_incarnation' => 'i', 'workspace_scope' => 'account',
        'scope_key' => str_repeat('a', 64), 'scope' => '{}', 'scope_seal' => str_repeat('b', 64), 'recipient_kind' => 'user',
        'recipient_id' => 'r', 'period' => 'daily', 'period_start' => now(), 'period_end' => now(), 'batch_seq' => 1,
        'membership_hash' => str_repeat('c', 64), 'state' => 'sent', 'created_at' => now(),
    ]);
    DB::table('ntf_digest_items')->insert(['id' => '33333333-3333-7333-8333-333333333333', 'run_id' => $runId, 'notification_id' => $id, 'channel' => 'email']);

    $this->artisan('corex:notifications:prune')->assertSuccessful();

    expect(remainingNotifications())->toBe(['digested']);
});

it('prunes a backlog larger than one batch and honours the configured window', function (): void {
    foreach (range(1, 5) as $n) {
        retainedNotification("backlog-{$n}", 'read_at', daysAgo: 40);
    }
    config(['corex-notifications.retention.settled_days' => 60]);
    app()->forgetInstance(NotificationsOptions::class);

    $this->artisan('corex:notifications:prune', ['--batch' => '2'])->assertSuccessful();
    expect(remainingNotifications())->toHaveCount(5);

    $this->artisan('corex:notifications:prune', ['--batch' => '2', '--older-than-days' => '30'])->assertSuccessful()
        ->expectsOutputToContain('pruned 5');
    expect(remainingNotifications())->toBe([]);
});

it('rejects a malformed window or batch size', function (array $options): void {
    $this->artisan('corex:notifications:prune', $options)->assertExitCode(2);
})->with([
    'zero days' => [['--older-than-days' => '0']],
    'text days' => [['--older-than-days' => '90d']],
    'zero batch' => [['--batch' => '0']],
    'huge batch' => [['--batch' => '10001']],
]);

it('rejects a connection outside the verified scope before reading or updating notifications', function (): void {
    retainedNotification('local', 'read_at');
    config(['database.connections.foreign' => config('database.connections.sqlite')]);
    expect(fn () => $this->artisan('corex:notifications:prune', ['--database' => 'foreign'])->run())
        ->toThrow(ScopeContextMismatch::class)
        ->and(remainingNotifications())->toBe(['local']);
});

it('resolves the configured window for each invocation in a long-lived console kernel', function (): void {
    retainedNotification('forty-days', 'read_at', daysAgo: 40);
    $this->artisan('corex:notifications:prune')->expectsOutputToContain('pruned 0')->assertSuccessful();

    config(['corex-notifications.retention.settled_days' => 30]);
    app()->forgetScopedInstances();
    $this->artisan('corex:notifications:prune')->expectsOutputToContain('pruned 1')->assertSuccessful();
});

it('retries a rolled-back batch without double counting compacted records', function (): void {
    $id = retainedNotification('retry', 'read_at');
    $writes = 0;
    DB::listen(static function (QueryExecuted $query) use (&$writes): void {
        if (str_starts_with($query->sql, 'update "ntf_notifications" set "pruned_at"') && ++$writes === 1) {
            throw new PDOException('database is locked');
        }
    });
    $this->artisan('corex:notifications:prune')->expectsOutputToContain('pruned 1')->assertSuccessful();
    expect($writes)->toBe(2)
        ->and(DB::table('ntf_notifications')->where('id', $id)->whereNotNull('pruned_at')->exists())->toBeTrue()
        ->and(DB::table('ntf_deliveries')->where('notification_id', $id)->value('message'))->toBeNull();
});
