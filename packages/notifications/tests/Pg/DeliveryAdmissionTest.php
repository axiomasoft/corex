<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Contracts\DeliveryExecution;
use CoreX\Notifications\Delivery\Contracts\NotificationChannelDriver;
use CoreX\Notifications\Delivery\Data\ChannelDescriptor;
use CoreX\Notifications\Delivery\Data\DeliveryInput;
use CoreX\Notifications\Delivery\Data\DeliveryOutcome;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Delivery\Enums\DeliveryOutcomeKind;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\EntityReference;
use CoreX\Notifications\Inbox\Data\InboxQuery;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\NotificationRef;
use CoreX\Notifications\Inbox\Data\NotificationSubject;
use CoreX\Notifications\Inbox\Exceptions\NotificationAccessDenied;
use CoreX\Notifications\Runtime\Internal\DeliveryStore;
use CoreX\Notifications\Tests\PgTestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('holds a PostgreSQL delivery row lock across independent connections', function (): void {
    $id = '11111111-1111-7111-8111-111111111111';
    $notification = '22222222-2222-7222-8222-222222222222';
    DB::table('ntf_notifications')->insert([
        'account_id' => 'account-1',
        'account_incarnation' => 'incarnation-1',
        'workspace_scope' => 'account',
        'source' => 'pg-fixture',
        'id' => $notification,
        'recipient_kind' => 'user',
        'recipient_id' => 'recipient-1',
        'event_id' => 'pg-delivery-lock',
        'request_digest' => hash('sha256', 'pg-delivery-lock'),
        'event_type' => 'document.ready',
        'template_action' => 'document-ready',
        'payload' => json_encode([], JSON_THROW_ON_ERROR),
        'revision' => '1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('ntf_deliveries')->insert([
        'account_id' => 'account-1',
        'account_incarnation' => 'incarnation-1',
        'workspace_scope' => 'account',
        'deployment_id' => 'deployment-1',
        'binding_id' => 'binding-1',
        'binding_generation' => 1,
        'restore_epoch' => str_repeat('a', 64),
        'scope' => '{}',
        'scope_seal' => str_repeat('a', 64),
        'id' => $id,
        'notification_id' => $notification,
        'channel' => 'in_app',
        'delivery_key' => hash('sha256', 'pg-delivery-lock:in_app'),
        'state' => 'pending',
        'claim_epoch' => '0',
        'attempts' => 0,
        'admitted_revision' => '1',
    ]);

    $connection = config('database.connections.pgsql');
    $dsn = "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}";
    $second = new PDO($dsn, $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    DB::beginTransaction();
    DB::table('ntf_deliveries')->where('id', $id)->lockForUpdate()->first();
    $second->exec("SET lock_timeout = '100ms'");

    try {
        expect(fn (): bool => $second->query("SELECT id FROM ntf_deliveries WHERE id = '{$id}' FOR UPDATE") !== false)->toThrow(PDOException::class);
    } finally {
        DB::rollBack();
    }
});

it('uses related record visibility for reading and a delivery admitted before access was revoked', function (): void {
    $policy = new class implements NotificationPolicy
    {
        public bool $relatedVisible = true;

        public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool
        {
            return $subject->related === null || $this->relatedVisible || $subject->notificationId === null;
        }
    };
    $driver = new class implements NotificationChannelDriver
    {
        public int $sends = 0;

        public function descriptor(): ChannelDescriptor
        {
            return new ChannelDescriptor('audit-fixture', '1', false, false, null, 'notifications-test');
        }

        public function send(DeliveryInput $input): DeliveryOutcome
        {
            $this->sends++;

            return new DeliveryOutcome(DeliveryOutcomeKind::Completed, 'receipt', null, 'confirmed');
        }

        public function reconcile(DeliveryInput $input): DeliveryOutcome
        {
            return new DeliveryOutcome(DeliveryOutcomeKind::Unknown, null, null, 'unknown');
        }
    };
    app()->instance(NotificationPolicy::class, $policy);
    app()->instance('notification-test-driver', $driver);
    app()->tag('notification-test-driver', 'corex.notifications.channels');
    $context = admissionNotificationContext();
    $input = new NotificationInput(
        'event-1', 'document.ready', new RecipientReference('user', $context->actorId ?? ''), 'document-ready',
        JsonDocuments::capture(['status' => 'ready']), new EntityReference('documents.document', '1'),
        channels: ['audit-fixture'], source: 'test.documents',
    );
    $center = resolve(NotificationCenter::class);
    $reference = $center->notify($context, $input);
    expect($center->notify($context, $input)->id)->toBe($reference->id)
        ->and(DB::table('ntf_deliveries')->count())->toBe(1)
        ->and($center->unreadCount($context)->count)->toBe(1);

    $policy->relatedVisible = false;
    expect($center->inbox($context, new InboxQuery(null))->items)->toBe([])
        ->and($center->unreadCount($context)->count)->toBe(0);
    expect(fn () => $center->markRead($context, $reference->id))->toThrow(NotificationAccessDenied::class);

    $id = DB::table('ntf_deliveries')->where('notification_id', $reference->id)->value('id');
    resolve(DeliveryExecution::class)->deliver($id);
    expect(DB::table('ntf_deliveries')->where('id', $id)->value('state'))->toBe('skipped')
        ->and($driver->sends)->toBe(0);
});

it('selects runnable work behind terminal history and reports an incomplete unread scan', function (): void {
    $policy = new class implements NotificationPolicy
    {
        public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool
        {
            return $action !== NotificationAction::Read || $subject->related === null;
        }
    };
    app()->instance(NotificationPolicy::class, $policy);
    $context = admissionNotificationContext();
    $center = resolve(NotificationCenter::class);
    $recipient = new RecipientReference('user', $context->actorId ?? '');
    foreach (range(1, 1000) as $index) {
        $history = $center->notify($context, new NotificationInput(
            "terminal-{$index}", 'document.ready', $recipient, 'document-ready',
            JsonDocuments::capture(['status' => 'ready']), new EntityReference('documents.document', (string) $index),
        ));
        DB::table('ntf_deliveries')->where('notification_id', $history->id)->update([
            'state' => 'completed', 'next_attempt' => now()->subDay(),
        ]);
    }
    $visible = $center->notify($context, new NotificationInput(
        'visible', 'document.ready', $recipient, 'document-ready', JsonDocuments::capture(['status' => 'ready']), null,
    ));
    $id = DB::table('ntf_deliveries')->where('notification_id', $visible->id)->value('id');

    expect(resolve(DeliveryStore::class)->dueIds(1))->toBe([$id])
        ->and($center->unreadCount($context)->count)->toBeNull()
        ->and($center->unreadCount($context)->reason)->toBe('visibility-scan-limit');
    $page = $center->inbox($context, new InboxQuery(null, 1));
    expect(array_map(static fn (NotificationRef $ref): string => $ref->id, $page->items))->toBe([$visible->id])
        ->and($page->cursor)->not->toBeNull();
    expect(fn () => $center->inbox($context, new InboxQuery('x'.$page->cursor, 1)))->toThrow(InvalidArgumentException::class);
    expect($center->inbox($context, new InboxQuery($page->cursor, 1))->items)->toBe([]);

    CallerWorkScopeFixture::bind($this->app, CallerWorkScopeFixture::forContext(
        $context->tenant, '11111111-1111-7111-8111-111111111112', str_repeat('b', 64),
    ));
    $this->app->forgetScopedInstances();
    expect(fn () => resolve(NotificationCenter::class)->inbox($context, new InboxQuery($page->cursor, 1)))
        ->toThrow(InvalidArgumentException::class);
});

function admissionNotificationContext(): NotificationContext
{
    return new NotificationContext(
        resolve(TenantContextResolver::class)->current(), 'user', '11111111-1111-7111-8111-111111111112',
    );
}

it('reads an inbox page from the recipient id index instead of sorting the whole history', function (): void {
    app()->instance(NotificationPolicy::class, new class implements NotificationPolicy
    {
        public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool
        {
            return true;
        }
    });
    $context = admissionNotificationContext();
    $center = resolve(NotificationCenter::class);
    $first = $center->notify($context, new NotificationInput(
        'history-0', 'document.ready', new RecipientReference('user', $context->actorId ?? ''), 'document-ready', JsonDocuments::capture(['status' => 'ready']), null,
    ));
    // A long read history for this recipient and for others: without an index ordered by id the
    // page query sorts every notification the recipient ever received.
    DB::statement(<<<'SQL'
        insert into ntf_notifications (id, account_id, account_incarnation, workspace_scope, recipient_kind, recipient_id, source, event_id,
            request_digest, event_type, template_action, payload, read_at, revision, created_at, updated_at)
        select gen_random_uuid(), account_id, account_incarnation, workspace_scope, recipient_kind,
            case when g % 4 = 0 then recipient_id else 'other-'||(g % 50) end, source, 'history-'||g,
            request_digest, event_type, template_action, payload, now(), revision, created_at, updated_at
        from ntf_notifications, generate_series(1, 8000) g where id = ?
        SQL, [$first->id]);
    DB::statement('analyze ntf_notifications');
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'ntf_notifications') && str_contains($query->sql, 'order by "id" desc')) {
            $queries[] = [$query->sql, $query->bindings];
        }
    });

    expect($center->inbox($context, new InboxQuery(null, 20))->items)->toHaveCount(20)
        ->and($queries)->toHaveCount(1);
    $plan = (string) DB::scalar('explain (format json) '.$queries[0][0], $queries[0][1]);
    expect($plan)->toContain('"Index Name": "ntf_notifications_inbox_ix"')
        ->and($plan)->not->toContain('"Node Type": "Sort"');
});
