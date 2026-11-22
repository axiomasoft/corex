<?php

declare(strict_types=1);

use App\Scenario\Scenario;
use App\Scenario\ScenarioJob;
use App\Scenario\ScenarioJobFailure;
use App\Scenario\ScenarioJobLedger;
use App\Scenario\ScenarioQueue;
use App\Scenario\ScenarioSetup;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\ScheduleResult;
use CoreX\Scheduling\Data\WakeupRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$cloud = getenv('COREX_SCENARIO_VARIANT') === 'cloud';
$control = Scenario::connection('control');
$controlWitness = Scenario::describe($control);

if (($argv[1] ?? null) === 'produce') {
    $ran = ScenarioSetup::install('worker-scope', $cloud);
    $proof->check($ran['a'] !== [], 'The installed Packman schema sets must be applied');
    $refs = [];

    if ($cloud) {
        $proof->check($ran['control'] !== [] && $ran['b'] !== [], 'The root and second tenant schema must be applied');
        foreach ([
            ['outer-a', 'outer', Scenario::ACCOUNT_A, Scenario::W1, Scenario::USER_A],
            ['failed-b', 'failing-b', Scenario::ACCOUNT_B, Scenario::W2, Scenario::USER_B],
            ['last-a', 'query', Scenario::ACCOUNT_A, Scenario::W1, Scenario::USER_A],
        ] as [$ref, $operation, $account, $workspace, $actor]) {
            $refs[$ref] = ScenarioQueue::inScope($account, $workspace, $actor, static fn (): string => ScenarioQueue::push(new ScenarioJob($ref, $operation)));
        }
        foreach ($control->table('jobs')->get(['id', 'payload']) as $job) {
            $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
            $production = $payload['corex_scope'] ?? [];
            $proof->same(3, $production['scope']['version'] ?? null, 'The normal Laravel producer must write v3 work metadata');
            $proof->same(64, strlen($production['signature'] ?? ''), 'The producer stamps a signed scope');
        }
        $proof->same(3, $control->table('jobs')->count(), 'Three durable Laravel jobs must be accepted');
    }
    Scenario::barrier('produced', ['control' => $controlWitness, 'refs' => $refs, 'migrations' => $ran]);
    $proof->report($control, $refs, ['jobs' => count($refs)], ['migrations' => $ran]);

    return;
}
$producer = Scenario::barrierRead('produced');
$proof->check($producer['control']['session'] !== $controlWitness['session'], 'The worker must use a different physical PostgreSQL session');
$default = app('db')->getDefaultConnection();

if (! $cloud) {
    $before = auth()->guard()->user();
    $seen = ScenarioQueue::inScope(Scenario::BOXED, null, Scenario::USER_A, static function () use ($proof): array {
        $scope = app(DataScopeFactory::class)->forCurrentPrincipal(app(TenantContextResolver::class)->current());
        $runner = app(WorkScopeRunner::class);
        $outer = $runner->run($scope, static function ($context) use ($proof, $runner, $scope): array {
            $proof->check($context->isBoxed(), 'Core must admit this fixed account in boxed mode');
            $proof->same(Scenario::BOXED, $context->id(), 'Boxed account identity');
            $first = Scenario::describe(DB::connection());

            try {
                $runner->run($scope, static function (): never {
                    throw new ScenarioJobFailure('Planted nested boxed exception.');
                });

                throw new RuntimeException('The boxed exception was swallowed.');
            } catch (ScenarioJobFailure) {
                $proof->same($first, Scenario::describe(DB::connection()), 'Boxed outer session after nested failure');
                $proof->same(Scenario::USER_A, auth()->guard()->user()?->getAuthIdentifier(), 'Boxed outer principal after nested failure');
            }

            return $first;
        });
        $codec = app(DataScopeCodec::class);
        $stale = $codec->decode([...$codec->encode($scope), 'restore_epoch' => str_repeat('b', 64)]);

        try {
            $runner->run($stale, static fn (): never => throw new RuntimeException('Stale boxed work reached its handler.'));

            throw new RuntimeException('Stale boxed work was accepted.');
        } catch (ScopeStale) {
            $proof->check(true, 'Stale boxed epoch denied');
        }

        return $outer;
    });
    $proof->same($before, auth()->guard()->user(), 'Boxed host principal restored');
    $proof->same($default, app('db')->getDefaultConnection(), 'Boxed default connection restored');
    Scenario::barrier('worked', ['owner' => $seen]);
    $proof->report($control, ['binding-a'], ['default' => $default], ['owner' => $seen, 'principal' => null]);

    return;
}

$ledger = app(ScenarioJobLedger::class);
$states = [];
for ($attempt = 0; $attempt < 3; $attempt++) {
    ScenarioQueue::next();
    $states[] = [
        'account' => app(TenancyManager::class)->context()?->id(),
        'principal' => auth()->guard()->user()?->getAuthIdentifier(),
        'connection' => app('db')->getDefaultConnection(),
    ];
}
$proof->same(array_fill(0, 3, ['account' => null, 'principal' => null, 'connection' => $default]), $states, 'Each Laravel worker attempt must restore central, DB and auth state');
$proof->same(0, $control->table('jobs')->count(), 'All durable jobs must leave the live queue');
$proof->same(1, $control->table('failed_jobs')->count(), 'Only the planted standalone B exception fails a job');
$proof->check(str_contains((string) $control->table('failed_jobs')->value('exception'), 'Intentional exception after resolving the B Search driver'), 'The process reaches B Search before failing');
$byRef = array_column($ledger->queries, null, 'reference');
$proof->same(['outer-a', 'nested-b', 'failed-b', 'last-a'], array_keys($byRef), 'The worker performs A, nested B, B, A searches in one PHP process');
$proof->same(['equipment a', 'equipment b', 'equipment a'], array_map(static fn (string $ref): string => $byRef[$ref]['marker'], ['outer-a', 'failed-b', 'last-a']), 'Equal record IDs cannot cause account marker leakage');
foreach ($byRef as $entry) {
    $isB = $entry['account'] === Scenario::ACCOUNT_B;
    $proof->same(Scenario::RECORD, $entry['record'], 'The same primary key was queried for each account');
    $proof->same($isB ? Scenario::W2 : Scenario::W1, $entry['workspace'], 'The real worker enters the persisted workspace');
    $proof->same($isB ? Scenario::USER_B : Scenario::USER_A, $entry['principal'], 'The real worker reinstalls the persisted live principal');
    $proof->same(getenv($isB ? 'COREX_SCENARIO_DB_B' : 'COREX_SCENARIO_DB_A'), $entry['database']['database'], 'Search executes on the verified PostgreSQL target');
    $proof->same('public', $entry['database']['namespace'], 'Search executes on the expected PostgreSQL schema');
    $proof->same($isB ? [['tenant_b_label' => 'visible-b']] : [['tenant_a_label' => 'visible-a']], $entry['records'], 'Field-use policy controls the real result projection');
    $proof->same(false, $entry['hidden_allowed'], 'The other account field is denied');
}
$proof->check($byRef['outer-a']['database']['session'] !== $byRef['nested-b']['database']['session'], 'Nested B uses a separate physical PostgreSQL session');
$proof->check(in_array('retained-driver-denied', $ledger->failures, true) && in_array('nested-error-observed', $ledger->failures, true), 'A retained driver is denied and nested SyncJob throws');
$proof->check(in_array('discarded-b-driver-denied', $ledger->failures, true), 'A retained B driver cannot execute after the exception restores A');
$last = end($ledger->failures);
$proof->same(Scenario::ACCOUNT_A, $last['outer_account'], 'Nested failure restores A');
$proof->same(Scenario::W1, $last['outer_workspace'], 'Nested failure restores W1');
$proof->same(Scenario::USER_A, $last['outer_principal'], 'Nested failure restores the authenticated A principal');
$proof->same(getenv('COREX_SCENARIO_DB_A'), $last['outer_db']['database'], 'Nested failure restores the physical A database');

$wakeup = ScenarioQueue::inScope(Scenario::ACCOUNT_A, Scenario::W1, Scenario::USER_A, static function (): ScheduleResult {
    $scope = app(DataScopeFactory::class)->forCurrentPrincipal(app(TenantContextResolver::class)->current());

    return app(WakeupScheduler::class)->schedule(new WakeupRequest(
        scope: $scope, kind: 'scenario.root-sweep', handlerVersion: 'root-sweep/1', requestKey: 'root-cli-wakeup',
        fireAt: new DateTimeImmutable('2000-01-01T00:00:00Z'), window: null, payload: JsonDocuments::capture(['reference' => 'root-cli-wakeup']),
    ));
});
$proof->same('created', $wakeup->status, 'The installed Scheduler accepts the W1 wakeup from the live user');
$hints = DB::connection('pgsql')->table('root_wakeup_hints');
$proof->same([Scenario::ACCOUNT_A], $hints->pluck('account_id')->all(), 'The installed root routing adapter records the producer hint');
$proof->same(1, (clone $hints)->where('account_id', Scenario::ACCOUNT_A)->delete(), 'The disposable root database loses only the advisory hint');
$accountA = Scenario::connection('a');
$accountB = Scenario::connection('b');
$command = 'corex:tenancy:wakeup-root-sweep';
$proof->same(0, Artisan::call($command, ['--reconcile' => true, '--limit' => 2]), 'The installed root reconcile command succeeds');
$reconcile = trim(Artisan::output());
$proof->check(str_contains($reconcile, 'Reconciled 2 account wakeup hints.'), 'Reconciliation visits both account databases under the host service grant');
$proof->same([Scenario::ACCOUNT_A], $hints->pluck('account_id')->all(), 'Reconciliation rebuilds the hint only for A');
$proof->same(0, $accountA->table('scenario_effects')->count(), 'Reconciliation does not deliver wakeups');
$proof->same(0, Artisan::call($command, ['--limit' => 1]), 'The installed root sweep command succeeds');
$sweep = trim(Artisan::output());
$proof->same('Swept 1 account hints.', $sweep, 'The root command claims one hint');
$proof->same('completed', $accountA->table('sys_wakeup_requests')->where('id', $wakeup->id)->value('status'), 'The actual tenant Scheduler completes the scoped request');
$effects = $accountA->table('scenario_effects')->pluck('marker', 'reference')->all();
$proof->same(['root-cli-wakeup' => 'user|'.Scenario::USER_A.'|'.Scenario::W1.'|'.getenv('COREX_SCENARIO_DB_A')], $effects, 'Delivery restores the persisted user, workspace and physical account');
$proof->same(0, $accountB->table('scenario_effects')->count(), 'The other database receives no delivery');
$proof->same(0, $hints->count(), 'A completed owner has no remaining advisory hint');
$admitted = $control->table('corex_scope_tickets')->where('account_id', Scenario::ACCOUNT_A)
    ->whereRaw("scope->>'principal_kind' = ?", ['service'])
    ->whereRaw("scope->>'principal_id' = ?", ['corex.scheduler-sweep'])
    ->where('state', 'committed')->pluck('ticket_id')->all();
$proof->check($admitted !== [], 'The independent control store records the named root service admission');
$proof->same(null, app(TenancyManager::class)->context(), 'The CLI restores the host tenancy frame');
$proof->same(null, auth()->guard()->user(), 'The CLI leaves no user installed in the host');
$proof->same($default, app('db')->getDefaultConnection(), 'The CLI restores the host connection');
$rootSweep = ['reconcile' => $reconcile, 'sweep' => $sweep, 'wakeup' => $wakeup->id, 'service_admissions' => $admitted, 'effects' => $effects];
Scenario::barrier('worked', ['queries' => $byRef, 'root_sweep' => $rootSweep]);
$proof->report($control, $producer['refs'], ['default' => $default], ['states' => $states, 'queries' => $byRef, 'nested' => $ledger->failures, 'root_sweep' => $rootSweep]);
