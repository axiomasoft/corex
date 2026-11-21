<?php

declare(strict_types=1);

use App\Scenario\FieldPgWitness;
use App\Scenario\PhysicalMarker;
use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use App\Scenario\ScenarioQueue;
use App\Scenario\ScenarioSetup;
use App\TaskLifetime\TaskLifetimeActivationGate;
use App\TaskLifetime\TaskLifetimeOuterGate;
use App\TaskLifetime\TaskLifetimeSeries;
use App\TaskLifetime\TaskLifetimeWorkflowEngine;
use Composer\InstalledVersions;
use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Task\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Contracts\MoveTaskToStageCommand;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\MoveTaskToStageData;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\Tasks\Task\Enums\TaskStatus;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Enums\CaptureStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Lifecycle\ModuleActivationGate;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Contracts\Registry\RegistryIntrospection;
use Packman\Modules\Enums\Lifecycle\ModuleState;
use Symfony\Component\Process\Process;

require getcwd().'/vendor/autoload.php';

const TASKS = 'axiomasoft/corex-tasks';
const WORKFLOW = 'axiomasoft/corex-workflow';
const BRIDGE = 'axiomasoft/corex-tasks-workflow';
const RECURRING = 'axiomasoft/corex-recurring-tasks';
const PROJECT = 'a0000000-0000-7000-8000-000000000001';
const IN_PROGRESS = 'a0000000-0000-7000-8000-000000000002';
const DONE = 'a0000000-0000-7000-8000-000000000003';
const DEFINITION = 'fixture:workflow';
const VERSION = 'fixture:version1';

if (($argv[1] ?? '') === 'duplicate-at-bootstrap') {
    try {
        Scenario::boot();
    } catch (LogicException $error) {
        echo json_encode(['pid' => getmypid(), 'error' => $error::class, 'message' => $error->getMessage()], JSON_THROW_ON_ERROR).PHP_EOL;
        exit(41);
    }

    exit(0);
}

if (($argv[1] ?? '') === 'diagnostics' && ($argv[3] ?? '') === 'outer') {
    putenv('COREX_SCENARIO_TASKS_OUTER_GATE=1');
}

if (($argv[1] ?? '') === 'diagnostics' && ($argv[4] ?? '') === 'gate-first') {
    putenv('COREX_SCENARIO_TASKS_GATE_FIRST=1');
}

if (($argv[1] ?? '') === 'registration') {
    putenv('COREX_SCENARIO_TASKS_REGISTRATION_ORDER='.$argv[2]);
    putenv('COREX_SCENARIO_TASKS_REGISTRATION_HOST='.$argv[4]);
    putenv('COREX_SCENARIO_TASKS_REGISTRATION_DENY='.(int) (($argv[5] ?? '') === 'deny'));

    if (($argv[6] ?? '') === 'outer') {
        putenv('COREX_SCENARIO_TASKS_OUTER_GATE=1');
    }
}

if (in_array(($argv[1] ?? ''), ['booted-activation', 'booted-reader'], true)) {
    putenv('COREX_SCENARIO_TASKS_REGISTRATION_ORDER=reader');
    putenv('COREX_SCENARIO_TASKS_REGISTRATION_HOST=instance');
    $application = require getcwd().'/bootstrap/app.php';
    $proof = new Scenario;
    $observe = $argv[1] === 'booted-activation' ? observeBootedActivation(...) : observeBootedReader(...);
    $early = null;
    $application->booted(static function () use (&$early, $observe, $proof): void {
        $early = $observe($proof);
    });
    $application->make(Kernel::class)->bootstrap();
    $proof->check(is_array($early), 'The listener queued at application construction completed');
    $after = $observe($proof);
    $proof->same($after, $early, 'Application listeners and post-bootstrap calls observe the same complete policy');
    $proof->report(Scenario::connection('a'), [TASKS], $early, $after);

    exit(0);
}

Scenario::boot();
$proof = new Scenario;
$database = Scenario::connection('a');
$variant = getenv('COREX_SCENARIO_VARIANT');

function actor(): ActorData
{
    return new ActorData(ActorType::User, Scenario::USER_A);
}

function enterTaskScope(Closure $work, ?string $workspace = Scenario::W1): mixed
{
    return ScenarioQueue::inScope(Scenario::BOXED, $workspace, Scenario::USER_A, static function () use ($work): mixed {
        $context = app(TenantContextResolver::class)->current();
        $scope = app(DataScopeFactory::class)->forCurrentPrincipal($context);

        return app(WorkScopeRunner::class)->run($scope, static fn (TenantContext $trusted): mixed => $work($scope, $trusted));
    });
}

function observeBootedActivation(Scenario $proof): array
{
    $proof->same(true, app()->isBooted(), 'Every provider has completed boot');
    $database = Scenario::connection('a');
    $tables = ['tsk_tasks', 'corex_task_operations', 'sys_workflow_intents', 'mod_modules', 'mod_lifecycle_log'];
    $counts = static fn (): array => array_map(static fn (string $table): int => $database->table($table)->count(), array_combine($tables, $tables));
    $before = $counts();
    $proof->same(array_fill_keys($tables, 0), $before, 'The lifecycle test starts with five empty tables');
    $outcome = enterTaskScope(static function (DataScope $scope, TenantContext $trusted) use ($proof): array {
        $lifecycle = app(ModuleLifecycle::class);
        $proof->same(ModuleState::Available, $lifecycle->status(TASKS)->state, 'Tasks starts available');
        $reason = null;

        try {
            $lifecycle->enable(TASKS, cascade: true);
        } catch (AuthorizationException $refusal) {
            $reason = $refusal->getMessage();
        }

        $proof->same('The pinned task workflow definition and version have no active engine.', $reason, 'The first public activation checks the unavailable pin');
        $lookups = app(TaskLifetimeWorkflowEngine::class)->lookups;
        $proof->same(expectedLookup(Scenario::W1, 'fixture:missing-version'), $lookups, 'Public lifecycle consults the exact missing pin once');
        $proof->same(false, app(ModuleRegistry::class)->isActive(TASKS, $trusted), 'The refused module remains inactive');

        return ['reason' => $reason, 'lookups' => $lookups];
    });
    $after = $counts();
    $proof->same($before, $after, 'The refused activation leaves all five tables unchanged');

    return ['booted' => true, 'counts_before' => $before, 'counts_after' => $after, ...$outcome];
}

function observeBootedReader(Scenario $proof): array
{
    $proof->same(true, app()->isBooted(), 'Every provider has completed boot');
    $held = app('task-lifetime.registration-reader');

    return enterTaskScope(static function (DataScope $scope, TenantContext $trusted) use ($proof, $held): array {
        $engine = app(TaskLifetimeWorkflowEngine::class);
        $rows = [];

        foreach (['held-first', 'held-again', 'fresh'] as $label) {
            $reader = $label === 'fresh' ? app(RegistryIntrospection::class) : $held;
            $offset = count($engine->lookups);
            $status = $reader->why(TASKS, $trusted)->status;
            $lookups = array_slice($engine->lookups, $offset);
            $proof->same('active', $status, 'The reader sees the enabled module in W1');
            $proof->same(expectedLookup(Scenario::W1), $lookups, 'Each reader operation checks the final pin once');
            $rows[$label] = ['status' => $status, 'lookups' => $lookups];
        }

        foreach (['gate-first', 'gate-again'] as $label) {
            $offset = count($engine->lookups);
            $allowed = app(ModuleActivationGate::class)->check($trusted, TASKS, 'standard')->allowed;
            $lookups = array_slice($engine->lookups, $offset);
            $proof->same(true, $allowed, 'The current gate permits the supported pin');
            $proof->same(expectedLookup(Scenario::W1), $lookups, 'Each independent gate call checks the pin once');
            $rows[$label] = ['allowed' => $allowed, 'lookups' => $lookups];
        }

        return $rows;
    });
}

function complete(string $task): TaskReference
{
    return app(CompleteTaskCommand::class)->handle(new CompleteTaskData($task, actor()));
}

function move(string $task, string $stage, ?string $workspace = Scenario::W1): void
{
    enterTaskScope(static fn (): TaskReference => app(MoveTaskToStageCommand::class)->handle(new MoveTaskToStageData($task, $stage, actor())), $workspace);
}

function taskId(string $title): string
{
    return Scenario::connection('a')->table('tsk_tasks')->where('title', $title)->value('id')
        ?? throw new RuntimeException('The fixture task does not exist.');
}

function snapshot(Connection $database, string $task, bool $workflow = true): array
{
    return [
        'task' => (array) $database->table('tsk_tasks')->where('id', $task)
            ->select('id', 'status', 'stage_id', 'completed_at')->selectRaw('xmin::text AS row_transaction')->first(),
        'receipts' => $database->table('corex_task_operations')->where('kind', 'completionCapture')
            ->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
        'intents' => $workflow ? $database->table('sys_workflow_intents')->orderBy('id')->get()
            ->map(static fn (object $row): array => (array) $row)->all() : [],
    ];
}

function assertSingleCompletion(Scenario $proof, string $task, array $state): void
{
    $proof->same('done', $state['task']['status'], 'The owner committed Done');
    $proof->check($state['task']['completed_at'] !== null, 'The completion has a persisted timestamp');
    $proof->same(1, count($state['receipts']), 'Exactly one Tasks lifetime receipt committed');
    $proof->same(1, count($state['intents']), 'Exactly one Workflow start intent committed');
    $receipt = $state['receipts'][0];
    $intent = $state['intents'][0];
    $result = json_decode($receipt['result_ref'], true, flags: JSON_THROW_ON_ERROR);
    $proof->same($task, $result['taskId'], 'The lifetime receipt names the exact task');
    $proof->same($intent['id'], $result['intentRef'], 'The Tasks receipt links the actual Workflow intent');
    $proof->same(VERSION, $result['versionRef'], 'The receipt records the pinned version');
    $proof->same(VERSION, $intent['version_ref'], 'The Workflow intent pins the same version');
    $proof->same(DEFINITION, $intent['definition_ref'], 'The Workflow intent pins the configured definition');
    $proof->same('task:'.$task, $intent['subject_ref'], 'The intent names the same task');
    $proof->same($receipt['payload_hash'], $intent['payload_hash'], 'Both owners persist the same admitted digest');
    $proof->same($receipt['request_key'], $intent['request_key'], 'Both durable rows use the same logical completion key');
    $proof->same($receipt['restore_epoch'], $intent['restore_epoch'], 'Both owners record the same verified restore epoch');
    $proof->same(Scenario::W1, $intent['workspace_id'], 'The intent owns the authenticated task workspace');
    $proof->same('pending', $intent['state'], 'The durable pending intent exists before any provider work');
}

function gateAtBoot(Scenario $proof): ModuleActivationGate
{
    $gate = app(ModuleActivationGate::class);
    $proof->check($gate::class !== TaskLifetimeActivationGate::class, 'Bridge boot decorated the current host policy');
    $proof->check(app('task-lifetime.boot-reader') !== app(RegistryIntrospection::class), 'Provider boot retires the construction-time reader');

    return $gate;
}

function gateInScope(Scenario $proof, ModuleActivationGate $bootGate): ModuleActivationGate
{
    $gate = app(ModuleActivationGate::class);
    $proof->same($bootGate::class, $gate::class, 'Tenant entry retains the bridge on the next scoped gate');
    $proof->check($bootGate !== $gate, 'Tenant entry resolved a fresh scoped gate');

    return $gate;
}

function expectedLookup(?string $workspace, string $version = VERSION): array
{
    return [[Scenario::BOXED, $workspace, Scenario::BOXED, $workspace, TASKS, 'tsk.task', 'completed', DEFINITION, $version]];
}

function checkScopedAvailability(Scenario $proof, ModuleActivationGate $bootGate, RegistryIntrospection $retainedReader, ?RegistryIntrospection $earlyReader = null): array
{
    $verdicts = [];

    foreach ([Scenario::W1, null, Scenario::W1] as $workspace) {
        $verdicts[] = enterTaskScope(static function (DataScope $scope, TenantContext $trusted) use ($proof, $bootGate, $retainedReader, $earlyReader, $workspace): array {
            $gate = gateInScope($proof, $bootGate);
            $engine = app(TaskLifetimeWorkflowEngine::class);
            $outer = app()->bound(TaskLifetimeOuterGate::class) ? app(TaskLifetimeOuterGate::class) : null;
            $denial = $gate->check($trusted, TASKS, 'restricted');
            $proof->same([false, 'Host declined edition', 'upgrade'], [$denial->allowed, $denial->reason, $denial->upgradeHint], 'The scoped host denial preserves its reason and upgrade hint');
            $proof->check($gate->check($trusted, 'fixture/other', 'standard')->allowed, 'An unrelated module passes to the host');

            if ($outer !== null) {
                $checks = $outer->checks;
                $outerDenial = $gate->check($trusted, TASKS, 'outer-restricted');
                $proof->same([false, 'Outer edition restricted', 'outer-upgrade'], [$outerDenial->allowed, $outerDenial->reason, $outerDenial->upgradeHint], 'The outer policy preserves its own denial and hint');
                $proof->same($checks + 1, $outer->checks, 'The explicit outer denial reaches the current policy once');
            }

            $proof->same([], $engine->lookups, 'Host denial and unrelated modules require no Workflow lookup');

            $expected = $workspace === Scenario::W1;
            $expectedDecision = $expected ? [true, null, null] : [false, 'Host declined edition', 'upgrade'];
            $expectedLookups = $expected ? expectedLookup($workspace) : [];
            $proof->check(app(ModuleRegistry::class)->isActive(TASKS, $trusted), 'The stored Tasks activation remains enabled in each scope');
            $outerChecks = $outer?->checks;
            $explanation = app(RegistryIntrospection::class)->why(TASKS, $trusted);
            $proof->same($expected ? 'active' : 'inactive', $explanation->status, 'Public why uses the composed activation gate');
            $proof->same([$expected ? 'enabled_for_tenant' : 'activation_gate_denied'], $explanation->reasons, 'The explanation distinguishes tenant activation from gate denial');
            $readerLookups = $engine->lookups;
            $proof->same($expectedLookups, $readerLookups, 'Public why consults the engine only when the current host allows');

            if ($outer !== null) {
                $proof->same($outerChecks + 1, $outer->checks, 'A fresh public reader consults the current outer policy exactly once');
                $outerChecks = $outer->checks;
            }

            $current = $gate->check($trusted, TASKS, 'standard');
            $proof->same($expectedDecision, [$current->allowed, $current->reason, $current->upgradeHint], 'The current gate preserves the host decision');
            $currentLookups = array_slice($engine->lookups, count($readerLookups));
            $proof->same($expectedLookups, $currentLookups, 'The current gate calls the engine only on host allowance');

            if ($outer !== null) {
                $proof->same($outerChecks + 1, $outer->checks, 'A direct activation check consults the current outer policy exactly once');
            }

            $retained = $bootGate->check($trusted, TASKS, 'standard');
            $proof->same($expectedDecision, [$retained->allowed, $retained->reason, $retained->upgradeHint], 'A retained bridge gate uses the current host decision');
            $lookups = array_slice($engine->lookups, count($readerLookups) + count($currentLookups));
            $proof->same($expectedLookups, $lookups, 'A retained bridge gate also avoids engine lookup on host denial');

            $outerChecks = $outer?->checks;
            $retainedExplanation = $retainedReader->why(TASKS, $trusted);
            $proof->same([$explanation->status, $explanation->reasons], [$retainedExplanation->status, $retainedExplanation->reasons], 'A retained public reader explains the current scope');
            $retainedReaderLookups = array_slice($engine->lookups, count($readerLookups) + count($currentLookups) + count($lookups));
            $proof->same($expectedLookups, $retainedReaderLookups, 'A retained public reader consults the current engine only on host allowance');

            if ($outer !== null) {
                $proof->same($outerChecks + 1, $outer->checks, 'A retained public reader consults the current outer policy exactly once');
            }

            if ($earlyReader !== null) {
                $outerChecks = $outer?->checks;
                $earlyExplanation = $earlyReader->why(TASKS, $trusted);
                $proof->same([$explanation->status, $explanation->reasons], [$earlyExplanation->status, $earlyExplanation->reasons], 'The public reader obtained before provider boot explains the current scope');
                $earlyLookups = array_slice($engine->lookups, count($readerLookups) + count($currentLookups) + count($lookups) + count($retainedReaderLookups));
                $proof->same($expectedLookups, $earlyLookups, 'The pre-boot reader consults the current engine only on host allowance');

                if ($outer !== null) {
                    $proof->same($outerChecks + 1, $outer->checks, 'The pre-boot reader consults the current outer policy exactly once');
                }
            }

            return ['gate' => $gate::class, ...($outer === null ? [] : ['outer_gate' => $outer::class]), 'allowed' => $current->allowed, 'reason' => $current->reason, 'upgrade_hint' => $current->upgradeHint,
                'lookups' => $lookups, 'current_lookups' => $currentLookups,
                'explanation' => $explanation->status, 'reasons' => $explanation->reasons, 'reader_lookups' => $readerLookups,
                'retained_explanation' => $retainedExplanation->status, 'retained_reader_lookups' => $retainedReaderLookups,
                ...($earlyReader === null ? [] : ['early_explanation' => $earlyExplanation->status, 'early_reader_lookups' => $earlyLookups])];
        }, $workspace);
    }

    return $verdicts;
}

function provision(Scenario $proof): array
{
    $migrations = ScenarioSetup::install('task-lifetime', false);
    $proof->check($migrations['a'] !== [], 'The installed Packman schema sets migrated the actual writer');
    $bootGate = config('corex-tasks-workflow.enabled') ? gateAtBoot($proof) : null;
    $activation = enterTaskScope(static function (DataScope $scope, TenantContext $trusted) use ($proof, $bootGate): array {
        app(ModuleLifecycle::class)->enable(TASKS, cascade: true);
        $proof->check(app(ModuleRegistry::class)->isActive(TASKS, $trusted), 'The public lifecycle activates Tasks for the trusted tenant');

        if ($bootGate === null) {
            return [];
        }

        $gate = gateInScope($proof, $bootGate);
        $lookups = app(TaskLifetimeWorkflowEngine::class)->lookups;
        $proof->same(expectedLookup(Scenario::W1), $lookups, 'Public enable validates the supported pin exactly once in the tenant scope');
        $captures = iterator_to_array(app()->tagged(TaskCompletionCapture::TAG));
        $proof->check(count($captures) === 1 && $captures[0] instanceof TaskCompletionCapture
            && str_starts_with($captures[0]::class, 'CoreX\\TasksWorkflow\\'), 'The active tenant resolves the installed bridge completion contributor');

        return ['boot_gate' => $bootGate::class, 'tenant_gate' => $gate::class, 'lookups' => $lookups];
    });
    $availability = $bootGate === null ? [] : checkScopedAvailability($proof, $bootGate, app('task-lifetime.boot-reader'));

    return [$migrations, $activation === [] ? [] : ['boot_reader_gate' => app('task-lifetime.introspection-gate'), ...$activation, 'availability' => $availability]];
}

switch ($argv[1] ?? '') {
    case 'bootstrap':
        $proof->same([true, true, true], array_map(InstalledVersions::isInstalled(...), [TASKS, WORKFLOW, BRIDGE]), 'Composer discovered Tasks, Workflow and the enabled bridge');
        $proof->same(true, config('corex-tasks-workflow.enabled'), 'This startup proof uses the enabled bridge');
        $proof->same([false, false], [app()->resolved(ModuleActivationGate::class), app()->resolved(EngineAdapter::class)], 'Application boot leaves both tenant-only ports unresolved');
        $proof->report($database, [TASKS], [], ['resolver' => app(TenantContextResolver::class)::class]);

        break;

    case 'strict-activation':
        $proof->same([false, false], [app()->resolved(ModuleActivationGate::class), app()->resolved(EngineAdapter::class)], 'The tenant-only ports are unresolved at boot');
        ScenarioSetup::install('task-lifetime', false);
        $activation = enterTaskScope(static function (DataScope $scope, TenantContext $trusted) use ($proof): array {
            $lifecycle = app(ModuleLifecycle::class);
            $proof->same(ModuleState::Available, $lifecycle->status(TASKS)->state, 'Tasks is available before tenant activation');
            $lifecycle->enable(TASKS, cascade: true);
            $proof->same(ModuleState::Enabled, $lifecycle->status(TASKS)->state, 'Public lifecycle enables Tasks in W1');
            $lookups = app(TaskLifetimeWorkflowEngine::class)->lookups;
            $proof->same(expectedLookup(Scenario::W1), $lookups, 'Activation resolves the exact pin in the tenant workspace');

            return ['gate' => app(ModuleActivationGate::class)::class, 'lookups' => $lookups];
        });
        $proof->report($database, [TASKS], [], $activation);

        break;

    case 'diagnostics':
        $hasEarlyReader = app()->bound('task-lifetime.boot-reader');
        $proof->same(getenv('COREX_SCENARIO_TASKS_LATE_REGISTRY') !== '1', $hasEarlyReader, 'The kernel uses the requested registry reader order');

        if ($hasEarlyReader) {
            $bootReadOrder = getenv('COREX_SCENARIO_TASKS_GATE_FIRST') === '1' ? 'gate-first' : 'reader-first';
            $proof->same($bootReadOrder, app('task-lifetime.boot-read-order'), 'The warm host used the requested public lookup order');
        }

        $gate = $hasEarlyReader ? gateAtBoot($proof) : app(ModuleActivationGate::class);

        if (($argv[3] ?? '') === 'outer') {
            $proof->check(app(TaskLifetimeOuterGate::class) instanceof TaskLifetimeOuterGate, 'The public chain includes the host decorator installed in boot');
        }
        $first = ($argv[2] ?? 'account') === 'workspace' ? Scenario::W1 : null;
        $firstScopedReader = enterTaskScope(static function (): RegistryIntrospection {
            $reader = app(RegistryIntrospection::class);
            $reader->extensionPoints();

            return $reader;
        }, $first);
        $earlyReader = $hasEarlyReader ? app('task-lifetime.boot-reader') : null;
        $availability = checkScopedAvailability($proof, $gate, $firstScopedReader, $earlyReader);
        $proof->report($database, [TASKS], ['early_reader' => $hasEarlyReader, 'first_reader_workspace' => $first], $availability);

        break;

    case 'activation':
        $migrations = ScenarioSetup::install('task-lifetime', false);
        $proof->check($migrations['a'] !== [], 'The installed schemas migrated the actual owner database');
        $proof->same('fixture:missing-version', config('corex-tasks-workflow.version_ref'), 'The cached config pins the unavailable version');
        $bootGate = gateAtBoot($proof);
        [$before, $after] = enterTaskScope(static function (DataScope $scope, TenantContext $trusted) use ($proof, $bootGate): array {
            $lifecycle = app(ModuleLifecycle::class);
            $modules = app(ModuleRegistry::class);
            $before = ['state' => $lifecycle->status(TASKS)->state->value, 'active' => $modules->isActive(TASKS, $trusted)];
            $proof->same(['state' => ModuleState::Available->value, 'active' => false], $before, 'Tasks is available before the activation attempt');

            try {
                $lifecycle->enable(TASKS, cascade: true);

                throw new RuntimeException('The unavailable pinned Workflow version allowed Tasks activation.');
            } catch (AuthorizationException $exception) {
                $proof->same('The pinned task workflow definition and version have no active engine.', $exception->getMessage(), 'The public lifecycle refuses the unsupported version');
            }

            $after = ['state' => $lifecycle->status(TASKS)->state->value, 'active' => $modules->isActive(TASKS, $trusted)];
            $proof->same($before, $after, 'The refused activation leaves Tasks available and inactive');
            $gate = gateInScope($proof, $bootGate);
            $lookups = app(TaskLifetimeWorkflowEngine::class)->lookups;
            $proof->same(expectedLookup(Scenario::W1, 'fixture:missing-version'), $lookups, 'Public enable consults the scoped engine exactly once for the unavailable pin');

            return [$before, [...$after, 'tenant_gate' => $gate::class, 'lookups' => $lookups]];
        });
        $tables = ['tsk_tasks', 'corex_task_operations', 'sys_workflow_intents', 'mod_modules', 'mod_lifecycle_log'];
        $writes = array_map(static fn (string $table): int => $database->table($table)->count(), array_combine($tables, $tables));
        $proof->same(array_fill_keys($tables, 0), $writes, 'Denied activation writes no business rows, activation state, or lifecycle log');
        $proof->report($database, [TASKS], [...$before, 'boot_reader_gate' => app('task-lifetime.introspection-gate'), 'boot_gate' => $bootGate::class], [
            ...$after, 'refusal_at' => 'ModuleLifecycle::enable', 'writes' => $writes,
        ]);

        break;

    case 'registration-setup':
        ScenarioSetup::install('task-lifetime', false);
        $proof->report($database, [TASKS], [], []);

        break;

    case 'registration':
        $mode = $argv[3];
        $wiring = $argv[4];
        $hostDenies = ($argv[5] ?? '') === 'deny';
        $outerPolicy = ($argv[6] ?? '') === 'outer';
        $version = config('corex-tasks-workflow.version_ref');
        $supported = $version === VERSION;
        $order = app('task-lifetime.registration-order');
        $proof->same($argv[2], $order, 'The catalogue provider ran before the final host registration');
        $proof->same(str_ends_with($wiring, '-alias') ? 'task-lifetime.host-policy' : ModuleActivationGate::class, app()->getAlias(ModuleActivationGate::class), 'The final host retains its canonical binding');
        $earlyReader = $order === 'reader' ? app('task-lifetime.registration-reader') : null;
        $reason = $hostDenies ? 'Host declined edition' : ($supported ? null : 'The pinned task workflow definition and version have no active engine.');
        $allowed = $supported && ! $hostDenies;
        $lookups = $hostDenies ? [] : expectedLookup(Scenario::W1, $version);
        $outcome = enterTaskScope(static function (DataScope $scope, TenantContext $trusted) use ($proof, $mode, $reason, $allowed, $lookups, $hostDenies, $earlyReader, $outerPolicy): array {
            $gate = app(ModuleActivationGate::class);
            $host = app()->bound('task-lifetime.registered-host') ? app('task-lifetime.registered-host') : null;
            $expectedDecision = [$allowed, $reason, $hostDenies ? 'upgrade' : null];
            $decision = $gate->check($trusted, TASKS, 'standard');
            $proof->same($expectedDecision, [$decision->allowed, $decision->reason, $decision->upgradeHint], 'The final public gate checks the pin after the host allows');
            $proof->same($host === null ? null : 1, $host?->checks, 'The exact supplied host object handled the current gate request');

            if ($hostDenies) {
                $proof->same(false, app()->resolved(EngineAdapter::class), 'Host denial leaves the engine unresolved');
            }

            $engine = app(TaskLifetimeWorkflowEngine::class);
            $proof->same($lookups, $engine->lookups, 'The final public gate consults the exact pin once on host allowance');
            $result = ['decision' => $expectedDecision, 'gate_lookups' => $engine->lookups];

            if ($outerPolicy) {
                $outer = app(TaskLifetimeOuterGate::class);
                $checks = $outer->checks;
                $denial = $gate->check($trusted, TASKS, 'outer-restricted');
                $proof->same([false, 'Outer edition restricted', 'outer-upgrade'], [$denial->allowed, $denial->reason, $denial->upgradeHint], 'An opaque outer policy may deny after its delegate allows');
                $proof->same($checks + 1, $outer->checks, 'The current opaque policy is called once');
                $proof->same($lookups, $engine->lookups, 'The final outer denial avoids an engine lookup');
            }

            $lifecycle = app(ModuleLifecycle::class);

            if ($mode === 'enable') {
                $proof->same(ModuleState::Available, $lifecycle->status(TASKS)->state, 'The activation starts with Tasks available');

                try {
                    $lifecycle->enable(TASKS, cascade: true);
                    $proof->same(true, $allowed, 'A permitted activation completes');
                } catch (AuthorizationException $exception) {
                    $proof->same(false, $allowed, 'A denied activation refuses');
                    $proof->same($reason, $exception->getMessage(), 'Public lifecycle preserves the refusal reason');
                }

                $result['activation_lookups'] = array_slice($engine->lookups, count($lookups));
                $proof->same($lookups, $result['activation_lookups'], 'Public lifecycle consults the exact pin once after host allowance');
            }

            $result['active'] = app(ModuleRegistry::class)->isActive(TASKS, $trusted);
            $proof->same($allowed, $result['active'], 'The final activation state matches the gate decision');

            if ($earlyReader !== null && $result['active']) {
                $previous = count($engine->lookups);
                $proof->same('active', $earlyReader->why(TASKS, $trusted)->status, 'A retained registration-time reader follows the final gate');
                $result['early_lookups'] = array_slice($engine->lookups, $previous);
                $proof->same($lookups, $result['early_lookups'], 'The retained registration-time reader consults the final pin once');
            }

            return $result;
        });

        if ($mode === 'enable' && ! $allowed) {
            $tables = ['tsk_tasks', 'corex_task_operations', 'sys_workflow_intents', 'mod_modules', 'mod_lifecycle_log'];
            $outcome['writes'] = array_map(static fn (string $table): int => $database->table($table)->count(), array_combine($tables, $tables));
            $proof->same(array_fill_keys($tables, 0), $outcome['writes'], 'Refusal occurs before all five owner and lifecycle writes');
        }

        $proof->report($database, [TASKS], ['order' => $order, 'wiring' => $wiring, 'pin' => $version], $outcome);

        break;

    case 'producer':
        [$migrations, $activation] = provision($proof);
        $tasks = enterTaskScope(static function () use ($database): array {
            $database->table('tsk_projects')->insert([
                'id' => PROJECT, 'workspace_id' => Scenario::W1, 'name' => 'Installed task project',
                'created_by' => json_encode(actor()->toArray(), JSON_THROW_ON_ERROR),
            ]);
            $database->table('tsk_stages')->insert([
                ['id' => IN_PROGRESS, 'project_id' => PROJECT, 'code' => 'doing', 'name' => '{"en":"Doing"}', 'auto_status' => 'in_progress'],
                ['id' => DONE, 'project_id' => PROJECT, 'code' => 'closed', 'name' => '{"en":"Closed"}', 'auto_status' => 'done'],
            ]);

            return array_map(static fn (string $title): string => app(CreateTaskCommand::class)->handle(
                new TaskDraft(Scenario::W1, $title, actor(), projectId: PROJECT),
            )->id, ['Race task', 'Conflict task']);
        });
        $initial = snapshot($database, $tasks[0]);
        $proof->same('open', $initial['task']['status'], 'Two concurrent commands start from an Open task');
        $proof->same([[], []], [$initial['receipts'], $initial['intents']], 'A task creation has no completion receipt or Workflow intent');
        $proof->report($database, $tasks, [], ['migrations' => $migrations, 'activation' => $activation, ...$initial]);

        break;

    case 'complete-a':
    case 'complete-b':
        $mode = $argv[1];
        $task = taskId('Race task');
        $before = snapshot($database, $task);
        $result = enterTaskScope(static function () use ($proof, $task, $mode): TaskReference {
            $writer = DB::connection();
            $identity = FieldPgWitness::markSession($writer, $mode);

            if ($mode === 'complete-a') {
                $class = app(EntityRegistry::class)->get('tsk.task')->model;
                Event::listen('eloquent.updating: '.$class, static function (Model $model) use ($proof, $task, $writer, $identity): void {
                    $proof->same($task, $model->getKey(), 'The Task ORM is about to save the raced row');
                    $proof->same(TaskStatus::Done, $model->getAttribute('status'), 'The changed status is Done inside the writer');
                    $proof->check($model->getConnection() === $writer && $writer->transactionLevel() > 0, 'The Eloquent update runs on the verified owner transaction');
                    $private = snapshot($writer, $task);
                    $proof->same('open', $private['task']['status'], 'The owner has captured before the Task SQL update');
                    $proof->same([1, 1], [count($private['receipts']), count($private['intents'])], 'Both real durable writes precede the Task update');
                    Scenario::barrier('task-before-save', ['connection' => $identity, ...$private]);
                });
            } else {
                Scenario::barrier('task-b-ready', ['connection' => $identity]);
            }

            return complete($task);
        });
        $proof->same($task, $result->id, 'The installed CompleteTaskCommand converges on the same task identity');
        $after = snapshot($database, $task);
        assertSingleCompletion($proof, $task, $after);
        $proof->report($database, [$result->id, $after['intents'][0]['id']], $before, $after);

        break;

    case 'observer':
        $a = Scenario::barrierRead('task-before-save');
        $b = Scenario::barrierRead('task-b-ready');
        $own = Scenario::describe($database);
        $proof->same(3, count(array_unique([$a['connection']['session'], $b['connection']['session'], $own['session']])), 'Both command writers and the observer use independent PostgreSQL sessions');
        $proof->same([$own['database'], $own['database']], [$a['connection']['database'], $b['connection']['database']], 'All three sessions address the same owner database');
        $witness = new FieldPgWitness($database);
        $blocked = FieldPgWitness::await('the second CompleteTaskCommand blocks behind the first task lock', static fn (): ?bool => $witness->blockedBy($b['connection']['session'], $a['connection']['session']) ? true : null);
        $proof->check($blocked, 'PostgreSQL reports A holding the lock B needs');
        $activity = $database->selectOne('SELECT wait_event_type, wait_event, query FROM pg_catalog.pg_stat_activity WHERE pid = ? AND datname = current_database()', [$b['connection']['session']]);
        $proof->same('Lock', $activity->wait_event_type, 'The second SQL session is blocked on a lock');
        $proof->check(str_contains(strtolower($activity->query), 'tsk_tasks') && str_contains(strtolower($activity->query), 'for update'), 'The actual waiting statement locks the Task row');
        $visible = snapshot($database, $a['task']['id']);
        $proof->same('open', $visible['task']['status'], 'A separate PostgreSQL connection still sees Open');
        $proof->same(null, $visible['task']['completed_at'], 'The separate reader sees no completion time');
        $proof->same([[], []], [$visible['receipts'], $visible['intents']], 'The separate reader sees no uncommitted receipt or intent');
        $proof->report($database, [$a['task']['id']], ['writer_a' => $a, 'writer_b' => $b], ['blocked' => (array) $activity, 'visible' => $visible]);

        break;

    case 'lifetime':
        $task = taskId('Race task');
        $before = snapshot($database, $task);
        assertSingleCompletion($proof, $task, $before);
        move($task, IN_PROGRESS);
        $reopened = snapshot($database, $task);
        $proof->same(['in_progress', IN_PROGRESS, null], [$reopened['task']['status'], $reopened['task']['stage_id'], $reopened['task']['completed_at']], 'Move to the InProgress stage reopens and clears completion time');
        app(ScenarioClock::class)->advance(90);
        move($task, DONE);
        $closed = snapshot($database, $task);
        $proof->same(DONE, $closed['task']['stage_id'], 'Move to the Done stage commits the configured stage');
        assertSingleCompletion($proof, $task, $closed);
        $proof->check($closed['task']['completed_at'] !== $before['task']['completed_at'], 'Recompletion records a new completion time');
        $proof->same([$before['receipts'], $before['intents']], [$closed['receipts'], $closed['intents']], 'The second completion keeps the original immutable capture and version');
        enterTaskScope(static fn (): TaskReference => complete($task));
        $replay = snapshot($database, $task);
        $proof->same($closed, $replay, 'Complete on Done does not update the PostgreSQL task row, receipt or intent');
        $proof->report($database, [$task, $closed['intents'][0]['id']], $before, ['reopened' => $reopened, 'closed' => $closed, 'replay' => $replay]);

        break;

    case 'stale-epoch':
        $task = taskId('Conflict task');
        $historyTask = taskId('Race task');
        [$oldScope, $oldProof] = enterTaskScope(static fn (DataScope $scope): array => [
            $scope,
            app(StoredWorkIssuer::class)->issue($scope, 'fixture.owner', 'complete:'.$task),
        ]);
        $stored = app(StoredWorkRunner::class);
        $proof->same(Scenario::W1, $stored->runStored($oldScope, $oldProof, static fn (TenantContext $context): ?string => $context->workspace?->id), 'The old signed work is admissible before the restore');
        $control = app(DatabaseScopeControl::class);
        $account = Scenario::account(Scenario::BOXED);
        $old = $control->resolve($account);
        $closed = $control->drain($account->id, $control->close($account->id, $old->controlRevision));
        $next = Scenario::binding($old->bindingId, $old->bindingGeneration, 'b', $account->id);
        $control->reopen($account->id, $closed, $next, static function (PhysicalBinding $owner) use ($database): bool {
            PhysicalMarker::assert($owner, $database, $database->getDatabaseName());

            return true;
        });
        $proof->same($next->restoreEpoch, $control->resolve($account)->restoreEpoch, 'The independent control database advanced the active restore epoch');
        $before = snapshot($database, $task);
        $proof->same('open', $before['task']['status'], 'The obsolete command targets an as-yet unfinished task');
        $called = false;

        try {
            $stored->runStored($oldScope, $oldProof, static function () use ($task, &$called): void {
                $called = true;
                complete($task);
            });

            throw new RuntimeException('Old completion work crossed the new restore fence.');
        } catch (ScopeStale $error) {
            $proof->check(! $called, 'The stale restore epoch is rejected before the real Task command is reached');
        }
        $after = snapshot($database, $task);
        $proof->same($before, $after, 'Stale queued completion leaves both owners and task row unchanged');
        $proof->same($next->restoreEpoch, enterTaskScope(static fn (DataScope $scope): string => $scope->restoreEpoch), 'New work can enter under the verified new fence');
        $historical = snapshot($database, $historyTask);
        $proof->same($oldScope->restoreEpoch, $historical['receipts'][0]['restore_epoch'], 'The adopted historical receipt retains its issuing epoch');
        move($historyTask, IN_PROGRESS, workspace: null);
        $reopened = snapshot($database, $historyTask);
        $proof->same(['in_progress', null], [$reopened['task']['status'], $reopened['task']['completed_at']], 'The fresh frame can reopen the adopted historical task');
        app(ScenarioClock::class)->advance(210);
        enterTaskScope(static function () use ($proof, $historyTask): void {
            app(MoveTaskToStageCommand::class)->handle(new MoveTaskToStageData($historyTask, DONE, actor()));
            $proof->same([], app(TaskLifetimeWorkflowEngine::class)->lookups, 'An authorized account-wide repeat reuses the receipt without a Workflow lookup');
        }, workspace: null);
        $completed = snapshot($database, $historyTask);
        assertSingleCompletion($proof, $historyTask, $completed);
        $proof->check($completed['task']['completed_at'] !== $historical['task']['completed_at'], 'The fresh completion records its own time after restore');
        $proof->same([$historical['receipts'], $historical['intents']], [$completed['receipts'], $completed['intents']], 'Adopted completion preserves the exact original receipt fence, digest and intent');
        $proof->report($database, [$task, $historyTask], ['epoch' => $oldScope->restoreEpoch, 'obsolete_task' => $before, 'historical' => $historical], [
            'epoch' => $next->restoreEpoch, 'completion_called' => $called, 'refusal' => $error::class, 'obsolete_task' => $after,
            'reopened' => $reopened, 'historical' => $completed,
        ]);

        break;

    case 'conflict':
        $task = taskId('Conflict task');
        $key = enterTaskScope(static function (DataScope $scope, TenantContext $context) use ($proof, $task): string {
            $key = hash('sha256', json_encode(['completion-capture/2', $scope->accountId, $scope->accountIncarnation, $task], JSON_THROW_ON_ERROR));
            $connection = DB::connection();
            $result = $connection->transaction(static fn () => app(WorkflowBinding::class)->capture(new TriggerRequest(
                $context, $scope, TASKS, 'tsk.task', 'task:'.$task, 'completed', 'task:'.$task, DEFINITION, VERSION,
                JsonDocuments::capture(['taskId' => $task, 'status' => 'divergent-fixture']), $key,
            ), $connection));
            $proof->same(CaptureStatus::Created, $result->status, 'The real Workflow API commits a valid conflicting capture at the exact lifetime key');

            return $key;
        });
        $before = snapshot($database, $task);
        $proof->same('open', $before['task']['status'], 'The conflict target has never completed');
        $proof->same($key, $database->table('sys_workflow_intents')->where('subject_ref', 'task:'.$task)->value('request_key'), 'The conflicting Workflow intent owns the expected request key');
        $refusal = null;

        try {
            enterTaskScope(static function () use ($task, &$refusal): void {
                try {
                    complete($task);
                } catch (LogicException $error) {
                    $refusal = $error;
                }
            });
            $proof->same('Conflicting completion intent for the task.', $refusal?->getMessage(), 'The real completion command refuses the first capture digest conflict');
        } finally {
            $after = snapshot($database, $task);
            $proof->same($before, $after, 'The failed first capture rolls back the task row, Task receipt and Workflow changes');
        }
        $proof->report($database, [$task, $key], $before, ['refusal' => $refusal !== null ? $refusal::class : null, ...$after]);

        break;

    case 'duplicate':
        $task = taskId('Race task');
        $before = snapshot($database, $task);
        $boot = new Process([PHP_BINARY, __FILE__, 'duplicate-at-bootstrap'], getcwd(), ['COREX_SCENARIO_DUPLICATE_COMPLETION' => '1']);
        $boot->run();
        $proof->same(41, $boot->getExitCode(), 'The fresh Laravel kernel refuses its duplicate contributor. '.$boot->getOutput().$boot->getErrorOutput());
        $refusal = json_decode($boot->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $proof->same(LogicException::class, $refusal['error'], 'The installed composition boot emits a typed configuration refusal');
        $proof->check(in_array($refusal['message'], [
            'Tasks accepts at most one task completion capture contributor.',
            'Enabled task workflow completion requires exactly one TaskCompletionCapture contributor.',
        ], true), 'The refusal identifies the duplicate tagged completion contributor');
        $proof->check($refusal['pid'] !== getmypid(), 'The configuration attack boots a fresh independent PHP process');
        $after = snapshot($database, $task);
        $proof->same($before, $after, 'Duplicate contributor boot leaves the Task and Workflow owners unchanged');
        $proof->report($database, [$task], $before, ['boot_exit' => $boot->getExitCode(), 'refusal' => $refusal, ...$after]);

        break;

    case 'composition':
        [$migrations, $activation] = provision($proof);
        $workflow = $variant !== 'recurring';
        $recurring = $variant !== 'without-bridge';
        $bridge = $variant === 'everything';
        $proof->same([true, $workflow, $recurring, $bridge], array_map(InstalledVersions::isInstalled(...), [TASKS, WORKFLOW, RECURRING, BRIDGE]), 'Composer resolved the independent installed package composition');
        $proof->same($workflow, $database->getSchemaBuilder()->hasTable('sys_workflow_intents'), 'Workflow tenant schema is present exactly when installed');
        $proof->same($recurring, $database->getSchemaBuilder()->hasTable('corex_task_occurrences'), 'The RecurringTasks owner schema is present exactly when installed');
        $task = $recurring ? TaskLifetimeSeries::materialize($proof, $database, enterTaskScope(...)) : enterTaskScope(static fn (): string => app(CreateTaskCommand::class)->handle(new TaskDraft(Scenario::W1, 'Independent task', actor()))->id);
        $before = snapshot($database, $task, $workflow);
        $proof->same('open', $before['task']['status'], 'The installed Tasks owner persists Open prior to completion');
        enterTaskScope(static fn (): TaskReference => complete($task));
        $after = snapshot($database, $task, $workflow);

        if ($bridge) {
            assertSingleCompletion($proof, $task, $after);
        } else {
            $proof->same('done', $after['task']['status'], 'Ordinary Task completion succeeds without a workflow contributor');
            $proof->same([[], []], [$after['receipts'], $after['intents']], 'Optional composition creates neither capture receipt nor workflow intent');
        }
        enterTaskScope(static fn (): TaskReference => complete($task));
        $proof->same($after, snapshot($database, $task, $workflow), 'Completion replay preserves the original owner rows');
        $proof->report($database, [$task], ['migrations' => $migrations, 'activation' => $activation, ...$before], [
            'recurring' => $recurring, 'workflow' => $workflow, 'bridge' => $bridge,
            'occurrences' => $recurring ? $database->table('corex_task_occurrences')->count() : 0,
            'materialization_receipts' => $recurring ? $database->table('corex_task_operations')->where('kind', 'materialize')->count() : 0,
            ...$after,
        ]);

        break;

    default:
        throw new RuntimeException('Unknown installed task lifetime action.');
}
