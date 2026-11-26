<?php

declare(strict_types=1);

use App\Scenario\FieldPgWitness;
use App\Scenario\PhysicalMarker;
use App\Scenario\Scenario;
use App\Scenario\SearchScenarioData as Owner;
use App\Scenario\SearchScenarioMeili as Meili;
use App\Scenario\SearchScenarioRebuild as Rebuild;
use App\Scenario\SearchScenarioTrace;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Errors\Exceptions\GenerationChanged;
use CoreX\Search\Errors\Exceptions\IndexNotReady;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\Rebuild\Contracts\RebuildWorker;
use CoreX\Search\Rebuild\Contracts\SearchRebuilds;
use CoreX\Search\Rebuild\Data\RebuildRef;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$database = Scenario::connection('a');
$mode = $argv[1] ?? '';
$variant = getenv('COREX_SCENARIO_VARIANT');
$trace = app(SearchScenarioTrace::class);

function changeTitle(Scenario $proof, string $id, string $title): array
{
    $result = Owner::within(static fn (DataScope $scope): array => Rebuild::change($scope, $id,
        static fn (Connection $writer) => Owner::put($writer, $id, $title, 'fixture-indexed-value', 10)), service: true);
    $proof->same('applied', $result['receipt'], 'Sealed owner change commits through BrokerReceiver and SearchInvalidations');
    $proof->same('duplicate', $result['duplicate'], 'The same broker bytes have one inbox admission');

    return $result;
}

function tombstone(Scenario $proof, string $id): array
{
    $result = Owner::within(static fn (DataScope $scope): array => Rebuild::change($scope, $id,
        static fn (Connection $writer): int => $writer->table('scenario_search_records')->where(['workspace_id' => Scenario::W1, 'id' => $id])->delete()),
        service: true);
    $proof->same('applied', $result['receipt'], 'The real subscriber records the committed deletion as a reread');
    $proof->same('duplicate', $result['duplicate'], 'Deletion redelivery does not allocate a second sequence');

    return $result;
}

function laneIdentity(object $lane): array
{
    return ['tuple' => $lane->tuple_version, 'active' => $lane->active_generation, 'writer' => $lane->writer_generation,
        'freeze' => $lane->freeze_seq, 'next' => $lane->next_seq, 'allocated' => $lane->last_allocated_seq, 'epoch' => $lane->restore_epoch];
}

function targetWire(IndexTarget $target): array
{
    return ['scope' => app(DataScopeCodec::class)->encode($target->scope), 'generation' => $target->generation,
        'backend' => $target->backend, 'cluster' => $target->cluster, 'uid' => $target->uid, 'mapping' => $target->mappingDigest,
        'allocation' => $target->allocationId];
}

function targetFrom(array $target): IndexTarget
{
    return new IndexTarget(app(DataScopeCodec::class)->decode($target['scope']), Owner::entity(), $target['backend'], $target['cluster'],
        $target['generation'], $target['uid'], $target['mapping'], $target['allocation']);
}

function cloneSearchDatabase(string $source, string $destination): array
{
    $names = [];
    $operator = Scenario::connection('control');
    foreach ([$source, $destination] as $role) {
        $name = getenv('COREX_SCENARIO_DB_'.strtoupper($role));

        if (! in_array($role, ['a', 'backup'], true) || ! is_string($name)
            || preg_match('/\Acorex_scenario_[a-f0-9]{16}_'.$role.'_test\z/D', $name) !== 1
            || $operator->selectOne('SELECT pg_catalog.pg_get_userbyid(datdba) = current_user AS owned FROM pg_database WHERE datname = ?', [$name])?->owned !== true) {
            throw new RuntimeException('The PostgreSQL snapshot is outside this scenario’s disposable owned databases.');
        }
        $names[$role] = $name;
        DB::purge('scenario_'.$role);
    }
    DB::purge('pgsql');
    $operator->unprepared('DROP DATABASE "'.$names[$destination].'"');
    $operator->unprepared('CREATE DATABASE "'.$names[$destination].'" TEMPLATE "'.$names[$source].'"');

    return $names;
}

function settleCrashedPublication(Scenario $proof, Connection $database, RebuildRef $ref, bool $committed): string
{
    $checkpoint = Scenario::barrierRead($committed ? 'publish-after-commit' : 'publish-before-commit');
    $proof->same($ref->id, $checkpoint['rebuild'], 'Reconciliation is restricted to the observed crashed publisher');
    $session = $checkpoint['session'];
    FieldPgWitness::await('the killed publisher no longer owns a PostgreSQL backend', static function () use ($database, $session): ?bool {
        $alive = $database->selectOne('SELECT EXISTS (SELECT 1 FROM pg_catalog.pg_stat_activity WHERE pid = ? AND application_name = ? AND backend_start::text = ?) AS alive',
            [$session['session'], $session['application'], $session['backend_start']]);

        return $alive?->alive === false ? true : null;
    });
    $row = Scenario::connection('control')->table('corex_scope_tickets')->where('ticket_id', $checkpoint['ticket'])->firstOrFail();
    $proof->same(AdmissionKind::LocalWrite->value, $row->kind, 'The observed publication ticket is local owner work');
    $proof->same(null, $row->provider_identity, 'The host does not isolate any uncertain provider effect');
    $outcome = $committed ? 'committed' : 'rolled-back';

    if ($row->state === 'outstanding') {
        $scope = app(DataScopeCodec::class)->decode(json_decode($row->scope, true, flags: JSON_THROW_ON_ERROR));
        $key = new OperationKey($row->operation_id, (int) $row->attempt, $row->effect_key,
            $row->deadline === null ? null : new DateTimeImmutable($row->deadline));
        app(DatabaseScopeControl::class)->settle(new AdmissionTicket($row->ticket_id, $scope, (int) $row->control_revision, $key, AdmissionKind::LocalWrite),
            new AttemptEvidence($outcome, 'search-rebuild/'.$ref->id.'/'.$outcome.'/'.$session['session']));
    }
    $proof->same($outcome, Scenario::connection('control')->table('corex_scope_tickets')->where('ticket_id', $row->ticket_id)->value('state'), 'Independent admission records the proven transaction outcome');

    return $row->ticket_id;
}

if ($mode === 'produce') {
    $database = Owner::install('generation');
    Owner::put($database, Owner::FIRST, 'before-initial-scan', 'fixture-indexed-value', 1);
    Owner::put($database, Owner::SECOND, 'delete-after-scan', 'fixture-indexed-value', 2);
    $previous = Owner::within(static function (DataScope $scope) use ($proof): array {
        $active = Rebuild::bootstrap($scope, 'initial-active');
        $target = Rebuild::target($scope, $active->destination);
        $proof->same('before-initial-scan', Meili::value(Meili::document($target, Owner::FIRST), 'title'), 'The original immutable index is ready in actual Meilisearch');
        $page = app(Search::class)->query($scope, new SearchQuery(Owner::entity(), fields: ['title'], limit: 1));
        $proof->same(Owner::FIRST, $page->hits[0]->record->id, 'The original authoritative index supplies the public query');
        $proof->check(is_string($page->cursor) && $page->cursor !== '', 'The old generation issues a real opaque continuation');

        return ['target' => targetWire($target), 'cursor' => $page->cursor];
    }, service: true);
    $old = $previous['target'];
    $event = changeTitle($proof, Owner::FIRST, 'before-scan');
    $started = Owner::within(static fn (DataScope $scope) => Rebuild::start($scope, 'new-generation'), service: true);
    $new = Owner::within(static fn (DataScope $scope): array => targetWire(Rebuild::target($scope, $started->destination)), service: true);
    $proof->same($old['generation'], $started->previous, 'The rebuild has one previous active generation');
    $proof->check($old['uid'] !== $new['uid'], 'The candidate receives a distinct immutable backend UID');
    Scenario::barrier('generation-ready', ['old' => $old, 'new' => $new, 'rebuild' => $started->ref->id, 'first_event' => $event, 'old_cursor' => $previous['cursor']]);
    $proof->report($database, [$started->ref->id, $event['id']], ['active' => $old], ['destination' => $new]);

    return;
}
$run = Scenario::barrierRead('generation-ready');
$ref = new RebuildRef($run['rebuild']);
$old = targetFrom($run['old']);
$new = targetFrom($run['new']);

switch ($mode) {
    case 'backup':
        $names = cloneSearchDatabase('a', 'backup');
        $snap = Scenario::connection('backup');
        $proof->same($old->generation, (int) Rebuild::lane($snap)->active_generation, 'The native PostgreSQL snapshot contains the previous active generation');
        $proof->same('before-scan', $snap->table('scenario_search_records')->where('id', Owner::FIRST)->value('title'), 'The backup captures the native PostgreSQL owner state');
        $proof->report($snap, array_values($names), [], ['snapshot' => laneIdentity(Rebuild::lane($snap))]);

        break;

    case 'old-task':
        Owner::within(static function (DataScope $scope) use ($old, $trace): void {
            FieldPgWitness::await('the admitted old writer returns its delayed actual task', static function () use ($scope, $old, $trace): ?bool {
                Rebuild::step($scope, $old->generation);

                return $trace->submissions === [] ? null : true;
            });
        }, service: true);
        $task = Meili::task($trace->submissions[0]['receipt']);
        $proof->same($old->uid, $task['indexUid'], 'The real delayed task still targets the immutable original UID');
        $proof->same('succeeded', $task['status'], 'Meilisearch actually applied the old task');
        Owner::within(static function (DataScope $scope) use ($old): void {
            Rebuild::step($scope, $old->generation);
        }, service: true);
        $proof->report($database, [$old->uid], [], ['task' => $task, 'submissions' => $trace->submissions]);

        break;

    case 'scan':
        $scanned = Owner::within(static fn (DataScope $scope) => Rebuild::scan($scope, Rebuild::inspect($scope, $ref->id)), service: true);
        $proof->same('catching-up', $scanned->state, 'The installed scanning worker reaches its durable catch-up phase');
        $proof->same($new->generation, $scanned->destination, 'The scan advances its one elected generation');
        $proof->report($database, [$ref->id], [], ['state' => $scanned->state, 'submissions' => $trace->submissions]);

        break;

    case 'during-scan':
        $snapshot = Scenario::barrierRead('scan-submitting');
        $proof->same($new->uid, $snapshot['uid'], 'The actual scan has captured a batch before the concurrent product changes');
        $first = changeTitle($proof, Owner::FIRST, 'during-scan');
        $third = changeTitle($proof, Owner::THIRD, 'inserted-during-scan');
        $lane = Rebuild::lane($database);
        $proof->same(3, (int) $lane->last_allocated_seq, 'The owner accepts both new dirty changes while scanning');
        $proof->same($old->generation, (int) $lane->active_generation, 'Scanning leaves the old generation authoritative');
        $proof->report($database, [$first['id'], $third['id']], ['scan' => $snapshot], ['lane' => laneIdentity($lane)]);

        break;

    case 'set-barrier':
        $removed = tombstone($proof, Owner::SECOND);
        $prior = changeTitle($proof, Owner::FIRST, 'before-h');
        $status = Owner::within(static fn (DataScope $scope) => app(RebuildWorker::class)->barrier($scope, $ref), service: true);
        $proof->same('barrier', $status->state, 'The installed worker commits the durable publish barrier');
        $proof->same(5, $status->barrier, 'H is the actual last allocated sequence including before-H writes');
        Scenario::barrier('generation-h', ['h' => $status->barrier, 'tombstone' => $removed['id'], 'latest_before' => $prior['id']]);
        $proof->report($database, [$ref->id, $prior['id'], $removed['id']], [], ['lane' => laneIdentity(Rebuild::lane($database))]);

        break;

    case 'destination':
        $h = Scenario::barrierRead('generation-h')['h'];
        $confirmed = Owner::within(static fn (DataScope $scope): object => Rebuild::confirmed($scope, $new->generation, $h), service: true);
        $proof->same($h, (int) $confirmed->confirmed_seq, 'The destination commits the confirmed contiguous boundary H');
        $proof->same('before-h', Meili::value(Meili::document($new, Owner::FIRST), 'title'), 'The destination at H reflects the last owner update within H');
        $proof->same(null, Meili::document($new, Owner::SECOND), 'The destination tombstone removes an actually indexed row');
        $proof->same('inserted-during-scan', Meili::value(Meili::document($new, Owner::THIRD), 'title'), 'The destination catches up the insertion during historical scan');
        $proof->report($database, [$ref->id], [], ['confirmed' => (int) $confirmed->confirmed_seq, 'delayed' => Scenario::barrierRead('destination-task-confirmed')]);

        break;

    case 'after-h':
        $h = Scenario::barrierRead('generation-h')['h'];
        $inflight = Scenario::barrierRead('destination-task-confirmed');
        $proof->same($new->uid, $inflight['task']['indexUid'], 'A real destination provider task reached terminal confirmation before owner ACK');
        $proof->same($h - 1, (int) Rebuild::generation($database, $new->generation)->confirmed_seq, 'The withheld task is not acknowledged in the SQL confirmed prefix');
        $post = changeTitle($proof, Owner::FIRST, 'after-h');
        Owner::within(static function (DataScope $scope) use ($proof, $ref, $old, $new): void {
            $proof->same(false, Rebuild::step($scope, $old->generation), 'The active worker cannot submit extra dirty work at the barrier');
            $proof->same(false, Rebuild::step($scope, $new->generation), 'The destination cannot advance beyond the unresolved H task');

            try {
                app(RebuildWorker::class)->publish($scope, $ref);
            } catch (LogicException $blocked) {
                $proof->same('Search publication barrier is not ready.', $blocked->getMessage(), 'Publication refuses an unconfirmed destination watermark');

                return;
            }

            throw new RuntimeException('Publication succeeded with unresolved destination H.');
        }, service: true);
        $lane = Rebuild::lane($database);
        $proof->same($h, (int) $lane->freeze_seq, 'H remains unchanged after accepting newer work');
        $proof->same($h + 1, (int) $lane->last_allocated_seq, 'The dirty sequence advances independently of the barrier');
        $proof->same($post['id'], $database->table('corex_search_dirty')->where(['lane_key' => $lane->lane_key, 'seq' => $h + 1])->value('event_id'), 'H+1 is durable with the independent inbox event ID');
        $proof->same(0, $database->table('corex_search_submissions')->where('lane_key', $lane->lane_key)->where('last_seq', '>', $h)->count(), 'Neither generation submits any work above H before publication');
        $proof->same('before-h', Meili::value(Meili::document($new, Owner::FIRST), 'title'), 'The actual destination remains at H');
        $proof->same('before-initial-scan', Meili::value(Meili::document($old, Owner::FIRST), 'title'), 'The old provider has not accepted the separately held task');
        $proof->report($database, [$post['id']], ['provider_task' => $inflight['task']], ['lane' => laneIdentity($lane), 'durable_h_plus_one' => $post['id']]);

        break;

    case 'publish-before':
    case 'publish-after':
        Owner::within(static function (DataScope $scope) use ($proof, $mode, $database, $ref, $old, $new): void {
            $writer = DB::connection();
            $identity = FieldPgWitness::markSession($writer, $mode);
            $staged = false;
            $ticket = null;
            $checkpoint = static function () use ($proof, $mode, $database, $ref, $old, $new, $writer, $identity, &$ticket): void {
                $beforeCommit = $mode === 'publish-before';
                $local = $writer->table('corex_search_lanes')->where('lane_key', Rebuild::lane($database)->lane_key)->firstOrFail();
                $live = Rebuild::lane($database);
                $proof->same($new->generation, (int) $local->active_generation, 'The genuine publish SQL has executed the new pointer');
                $proof->same($beforeCommit ? $old->generation : $new->generation, (int) $live->active_generation, 'An independent PostgreSQL session observes the correct commit visibility');
                $proof->same($beforeCommit ? 'barrier' : 'published',
                    $database->table('corex_search_rebuilds')->where('rebuild_id', $ref->id)->value('state'), 'An independent SQL observer sees the exact durable rebuild state');
                $proof->same($beforeCommit, $writer->transactionLevel() > 0, 'The owner is stopped on the specified side of the commit');
                $proof->report($writer, [$ref->id, $old->uid, $new->uid], ['old' => $old->generation], ['lane' => laneIdentity($live), 'staged_active' => (int) $local->active_generation]);
                Scenario::barrier($beforeCommit ? 'publish-before-commit' : 'publish-after-commit', ['rebuild' => $ref->id, 'lane' => laneIdentity($live), 'session' => $identity, 'ticket' => $ticket]);
            };
            Event::listen(QueryExecuted::class, static function (QueryExecuted $event) use ($writer, $mode, $checkpoint, &$staged, &$ticket): void {
                if ($event->connection === $writer && str_starts_with($event->sql, 'update "corex_search_lanes"') && str_contains($event->sql, '"active_generation" = ?')) {
                    $staged = true;
                    $ticket = Scenario::connection('control')->table('corex_scope_tickets')->where([
                        'account_id' => Scenario::BOXED, 'kind' => AdmissionKind::LocalWrite->value, 'state' => 'outstanding',
                    ])->sole()->ticket_id;

                    if ($mode === 'publish-before') {
                        $checkpoint();
                    }
                }
            });
            Event::listen(TransactionCommitted::class, static function (TransactionCommitted $event) use ($writer, $mode, $checkpoint, &$staged): void {
                if ($staged && $mode === 'publish-after' && $event->connection === $writer && $writer->transactionLevel() === 0) {
                    $staged = false;
                    $checkpoint();
                }
            });
            app(RebuildWorker::class)->publish($scope, $ref);

            throw new RuntimeException('The publisher must be killed at the designated real SQL checkpoint.');
        }, service: true);

        break;

    case 'inspect-before':
    case 'inspect-after':
        $after = $mode === 'inspect-after';
        $h = Scenario::barrierRead('generation-h')['h'];
        $status = Owner::within(static fn (DataScope $scope) => Rebuild::inspect($scope, $ref->id), service: true);
        $lane = Rebuild::lane($database);
        $proof->same($after ? 'published' : 'barrier', $status->state, 'Public inspect distinguishes rollback from durable publication after restart');
        $proof->same($ref->id, $status->ref->id, 'Restart resumes the same rebuild reference');
        $proof->same($h, $status->barrier, 'Restart does not allocate a replacement barrier');
        $proof->same($after ? $new->generation : $old->generation, (int) $lane->active_generation, 'Public inspection agrees with the durable SQL pointer');
        $proof->same($h + 1, (int) $lane->last_allocated_seq, 'Publication never lowers the durable allocation watermark');

        if ($after) {
            $proof->same($h + 1, (int) $lane->next_seq, 'The published writer resumes exactly at H+1');
            $proof->same($new->generation, (int) $lane->writer_generation, 'One commit changes both dispatch and query ownership');
        }
        $ticket = settleCrashedPublication($proof, $database, $ref, $after);
        $proof->report($database, [$ref->id, $ticket], [], ['status' => $status, 'lane' => laneIdentity($lane), 'reconciled_ticket' => $ticket]);

        break;

    case 'drain':
        $h = Scenario::barrierRead('generation-h')['h'];
        $confirmed = Owner::within(static fn (DataScope $scope): object => Rebuild::confirmed($scope, $new->generation, $h + 1), service: true);
        $proof->same($h + 1, (int) $confirmed->confirmed_seq, 'The new writer confirms the exact next contiguous sequence');
        $proof->same('after-h', Meili::value(Meili::document($new, Owner::FIRST), 'title'), 'Native Meilisearch reflects the latest authoritative owner document');
        $proof->same(null, Meili::document($new, Owner::SECOND), 'The confirmed provider keeps the tombstone absent');
        $proof->same('inserted-during-scan', Meili::value(Meili::document($new, Owner::THIRD), 'title'), 'The provider preserves the row inserted during scan');
        $lane = Rebuild::lane($database);
        $proof->same($h + 2, (int) $lane->next_seq, 'The write cursor advances only after actual provider confirmation');
        Scenario::barrier('generation-drained', ['lane' => laneIdentity($lane)]);
        $proof->report($database, [$new->uid], [], ['lane' => laneIdentity($lane), 'confirmed' => (int) $confirmed->confirmed_seq]);

        break;

    case 'verify-late':
        $before = Scenario::barrierRead('generation-drained')['lane'];
        $lane = Rebuild::lane($database);
        $proof->same($before, laneIdentity($lane), 'Completion of old work leaves the exact published lane tuple unchanged');
        $proof->same(1, $database->table('corex_search_generations')->where('lane_key', $lane->lane_key)->where('state', 'active')->count(), 'Exactly one immutable UID remains active');
        $proof->same('before-scan', Meili::value(Meili::document($old, Owner::FIRST), 'title'), 'The late actual task mutates its original UID');
        $proof->same('after-h', Meili::value(Meili::document($new, Owner::FIRST), 'title'), 'The new UID document remains unaffected by the old provider task');
        $proof->same([], Owner::blockingTickets(), 'All crash and late provider attempts are terminal before querying an obsolete cursor');

        try {
            Owner::within(static fn (DataScope $scope) => app(Search::class)->query($scope,
                new SearchQuery(Owner::entity(), fields: ['title'], limit: 1, cursor: $run['old_cursor'])), service: true);

            throw new RuntimeException('The previous generation continuation unexpectedly survived publication.');
        } catch (GenerationChanged) {
            $proof->same([], $trace->backend, 'The previous generation continuation fails before accessing the backend');
            $proof->same([], $trace->loads, 'The rejected continuation does not hydrate the SQL owner');
        }
        $blocking = Owner::blockingTickets();
        $proof->same([], $blocking, 'The read-only refusal leaves no blocking admission for the independent control owner');
        $proof->report($database, [$old->uid, $new->uid], ['lane' => $before], ['lane' => laneIdentity($lane), 'blocking_admission' => $blocking]);

        break;

    case 'restore':
        $authority = app(DatabaseScopeControl::class);
        $account = Scenario::account(Scenario::BOXED);
        $original = $authority->resolve($account);
        $closing = $authority->close($account->id, $original->controlRevision);
        $drained = $authority->drain($account->id, $closing);
        $names = cloneSearchDatabase('backup', 'a');
        $restored = Scenario::connection('a');
        $proof->same($old->generation, (int) Rebuild::lane($restored)->active_generation, 'Native restore resurrects the backup active pointer in owner SQL');
        $proof->same('before-scan', $restored->table('scenario_search_records')->where('id', Owner::FIRST)->value('title'), 'Native PostgreSQL reopened the actual backup writer contents');
        $binding = new PhysicalBinding($original->deploymentId, $original->bindingId, $original->bindingGeneration, $original->accountIncarnation,
            hash('sha256', 'fixture-generation-restore:'.$original->restoreEpoch));
        $authority->reopen($account->id, $drained, $binding, static function (PhysicalBinding $next) use ($restored, $names): bool {
            PhysicalMarker::assert($next, $restored, $names['a']);

            return true;
        });
        $proof->same($original->bindingId, $authority->resolve($account)->bindingId, 'Restore retains the physical binding ID');
        $proof->same($original->bindingGeneration, $authority->resolve($account)->bindingGeneration, 'Restore changes only the independent epoch fence');
        $proof->check($original->restoreEpoch !== $authority->resolve($account)->restoreEpoch, 'The durable external epoch advances independently of owner backup');
        $proof->same('before-scan', Meili::value(Meili::document($old, Owner::FIRST), 'title'), 'The older epoch Meilisearch object remains reachable by the operator');
        $proof->same('after-h', Meili::value(Meili::document($new, Owner::FIRST), 'title'), 'The newer old-epoch object still exists on the same actual engine');

        $fresh = Owner::within(static function (DataScope $scope) use ($proof, $trace, $old, $new): array {
            try {
                app(Search::class)->query($scope, new SearchQuery(Owner::entity(), fields: ['title']));

                throw new RuntimeException('The published pointer from the restored backup escaped the epoch fence.');
            } catch (IndexNotReady) {
                $proof->same([], $trace->backend, 'IndexNotReady occurs before querying either previous-epoch UID');
                $proof->same([], $trace->loads, 'An old generation does not hydrate owner records');
            }
            $published = Rebuild::bootstrap($scope, 'fresh-epoch');
            $target = Rebuild::target($scope, $published->destination);
            $proof->check(! in_array($target->uid, [$old->uid, $new->uid], true), 'A verified new epoch allocates a different immutable UID');
            $page = app(Search::class)->query($scope, new SearchQuery(Owner::entity(), fields: ['title'], limit: 1));
            $proof->same(['title' => 'before-scan'], $page->hits[0]->projection->values, 'The new public Search entry point reads the restored owner through the fresh generation');
            $proof->same([$target->uid], array_column($trace->backend, 'target'), 'Only the fresh index UID receives the live query');

            return targetWire($target);
        }, service: true);
        $proof->report($restored, [$old->uid, $new->uid, $fresh['uid']], ['previous_epoch' => $original->restoreEpoch], ['fresh' => $fresh]);

        break;

    case 'cancel':
        $result = Owner::within(static function (DataScope $scope) use ($proof, $database, $ref, $old, $new): array {
            Rebuild::scan($scope, Rebuild::inspect($scope, $ref->id));
            $barrier = app(RebuildWorker::class)->barrier($scope, $ref);
            $proof->same(1, $barrier->barrier, 'Cancellation is tested after electing a real frozen H');
            $initial = Rebuild::generation($database, $old->generation);
            $proof->same(0, (int) $initial->confirmed_seq, 'The original provider watermark is independently visible');
            $event = Rebuild::change($scope, Owner::FIRST,
                static fn (Connection $writer) => Owner::put($writer, Owner::FIRST, 'after-cancel-barrier', 'fixture-indexed-value', 55));
            $proof->same('applied', $event['receipt'], 'The frozen lane still admits an independent durable dirty event');
            $proof->same('duplicate', $event['duplicate'], 'Duplicate delivery does not increment the frozen lane');
            $proof->same(false, Rebuild::step($scope, $old->generation), 'The frozen original writer cannot dispatch dirty work');
            $snapshot = Rebuild::lane($database);
            $proof->same(2, (int) $snapshot->last_allocated_seq, 'H+1 is durable before cancellation');
            $cancelled = app(SearchRebuilds::class)->cancel($scope, $ref);
            $lane = Rebuild::lane($database);
            $proof->same('cancelled', $cancelled->state, 'The public cancel call records the terminal state');
            $proof->same($old->generation, (int) $lane->active_generation, 'Cancellation preserves the authoritative previous generation');
            $proof->same($old->generation, (int) $lane->writer_generation, 'Only the previous generation retains writer authority');
            $proof->same(null, $lane->freeze_seq, 'Cancellation atomically clears the freeze');
            $proof->same((int) $initial->confirmed_seq + 1, (int) $lane->next_seq, 'The previous writer resumes from its own confirmed watermark');
            $proof->same('retired', Rebuild::generation($database, $new->generation)->state, 'The unpublished destination is retired');
            $proof->same([1, 2], $database->table('corex_search_dirty')->where('lane_key', $lane->lane_key)->orderBy('seq')->pluck('seq')->map(static fn ($seq): int => (int) $seq)->all(), 'Both old and newly accumulated dirty rows are preserved');
            $confirmed = Rebuild::confirmed($scope, $old->generation, 2);
            $proof->same(2, (int) $confirmed->confirmed_seq, 'Real Meilisearch confirms the resumed contiguous prefix');
            $proof->same('after-cancel-barrier', Meili::value(Meili::document($old, Owner::FIRST), 'title'), 'The resumed old writer sends the authoritative current value');
            $proof->same('before-scan', Meili::value(Meili::document($new, Owner::FIRST), 'title'), 'The retired destination receives no H+1 submission');

            return ['before_cancel' => laneIdentity($snapshot), 'after_cancel' => laneIdentity($lane), 'confirmed' => (int) $confirmed->confirmed_seq];
        }, service: true);
        $proof->report($database, [$ref->id, $old->uid, $new->uid], [], $result);

        break;

    default:
        throw new RuntimeException('Unknown installed generation action.');
}
