<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\EntityReference;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\NotificationSubject;
use CoreX\Notifications\Tests\PgTestCase;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('pages the retention scan by uuid under row locks on PostgreSQL', function (): void {
    app()->instance(NotificationPolicy::class, new class implements NotificationPolicy
    {
        public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool
        {
            return true;
        }
    });
    app()->forgetScopedInstances();
    $context = new NotificationContext(resolve(TenantContextResolver::class)->current(), 'user', '11111111-1111-7111-8111-111111111112');
    $ids = [];
    foreach (['settled-1', 'settled-2', 'settled-3', 'open'] as $key) {
        $ids[$key] = resolve(NotificationCenter::class)->notify($context, new NotificationInput(
            requestKey: $key,
            eventType: 'document.ready',
            recipient: new RecipientReference(kind: 'user', id: '11111111-1111-7111-8111-111111111112'),
            templateKey: 'document-ready',
            payload: JsonDocuments::capture(['message' => $key]),
            related: new EntityReference(handle: 'document', id: 'document-1'),
        ))->id;
    }
    DB::table('ntf_notifications')->update(['read_at' => now()->subDays(100)]);
    DB::table('ntf_deliveries')->where('notification_id', '!=', $ids['open'])->update(['state' => 'completed']);

    $this->artisan('corex:notifications:prune', ['--batch' => '1'])->assertSuccessful()
        ->expectsOutputToContain('pruned 3 settled notification(s)');

    expect(DB::table('ntf_notifications')->pluck('id')->all())->toBe([$ids['open']])
        ->and(DB::table('ntf_deliveries')->where('notification_id', $ids['open'])->value('state'))->toBe('pending');
});
