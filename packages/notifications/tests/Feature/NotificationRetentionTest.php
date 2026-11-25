<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\EntityReference;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\NotificationSubject;
use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Notifications\Tests\TestCase;
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
    $id = resolve(NotificationCenter::class)->notify(retentionContext(), new NotificationInput(
        requestKey: $key,
        eventType: 'document.ready',
        recipient: new RecipientReference(kind: 'user', id: '11111111-1111-7111-8111-111111111112'),
        templateKey: 'document-ready',
        payload: JsonDocuments::capture(['message' => $key]),
        related: new EntityReference(handle: 'document', id: 'document-1'),
    ))->id;
    DB::table('ntf_deliveries')->where('notification_id', $id)->update(['state' => $deliveryState]);

    if ($settled !== null) {
        DB::table('ntf_notifications')->where('id', $id)->update([$settled => now()->subDays($daysAgo ?? 0)]);
    }

    return $id;
}

/** @return list<string> */
function remainingNotifications(): array
{
    return DB::table('ntf_notifications')->orderBy('event_id')->pluck('event_id')->all();
}

it('prunes notifications settled before the window together with their final deliveries', function (): void {
    foreach (['read_at', 'dismissed_at', 'cancelled_at'] as $column) {
        retainedNotification("old-{$column}", $column);
    }
    retainedNotification('old-unread', null);
    retainedNotification('recent-read', 'read_at', daysAgo: 30);
    foreach (['accepted', 'permanently-failed', 'exhausted', 'skipped', 'cancelled'] as $state) {
        retainedNotification("old-{$state}", 'read_at', $state);
    }

    $this->artisan('corex:notifications:prune')->assertSuccessful()
        ->expectsOutputToContain('pruned 8 settled notification(s) older than 90 day(s)');

    expect(remainingNotifications())->toBe(['old-unread', 'recent-read'])
        ->and(DB::table('ntf_deliveries')->count())->toBe(2);
});

it('keeps a settled notification while a delivery is unfinished', function (string $deliveryState): void {
    $id = retainedNotification('open', 'read_at', $deliveryState);

    $this->artisan('corex:notifications:prune')->assertSuccessful();

    expect(DB::table('ntf_notifications')->where('id', $id)->exists())->toBeTrue()
        ->and(DB::table('ntf_deliveries')->where('notification_id', $id)->value('state'))->toBe($deliveryState);
})->with(['pending', 'claimed', 'sending', 'retry-scheduled', 'unknown', 'quarantined']);

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
        ->expectsOutputToContain('pruned 5 settled notification(s) older than 30 day(s)');
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
