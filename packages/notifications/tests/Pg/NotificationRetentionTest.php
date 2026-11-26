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

it('uses the scoped retention index and skips a concurrently locked notification until the next run', function (): void {
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

    DB::statement(<<<'SQL'
        insert into ntf_notifications (id, account_id, account_incarnation, workspace_scope, recipient_kind, recipient_id,
            source, event_id, request_digest, event_type, template_action, payload, revision, created_at, updated_at)
        select gen_random_uuid(), account_id, account_incarnation, workspace_scope, recipient_kind, recipient_id,
            source, 'unread-' || n, request_digest, event_type, template_action, payload, revision, created_at, updated_at
        from ntf_notifications cross join generate_series(1, 8000) n where id = ?
        SQL, [$ids['open']]);
    DB::statement('analyze ntf_notifications');
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'from "ntf_notifications"') && str_contains($query->sql, 'order by "read_at"')) {
            $queries[] = [$query->sql, $query->bindings];
        }
    });

    config(['database.connections.retention_peer' => config('database.connections.pgsql')]);
    $peer = DB::connection('retention_peer');
    $peer->beginTransaction();
    DB::statement("SET lock_timeout = '500ms'");

    try {
        $peer->table('ntf_notifications')->where('id', $ids['settled-1'])->lockForUpdate()->firstOrFail();
        $this->artisan('corex:notifications:prune', ['--batch' => '1'])->expectsOutputToContain('pruned 2')->assertSuccessful();
        expect(DB::table('ntf_notifications')->where('id', $ids['settled-1'])->value('pruned_at'))->toBeNull();
    } finally {
        $peer->rollBack();
        DB::statement('RESET lock_timeout');
    }
    $this->artisan('corex:notifications:prune', ['--batch' => '1'])->expectsOutputToContain('pruned 1')->assertSuccessful();
    expect(DB::table('ntf_notifications')->whereNotNull('pruned_at')->count())->toBe(3)
        ->and(DB::table('ntf_deliveries')->where('notification_id', $ids['open'])->value('state'))->toBe('pending');

    $plan = DB::scalar('explain (format json) '.$queries[0][0], $queries[0][1]);
    expect($plan)->toContain('"Index Name": "ntf_notifications_retention_ix"')
        ->and($plan)->not->toContain('"Node Type": "Sort"');
});
