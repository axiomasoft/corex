<?php

declare(strict_types=1);

use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use App\Scenario\ScenarioQueue;
use App\Scenario\ScenarioSetup;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Recurrence\Data\DateStart;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupIdentity;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Scheduling\Data\WakeupWindow;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$database = Scenario::connection('a');
$clock = resolve(ScenarioClock::class);
$mode = $argv[1] ?? '';

function inWorkspaceTwo(Closure $callback): mixed
{
    return ScenarioQueue::inScope(Scenario::BOXED, Scenario::W2, Scenario::USER_B, static fn (): mixed => $callback(resolve(DataScopeFactory::class)->forCurrentPrincipal(resolve(TenantContextResolver::class)->current())));
}

function sweepFromWorkspaceOne(Scenario $proof): void
{
    ScenarioQueue::inScope(Scenario::BOXED, Scenario::W1, Scenario::USER_A, static function () use ($proof): void {
        $proof->same(Scenario::W1, resolve(TenantContextResolver::class)->current()->workspace?->id, 'The sweep starts as W1');
        $proof->same(0, Artisan::call('corex:scheduler:sweep', ['--limit' => 1]), 'Run the installed Laravel Scheduler command');
        $proof->same(Scenario::W1, resolve(TenantContextResolver::class)->current()->workspace?->id, 'Sweep restores its ambient workspace');
        $proof->same(Scenario::USER_A, auth()->guard()->user()?->getAuthIdentifier(), 'Sweep restores its own ambient actor');
    });
}

function row(string $key): array
{
    $row = Scenario::connection('a')->table('sys_wakeup_requests')->where('request_key', $key)->first();

    if ($row === null) {
        throw new RuntimeException('Missing durable Scheduler request '.$key);
    }

    return (array) $row;
}

function wakeupRequest(DataScope $scope, string $kind, string $version, string $key, DateTimeImmutable $fireAt, array $payload, ?WakeupWindow $window = null): WakeupRequest
{
    return new WakeupRequest($scope, $kind, $version, $key, $fireAt, $window, JsonDocuments::capture($payload));
}

switch ($mode) {
    case 'produce':
        $ran = ScenarioSetup::install('workspace-sweep', false);
        $proof->check($ran['a'] !== [], 'The installed Packman schema sets are active');
        $set = RecurrenceSet::create('installed-series', 'revision-1', new DateStart('DATE', '2026-11-20', null), 'FREQ=DAILY;COUNT=1');
        $database->table('corex_task_series')->insert([
            'id' => (string) Str::uuid7(), 'series_key' => $set->seriesId, 'immutable_revision' => $set->immutableRevision,
            'definition_hash' => $set->definitionHash, 'definition' => json_encode($set, JSON_THROW_ON_ERROR),
            'template' => json_encode(['workspaceId' => Scenario::W2, 'title' => 'Installed W2 materialization', 'authorId' => Scenario::USER_B], JSON_THROW_ON_ERROR),
            'actor' => json_encode(['type' => 'user', 'id' => Scenario::USER_B], JSON_THROW_ON_ERROR),
            'context_hash' => hash('sha256', Scenario::W2), 'restore_epoch' => str_repeat('a', 64),
        ]);
        $database->table('corex_task_series_state')->insert([
            'series_key' => $set->seriesId, 'active_revision' => $set->immutableRevision, 'restore_epoch' => str_repeat('a', 64),
        ]);
        $database->table('scenario_field_grants')->insert([
            'principal_id' => Scenario::USER_B, 'workspace_id' => Scenario::W2, 'entity' => 'task-series',
            'record_id' => $set->seriesId, 'field_id' => 'record', 'field_use' => 'write',
        ]);
        $ref = inWorkspaceTwo(static function (DataScope $scope) use ($clock, $proof, $set): string {
            $now = $clock->now();
            $data = [
                'seriesId' => $set->seriesId, 'immutableRevision' => $set->immutableRevision, 'definitionHash' => $set->definitionHash,
                'start' => '2026-11-20', 'end' => '2026-11-21', 'aheadDays' => 1, 'pageLimit' => 10, 'maxSeconds' => 5,
            ];
            $window = new WakeupWindow($now->modify('-1 minute'), $now, 'custom');
            $wakeup = wakeupRequest($scope, 'task-recurrence.materialize', 'task-materialization/1', 'materialize-w2', $now, $data, $window);
            $scheduler = resolve(WakeupScheduler::class);
            $created = $scheduler->schedule($wakeup);
            $proof->same('created', $created->status, 'Schedule W2 through the installed Scheduler');
            $replay = $scheduler->schedule($wakeup);
            $proof->same('existing', $replay->status, 'Identical scope, window and payload replay the stored request');
            $proof->same($created->id, $replay->id, 'Exact replay preserves the original request ID');
            $changed = new WakeupWindow($now->modify('-2 minutes'), $now, 'custom');
            $proof->same('conflict', $scheduler->schedule(wakeupRequest($scope, $wakeup->kind, $wakeup->handlerVersion, $wakeup->requestKey, $now, $data, $changed))->status, 'A different window on the same key conflicts');
            $proof->same('created', $scheduler->schedule(wakeupRequest($scope, 'scenario.cas', 'cas/1', 'cancelled', $now, ['fixture' => true]))->status, 'Prepare cancellable request');
            $proof->same('cancelled', $scheduler->cancel($scope, new WakeupIdentity('scenario.cas', 'cancelled'))->status, 'Cancel through the public Scheduler');
            $proof->same('created', $scheduler->schedule(wakeupRequest($scope, 'scenario.retired', 'retired/1', 'retired', $now->modify('+30 seconds'), ['fixture' => true]))->status, 'The first deployment accepts the legacy handler version.');

            return $created->id ?? throw new RuntimeException('Missing durable W2 request ID.');
        });
        $wire = json_decode(row('materialize-w2')['data_scope'], true, flags: JSON_THROW_ON_ERROR);
        $proof->same(3, $wire['version'], 'Durable Scheduler scope codec version');
        $proof->same(Scenario::W2, $wire['workspace_id'], 'Producer writes W2, not a future sweeper workspace');
        $proof->same(Scenario::USER_B, $wire['principal_id'], 'Producer writes its own actor');
        $proof->report($database, [$ref], [], ['scope' => $wire, 'migrations' => $ran]);

        break;

    case 'first':
        Event::listen(TransactionCommitted::class, static function (TransactionCommitted $event) use ($proof, $database): void {
            if ($event->connection !== DB::connection() || $event->connection->transactionLevel() !== 0) {
                return;
            }
            $running = row('materialize-w2');

            if ($running['status'] !== 'running') {
                return;
            }
            $proof->same(0, $database->table('tsk_tasks')->count(), 'The committed claim precedes every task write');
            $proof->same(1, $running['attempt'], 'The first durable claim has attempt one');
            $proof->check(is_string($running['claim_token']) && $running['claim_token'] !== '', 'The attempt stores a concrete SQL claim token');
            $state = Scenario::describe($event->connection);
            $proof->report($database, [$running['id'], $running['claim_token']], ['workspace' => Scenario::W1], ['claim' => $running, 'actual_claim_session' => $state]);
            Scenario::barrier('workspace-claim', ['claim' => $running, 'session' => $state]);

            throw new RuntimeException('The runner must terminate this worker at the durable claim.');
        });
        sweepFromWorkspaceOne($proof);

        throw new RuntimeException('The first worker did not stop after committed claim.');
    case 'resume':
        $old = Scenario::barrierRead('workspace-claim');
        $actual = row('materialize-w2');
        $proof->same($old['claim']['claim_token'], $actual['claim_token'], 'A fresh process observes the abandoned committed claim');
        $proof->same(0, $database->table('tsk_tasks')->count(), 'Killed worker made no task');
        $clock->advance(5);
        $proof->check($clock->now() > new DateTimeImmutable($actual['lease_until']), 'The real scheduler clock passes the abandoned lease');
        inWorkspaceTwo(static function (DataScope $scope) use ($proof): void {
            $proof->same(Scenario::W2, $scope->workspaceId, 'A separate process initially opens W2 after the abandoned claim');
            sweepFromWorkspaceOne($proof);
            $proof->same(Scenario::W2, resolve(TenantContextResolver::class)->current()->workspace?->id, 'The replacement sweep exits back to W2');
            $proof->same(Scenario::USER_B, auth()->guard()->user()?->getAuthIdentifier(), 'The replacement sweep restores the previous W2 actor');
        });
        $task = $database->table('tsk_tasks')->first();
        $proof->same(1, $database->table('corex_task_occurrences')->count(), 'Exactly one real recurrence occurrence is materialized. Request state '.json_encode(row('materialize-w2'), JSON_THROW_ON_ERROR));
        $proof->same(1, $database->table('tsk_tasks')->where('workspace_id', Scenario::W2)->count(), 'W2 owns the materialized task');
        $proof->same(0, $database->table('tsk_tasks')->where('workspace_id', Scenario::W1)->count(), 'No materialization enters W1');
        $proof->same(Scenario::USER_B, json_decode($task->created_by, true, flags: JSON_THROW_ON_ERROR)['id'], 'Task stores the original W2 actor');
        $completed = row('materialize-w2');
        $proof->same('completed', $completed['status'], 'Recovered wakeup completes durably');
        $proof->same(2, $completed['attempt'], 'Recovery uses a separate claim attempt');
        $proof->same(null, $completed['claim_token'], 'Completed wakeup releases its claim');
        $proof->same('cancelled', row('cancelled')['status'], 'Cancelled work stays terminal');
        $continuations = $database->table('sys_wakeup_requests')->where('request_key', 'like', 'materialize:%')->get();
        $proof->same(1, count($continuations), 'The materializer writes its continuation through the real Scheduler');
        inWorkspaceTwo(static function (DataScope $scope) use ($proof, $continuations): void {
            foreach ($continuations as $continuation) {
                $stored = json_decode($continuation->data_scope, true, flags: JSON_THROW_ON_ERROR);
                $proof->same($scope->workspaceId, $stored['workspace_id'], 'Continuation retains W2');
                $proof->same($scope->principalId, $stored['principal_id'], 'Continuation retains the authorizing actor');
                $identity = new WakeupIdentity($continuation->kind, $continuation->request_key);
                $proof->same('cancelled', resolve(WakeupScheduler::class)->cancel($scope, $identity)->status, 'Cancel future continuation before testing the removed handler');
            }
        });
        $proof->report($database, [$completed['id'], $task->id], ['old' => $old], ['claim' => $completed, 'task' => (array) $task, 'continuations' => $continuations]);

        break;

    case 'retire':
        $clock->advance(60);
        $before = row('retired');
        sweepFromWorkspaceOne($proof);
        $after = row('retired');
        $proof->same('quarantined', $after['status'], 'Removed handler is terminally quarantined');
        $proof->same('handler-unavailable', $after['failure_code'], 'Removed handler supplies its explicit quarantine reason');
        $proof->same(0, $database->table('scenario_effects')->count(), 'Retired and cancelled handles never execute');
        $proof->report($database, [$after['id']], $before, $after);

        break;

    case 'late':
        $clock->advance(120);
        $created = inWorkspaceTwo(static fn (DataScope $scope) => resolve(WakeupScheduler::class)->schedule(
            wakeupRequest($scope, 'scenario.cas', 'cas/1', 'expired-cas', $clock->now(), ['fixture' => true]),
        ));
        $proof->same('created', $created->status, 'Prepare a real late-CAS request in W2');
        sweepFromWorkspaceOne($proof);
        $row = row('expired-cas');
        $proof->same('running', $row['status'], 'Expired completion cannot advance the first claim');
        $proof->same(1, $row['attempt'], 'No in-place reassignment of the expired worker token');
        $proof->check($clock->now() > new DateTimeImmutable($row['lease_until']), 'The lease elapsed before completion CAS');
        $proof->same(0, $database->table('scenario_effects')->count(), 'Expired local CAS rolls back the transactional handler effect');
        $proof->report($database, [$row['id'], $row['claim_token']], ['status' => 'pending'], $row);

        break;

    case 'observe-late':
        $peer = Scenario::barrierRead('before-expired-cas');
        $proof->check(Scenario::describe($database)['session'] !== $peer['connection']['session'], 'The observer uses an independent PostgreSQL session');
        $proof->check($peer['transaction_level'] > 0, 'The blocked handler writes within its real owner transaction');
        $proof->same(Scenario::W2, $peer['workspace'], 'The resumed handler owns W2');
        $proof->same(Scenario::USER_B, $peer['principal'], 'The resumed handler has W2’s producer principal');
        $proof->same(0, $database->table('scenario_effects')->count(), 'The local effect is invisible until completion commits');
        $row = row('expired-cas');
        $proof->report($database, [$row['id']], ['peer' => $peer], ['visible_effects' => 0]);

        break;

    case 'reclaim':
        $clock->advance(132);
        $before = row('expired-cas');
        sweepFromWorkspaceOne($proof);
        $after = row('expired-cas');
        $effects = $database->table('scenario_effects')->pluck('marker', 'reference')->all();
        $proof->same(['expired-cas' => Scenario::USER_B.'|'.Scenario::W2], $effects, 'One committed effect follows a new claim');
        $proof->same('completed', $after['status'], 'Fresh attempt finishes the same request');
        $proof->same(2, $after['attempt'], 'Expired token cannot serve as the fresh attempt');
        $proof->report($database, [$after['id']], $before, ['claim' => $after, 'effects' => $effects]);

        break;

    default:
        throw new RuntimeException('Unknown workspace sweep action.');
}
