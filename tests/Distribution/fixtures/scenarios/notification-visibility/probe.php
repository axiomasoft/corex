<?php

declare(strict_types=1);

use App\Scenario\FixtureNotificationDriver;
use App\Scenario\NotificationRelated;
use App\Scenario\ProviderSimulator;
use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use App\Scenario\ScenarioQueue;
use App\Scenario\ScenarioSetup;
use CoreX\Access\Access\Laravel\PrincipalIdentity;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Roles\DirectGrants;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Enums\PrincipalType;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Contracts\DeliveryExecution;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Data\EntityReference;
use CoreX\Notifications\Inbox\Data\InboxQuery;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\NotificationRef;
use CoreX\Notifications\Inbox\Exceptions\NotificationAccessDenied;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$database = Scenario::connection('a');
$mode = $argv[1] ?? '';

function identity(): PrincipalIdentity
{
    return new PrincipalIdentity(PrincipalType::User, Scenario::USER_A);
}

function withRecipient(Closure $work): mixed
{
    return ScenarioQueue::inScope(Scenario::BOXED, Scenario::W1, Scenario::USER_A, static function () use ($work): mixed {
        $context = new NotificationContext(app(TenantContextResolver::class)->current(), 'user', Scenario::USER_A);

        return $work(app(NotificationCenter::class), $context);
    });
}

function input(string $key, bool $related = false): NotificationInput
{
    return new NotificationInput(
        requestKey: $key,
        eventType: 'fixture.record-changed',
        recipient: new RecipientReference('user', Scenario::USER_A),
        templateKey: 'fixture.record-notification',
        payload: JsonDocuments::capture(['key' => $key]),
        related: $related ? new EntityReference(NotificationRelated::HANDLE, Scenario::RECORD) : null,
        channels: [FixtureNotificationDriver::CHANNEL],
    );
}

function delivery(Connection $database, string $notification): object
{
    return $database->table('ntf_deliveries')->where('notification_id', $notification)->firstOrFail();
}

function visibleRelated(): bool
{
    return app(AccessChecker::class)->canOnRecord(identity(), 'read', NotificationRelated::query()->findOrFail(Scenario::RECORD));
}

switch ($mode) {
    case 'produce':
        ScenarioSetup::install('notification-visibility', false);
        $refs = withRecipient(static function (NotificationCenter $center, NotificationContext $context) use ($proof): array {
            app(ModuleLifecycle::class)->enable('axiomasoft/corex-notifications', cascade: true);
            app(DirectGrants::class)->grant(identity(), NotificationRelated::HANDLE, 'read');
            $proof->same(true, visibleRelated(), 'The installed Access checker sees the original related grant');
            $hidden = $center->notify($context, input('one-fact', true));
            $duplicate = $center->notify($context, input('one-fact', true));
            $proof->same($hidden->id, $duplicate->id, 'Repeated input for one immutable fact returns the same notification');
            $visible = $center->notify($context, input('unrelated-visible'));

            return ['hidden' => $hidden->id, 'visible' => $visible->id];
        });
        $proof->same(2, $database->table('ntf_notifications')->count(), 'Two different facts result in exactly two notifications');
        $proof->same(1, $database->table('ntf_deliveries')->where('notification_id', $refs['hidden'])->count(), 'One fact and channel has one delivery');
        $proof->same('pending', delivery($database, $refs['hidden'])->state, 'The first producer committed its durable delivery');
        $proof->same([], ProviderSimulator::evidence('notifications')['requests'], 'The production entry has not made a provider call');
        Scenario::barrier('notification-committed', $refs);
        $proof->report($database, array_values($refs), [], $refs);

        break;

    case 'revoke':
        $refs = Scenario::barrierRead('notification-committed');
        $proof->same('pending', delivery($database, $refs['hidden'])->state, 'An independent session observes committed work before revocation');
        withRecipient(static function () use ($proof): void {
            app(DirectGrants::class)->revoke(identity(), NotificationRelated::HANDLE, 'read');
            $proof->same(false, visibleRelated(), 'The actual Access grant has been revoked for the related record');
        });
        $proof->report($database, [$refs['hidden']], ['related_visible' => true], ['related_visible' => false]);

        break;

    case 'observe':
        $refs = Scenario::barrierRead('notification-committed');
        $hidden = delivery($database, $refs['hidden']);
        $visible = delivery($database, $refs['visible']);
        withRecipient(static function (NotificationCenter $center, NotificationContext $context) use ($proof, $refs, $hidden, $visible): void {
            $proof->same(false, visibleRelated(), 'A fresh process sees the committed revocation');
            $page = $center->inbox($context, new InboxQuery(null, 5));
            $proof->same([$refs['visible']], array_map(static fn (NotificationRef $ref): string => $ref->id, $page->items), 'The inbox contains only the visible notification');
            $proof->same(1, $center->unreadCount($context)->count, 'The bounded complete unread count excludes revoked related data');

            try {
                $center->markRead($context, $refs['hidden']);
            } catch (NotificationAccessDenied) {
                // Either an indistinguishable no-op or the package's safe denial leaves no read receipt.
            }
            $center->markRead($context, $refs['visible']);
            $proof->same(0, $center->unreadCount($context)->count, 'A visible markRead changes the exact count');
            $execution = app(DeliveryExecution::class);
            $execution->deliver($hidden->id);
            $execution->deliver($visible->id);
        });
        $proof->same('skipped', delivery($database, $refs['hidden'])->state, 'Revoked related data is skipped by installed delivery');
        $proof->same('completed', delivery($database, $refs['visible'])->state, 'A visible delivery completes via the installed owner');
        $revoked = ProviderSimulator::evidence('notifications', $hidden->delivery_key);
        $proof->same([], $revoked['requests'], 'The revoked delivery makes zero provider requests');
        $proof->same([], $revoked['effects'], 'The revoked delivery creates no external effect');
        $positive = ProviderSimulator::evidence('notifications', $visible->delivery_key);
        $proof->same(1, count($positive['requests']), 'The positive control makes one transport request');
        $proof->same(1, count($positive['effects']), 'The positive control commits one independent provider effect');
        $read = $database->table('ntf_notifications')->whereNotNull('read_at')->pluck('id')->all();
        $proof->same([$refs['visible']], $read, 'Only the visible notification has a read receipt');
        $proof->report($database, [$hidden->id, $visible->id], ['related' => 'revoked'], [
            'hidden_state' => delivery($database, $refs['hidden'])->state, 'read_notification_ids' => $read,
            'hidden_journal' => $revoked, 'visible_journal' => $positive,
        ]);

        break;

    case 'history':
        $due = withRecipient(static function (NotificationCenter $center, NotificationContext $context) use ($proof): NotificationRef {
            // The producer seals the historical rows normally. Fixture SQL gives them inert outcomes and early due times.
            app(DirectGrants::class)->grant(identity(), NotificationRelated::HANDLE, 'read');
            DB::connection()->transaction(static function () use ($center, $context): void {
                for ($index = 0; $index < 1001; $index++) {
                    $center->notify($context, input('historical-'.$index, true));
                }
                DB::table('ntf_deliveries')->where('state', 'pending')->update([
                    'state' => 'completed', 'next_attempt' => app(ScenarioClock::class)->now()->modify('-1 hour'),
                ]);
            });
            app(DirectGrants::class)->revoke(identity(), NotificationRelated::HANDLE, 'read');
            $proof->same(false, visibleRelated(), 'All related history is hidden by the installed access policy');

            return $center->notify($context, input('next-due'));
        });
        $queue = $database->table('ntf_deliveries');
        $row = delivery($database, $due->id);
        $proof->same(1001, (clone $queue)->where('state', 'completed')->where('next_attempt', '<', $row->next_attempt)->count(), '1,001 terminal deliveries sort before the live due row');
        $proof->same([$row->id], (clone $queue)->where('state', 'pending')->pluck('id')->all(), 'The owner has exactly one runnable due row');
        Scenario::barrier('terminal-history', ['due' => $row->id, 'key' => $row->delivery_key, 'visible' => $due->id]);
        $proof->report($database, [$row->id], [], ['terminal' => 1001, 'pending' => 1]);

        break;

    case 'bounded':
        $history = Scenario::barrierRead('terminal-history');
        $visibility = withRecipient(static function (NotificationCenter $center, NotificationContext $context) use ($proof, $history): array {
            $count = $center->unreadCount($context);
            $proof->same(null, $count->count, 'An incomplete visibility scan yields no exact count');
            $proof->check(is_string($count->reason) && $count->reason !== '', 'An incomplete count returns a safe reason');
            $page = $center->inbox($context, new InboxQuery(null, 5));
            $ids = static fn (array $items): array => array_map(static fn (NotificationRef $ref): string => $ref->id, $items);
            $proof->same([$history['visible']], $ids($page->items), 'The bounded initial inbox includes the recent visible row and omits hidden records');
            $proof->check($page->cursor !== null, 'The bounded page has a continuation for remaining rows');
            $next = $center->inbox($context, new InboxQuery($page->cursor, 5));
            $proof->same([Scenario::barrierRead('notification-committed')['visible']], $ids($next->items), 'The cursor advances over examined hidden records to an older visible record');
            $proof->same(null, $next->cursor, 'The second page exhausts the remaining records');
            $proof->same(0, Artisan::call('corex:notifications:sweep', ['--limit' => 1]), 'The installed bounded owner sweep succeeds');

            return ['exact_count' => $count->count, 'reason' => $count->reason, 'visible_pages' => [$ids($page->items), $ids($next->items)]];
        });
        $proof->same('completed', $database->table('ntf_deliveries')->where('id', $history['due'])->value('state'), 'The due selector skips terminal history before its limit');
        $proof->same(1003, $database->table('ntf_deliveries')->where('state', 'completed')->count(), 'Terminal history remains intact after the sweep');
        $provider = ProviderSimulator::evidence('notifications');
        $proof->same(2, count($provider['requests']), 'The visible positive control and one due row were the only actual sends');
        $proof->same($history['key'], $provider['requests'][1]['effect_key'], 'The bounded sweep sends the one identified pending key');
        $proof->same(2, count($provider['effects']), 'The external journal has exactly the two permitted effects');
        $proof->report($database, [$history['due']], ['terminal' => 1001], [
            'visibility' => $visibility, 'selected_delivery' => $history['due'], 'provider' => $provider,
        ]);

        break;

    default:
        throw new RuntimeException('Unknown notification visibility action.');
}
