<?php

declare(strict_types=1);

use App\Scenario\PhysicalMarker;
use App\Scenario\Scenario;
use App\Scenario\ScenarioJob;
use App\Scenario\ScenarioQueue;
use App\Scenario\ScenarioSetup;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$control = Scenario::connection('control');
$owner = app(DatabaseScopeControl::class);
$account = Scenario::account(Scenario::ACCOUNT_A);
$mode = $argv[1] ?? '';

function enqueueWakeup(string $reference): array
{
    return ScenarioQueue::inScope(Scenario::ACCOUNT_A, Scenario::W1, Scenario::USER_A, static function () use ($reference): array {
        $scope = app(DataScopeFactory::class)->forCurrentPrincipal(app(TenantContextResolver::class)->current());
        $stored = app(WakeupScheduler::class)->schedule(new WakeupRequest(
            $scope, 'scenario.wakeup', 'scenario-wakeup/1', $reference,
            new DateTimeImmutable('2000-01-01T00:00:00Z'), null, JsonDocuments::capture(['reference' => $reference]),
        ));

        if ($stored->status !== 'created') {
            throw new RuntimeException('The installed Scheduler did not persist the wakeup.');
        }
        $id = ScenarioQueue::push(new ScenarioJob($reference, 'sweep'));

        return [
            'job' => $id, 'wakeup' => $stored->id, 'binding' => $scope->bindingId,
            'generation' => $scope->bindingGeneration, 'epoch' => $scope->restoreEpoch,
        ];
    });
}

function effectState(): array
{
    $result = ['driver_resolutions' => Scenario::connection('control')->table('scenario_search_resolutions')->pluck('target')->all()];
    foreach (['a', 'b'] as $role) {
        $target = Scenario::connection($role);
        $result[$role] = [
            'identity' => Scenario::describe($target),
            'effects' => $target->table('scenario_effects')->orderBy('reference')->pluck('marker', 'reference')->all(),
            'ddl' => $target->getSchemaBuilder()->hasTable('scenario_wakeup_publication'),
            'records' => $target->table('scenario_records')->value('marker'),
        ];
    }

    return $result;
}

function observeNoEffects(Scenario $proof, array $state): void
{
    $proof->same([], $state['driver_resolutions'], 'Stale work resolves no Search driver');
    foreach (['a', 'b'] as $role) {
        $proof->same([], $state[$role]['effects'], 'No handler effect reaches PostgreSQL target '.$role);
        $proof->same(false, $state[$role]['ddl'], 'No DDL reaches PostgreSQL target '.$role);
    }
}

function cloneDatabase(string $source, string $destination): array
{
    $names = [];
    foreach ([$source, $destination] as $role) {
        $name = getenv('COREX_SCENARIO_DB_'.strtoupper($role));

        if (! is_string($name) || preg_match('/\Acorex_scenario_[a-f0-9]{16}_(b|backup)_test\z/D', $name) !== 1) {
            throw new RuntimeException('Database snapshot must stay inside the isolated case targets.');
        }
        $names[$role] = $name;
        DB::purge('scenario_'.$role);
    }
    $operator = DB::connection('scenario_control');
    foreach ($names as $database) {
        $row = $operator->selectOne('SELECT pg_catalog.pg_get_userbyid(datdba) = current_user AS owned FROM pg_database WHERE datname = ?', [$database]);

        if ($row?->owned !== true) {
            throw new RuntimeException('The database snapshot role does not own the target.');
        }
    }
    $operator->unprepared('DROP DATABASE "'.$names[$destination].'"');
    $operator->unprepared('CREATE DATABASE "'.$names[$destination].'" TEMPLATE "'.$names[$source].'"');

    return $names;
}

function verifyB(PhysicalBinding $binding): bool
{
    $db = Scenario::connection('b');
    PhysicalMarker::assert($binding, $db, (string) getenv('COREX_SCENARIO_DB_B'));

    return $db->getSchemaBuilder()->hasTable('sys_wakeup_requests') && $db->getSchemaBuilder()->hasTable('scenario_records');
}

function expectBlocked(Scenario $proof, Closure $transition): void
{
    try {
        $transition();

        throw new RuntimeException('The control owner completed cutover with unresolved work.');
    } catch (ScopeContextMismatch $failure) {
        $proof->check(str_contains($failure->getMessage(), 'Outstanding'), 'A durable admission obligation blocks cutover');
    }
}

switch ($mode) {
    case 'produce':
        $migrations = ScenarioSetup::install('binding-restore', true);
        $old = enqueueWakeup('old-route');
        $proof->same(['binding-a', 1, str_repeat('a', 64)], [$old['binding'], $old['generation'], $old['epoch']], 'Producer captures A before route change');
        $proof->same(1, $control->table('jobs')->count(), 'Laravel database broker has one durable enqueue');
        $proof->check($migrations['a'] !== [] && $migrations['b'] !== [], 'Both tenants used real Packman schema sets');
        $proof->report($control, $old, [], ['queued' => $old, 'migrations' => $migrations]);

        break;

    case 'old-route':
    case 'old-epoch':
        $prior = effectState();
        observeNoEffects($proof, $prior);
        // The independent controller advances the route before the worker selects any tenant.
        DB::purge('scenario_a');
        DB::purge('scenario_b');
        $first = $control->table('jobs')->oldest('id')->first();
        $payload = json_decode($first->payload, true, flags: JSON_THROW_ON_ERROR);
        Scenario::barrier('before-'.$mode, ['job' => $payload['uuid'], 'scope' => $payload['corex_scope']['scope'], 'control' => Scenario::describe($control)]);
        ScenarioQueue::next();
        $failure = $control->table('failed_jobs')->where('uuid', $payload['uuid'])->first();
        $proof->check($failure !== null && str_contains($failure->exception, 'stale physical binding'), 'The original signed envelope is durably quarantined by the broker');
        $proof->same(false, $control->table('jobs')->where('id', $first->id)->exists(), 'Stale envelope is removed from the live queue');
        $after = effectState();
        observeNoEffects($proof, $after);
        $proof->report($control, [$payload['uuid']], $prior, [...$after, 'quarantine' => $failure->uuid]);

        break;

    case 'rebind':
        $old = $owner->resolve($account);
        $next = Scenario::binding('binding-b', 2, 'a', Scenario::ACCOUNT_A);
        $closed = $owner->drain($account->id, $owner->close($account->id, $old->controlRevision));
        $control->table('root_accounts')->where('id', $account->id)->update(['db_name' => getenv('COREX_SCENARIO_DB_B')]);
        $owner->reopen($account->id, $closed, $next, verifyB(...));
        $queued = enqueueWakeup('old-epoch');
        $proof->same(['binding-b', 2, str_repeat('a', 64)], [$queued['binding'], $queued['generation'], $queued['epoch']], 'New producer uses trusted B route');
        $backup = cloneDatabase('b', 'backup');
        Scenario::connection('b')->table('scenario_records')->update(['marker' => 'post-backup change']);
        $proof->same('equipment b', Scenario::connection('backup')->table('scenario_records')->value('marker'), 'Backup is an independent PostgreSQL snapshot');
        $proof->report($control, $queued, ['binding' => $old], ['binding' => $next, 'backup' => $backup]);

        break;

    case 'restore':
        $old = $owner->resolve($account);
        $closed = $owner->drain($account->id, $owner->close($account->id, $old->controlRevision));
        $proof->same('post-backup change', Scenario::connection('b')->table('scenario_records')->value('marker'), 'The live data has changed since the backup');
        $restored = cloneDatabase('backup', 'b');
        $next = Scenario::binding($old->bindingId, $old->bindingGeneration, 'b', Scenario::ACCOUNT_A);
        $owner->reopen($account->id, $closed, $next, verifyB(...));
        $actual = $owner->resolve($account);
        $proof->same(['binding-b', 2, str_repeat('b', 64)], [$actual->bindingId, $actual->bindingGeneration, $actual->restoreEpoch], 'The external control store changes epoch independently of the restored binding');
        $proof->same('equipment b', Scenario::connection('b')->table('scenario_records')->value('marker'), 'The target really contains the stale PostgreSQL snapshot');
        $proof->report($control, array_values($restored), ['binding' => $old], ['binding' => $actual, 'restored' => $restored]);

        break;

    case 'provider':
        $admission = app(ScopeAdmission::class);
        $ticket = ScenarioQueue::inScope(Scenario::ACCOUNT_A, Scenario::W1, Scenario::USER_A, static function () use ($admission) {
            $scope = app(DataScopeFactory::class)->forCurrentPrincipal(app(TenantContextResolver::class)->current());

            return $admission->acquire($scope, new OperationKey('provider-in-flight', 1, 'provider-effect-key', new DateTimeImmutable('now +1 hour')), AdmissionKind::ProviderSend);
        });
        $providerSession = Scenario::describe(Scenario::connection('b'));
        Scenario::barrier('provider-outstanding', ['ticket' => $ticket->id, 'control' => Scenario::describe($control), 'provider' => $providerSession]);
        $admission->settle($ticket, new AttemptEvidence('unknown', 'provider reply unconfirmed'));
        Scenario::barrier('provider-unknown', ['ticket' => $ticket->id]);
        DB::purge('scenario_b');
        $sessionExists = $control->selectOne('SELECT EXISTS (SELECT 1 FROM pg_stat_activity WHERE pid = ? AND datname = ?) AS live', [
            $providerSession['session'], $providerSession['database'],
        ])->live;
        $proof->same(false, $sessionExists, 'The external owner witnesses that the admitted SQL provider session has ended');
        $admission->settle($ticket, new AttemptEvidence('isolated', 'confirmed PostgreSQL provider session exit: '.$providerSession['session']));
        $proof->same('isolated', $control->table('corex_scope_tickets')->where('ticket_id', $ticket->id)->value('state'), 'Durable owner records explicit isolation evidence');
        $proof->report($control, [$ticket->id], ['state' => 'outstanding', 'session' => $providerSession], ['state' => 'isolated', 'session_live' => $sessionExists]);

        break;

    case 'close-provider':
        $peer = Scenario::barrierRead('provider-outstanding');
        $proof->check($peer['control']['session'] !== Scenario::describe($control)['session'], 'Provider and controller use distinct PostgreSQL sessions');
        $old = $owner->resolve($account);
        $closing = $owner->close($account->id, $old->controlRevision);
        expectBlocked($proof, static fn () => $owner->drain($account->id, $closing));
        Scenario::barrier('provider-closing', ['revision' => $closing]);
        $proof->same('unknown', $control->table('corex_scope_tickets')->where('ticket_id', $peer['ticket'])->value('state'), 'Uncertain outcome remains durable');
        expectBlocked($proof, static fn () => $owner->drain($account->id, $closing));
        Scenario::barrier('provider-still-blocked', ['revision' => $closing]);
        $closed = $owner->drain($account->id, $closing);
        $owner->reopen($account->id, $closed, Scenario::binding('binding-b', 2, 'c', Scenario::ACCOUNT_A), verifyB(...));
        $proof->same('open', $owner->resolve($account)->mode, 'Verified reopen follows provider isolation');
        $proof->report($control, [$peer['ticket']], ['binding' => $old], ['binding' => $owner->resolve($account)]);

        break;

    case 'fresh':
        $before = effectState();
        observeNoEffects($proof, $before);
        ScenarioQueue::inScope(Scenario::ACCOUNT_A, Scenario::W1, Scenario::USER_A, static function () use ($proof): void {
            $proof->same(0, Artisan::call('corex:scheduler:sweep', ['--limit' => 1]), 'Run the real Scheduler against the restored stale row');
            $proof->same('quarantined', DB::table('sys_wakeup_requests')->where('request_key', 'old-epoch')->value('status'), 'The restored persisted wakeup is quarantined');
        });
        $afterOld = effectState();
        observeNoEffects($proof, $afterOld);
        $fresh = enqueueWakeup('fresh-after-reopen');
        $proof->same(['binding-b', 2, str_repeat('c', 64)], [$fresh['binding'], $fresh['generation'], $fresh['epoch']], 'Fresh work obtains the reopened fence');
        ScenarioQueue::next();
        $after = effectState();
        $proof->same([], $after['a']['effects'], 'A remains untouched after the verified rebind');
        $proof->same(['fresh-after-reopen' => 'equipment b'], $after['b']['effects'], 'Only the authenticated fresh request runs on B');
        $proof->same(true, $after['b']['ddl'], 'Fresh work reaches its declared DDL');
        $proof->same([getenv('COREX_SCENARIO_DB_B')], $after['driver_resolutions'], 'Exactly one real Search driver opens on the new target');
        $proof->same(2, $control->table('failed_jobs')->count(), 'Both obsolete broker attempts remain quarantined');
        $proof->report($control, $fresh, $before, $after);

        break;

    default:
        throw new RuntimeException('Unknown binding-restore action.');
}
