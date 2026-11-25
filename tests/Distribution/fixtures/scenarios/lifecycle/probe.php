<?php

declare(strict_types=1);

use App\Lifecycle\Deployment;
use App\Lifecycle\ExternalResources;
use App\Lifecycle\HostModule;
use App\Lifecycle\Installation;
use App\Lifecycle\Inventory;
use App\Lifecycle\NativeSearch;
use App\Lifecycle\ObservedAdmission;
use App\Lifecycle\Views;
use App\Scenario\FixtureLifecycleOperators;
use App\Scenario\PhysicalMarker;
use App\Scenario\Scenario;
use App\Scenario\SearchScenarioMeili;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\DeploymentOwners;
use CoreX\Foundation\Contracts\LifecyclePlans;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ProviderRemovalGate;
use CoreX\Foundation\Data\LifecycleConfirmation;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleId;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Foundation\Data\PlanDigest;
use CoreX\Foundation\Enums\LifecycleAction;
use CoreX\Foundation\Enums\LifecyclePhase;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Foundation\Exceptions\LifecyclePlanConflict;
use CoreX\Panels\Contracts\SavedViewInventory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Packman\Data\Laravel\JsonDocuments;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Enums\Lifecycle\DisableReason;
use Packman\Modules\Enums\Lifecycle\ModuleState;
use Packman\Panels\SavedViews\Contracts\SavedViewRepository;
use Packman\Panels\SavedViews\Data\SavedViewRef;
use Packman\Panels\SavedViews\Data\SaveViewInput;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$mode = $argv[1];
$observer = Scenario::connection('a');
$control = Scenario::connection('control');

function savedView(): SavedViewRef
{
    return resolve(SavedViewRepository::class)->save(Views::context(), new SaveViewInput(null, '', Views::scopes()[1], 'table', ['en' => 'Inspections'],
        JsonDocuments::capture(['version' => 'saved-view/1', 'filters' => ['field' => 'inspection_code', 'op' => 'eq', 'value' => 'A-42'],
            'sorts' => [], 'columns' => ['inspection_code'], 'groupBy' => null, 'presentation' => []])));
}

function deny(Scenario $proof, string $exception, Closure $operation): string
{
    try {
        $operation();
    } catch (Throwable $error) {
        $proof->check($error instanceof $exception, 'Expected '.$exception.', received '.$error::class.' '.$error->getMessage());

        return $error->getMessage();
    }

    throw new RuntimeException('Expected refusal '.$exception);
}

if ($mode === 'setup') {
    $ran = Installation::setup();
    $proof->check($ran['a'] !== [] && $ran['b'] !== [], 'Both physical installations ran migrations');
    $foreign = Inventory::enter(Scenario::ACCOUNT_B, Scenario::W2, static function (DataScope $scope): array {
        $row = Installation::read(Scenario::ACCOUNT_B);
        SearchScenarioMeili::task($row['task']);

        return ExternalResources::observe(ExternalResources::target($scope));
    });
    $proof->same(200, $foreign['http'], 'Control index is present in the second namespace');
    $other = Inventory::enter(Scenario::ACCOUNT_B, Scenario::W2, static fn (DataScope $s) => ExternalResources::target($s));
    $controls = Inventory::enter(Scenario::ACCOUNT_A, Scenario::W1, static function (DataScope $scope) use ($proof, $other): array {
        $target = ExternalResources::target($scope);
        $unauthorized = deny($proof, RuntimeException::class, static fn () => ExternalResources::observe($target,
            Http::baseUrl(getenv('MEILISEARCH_HOST'))->withToken('fixture-invalid-observe-key')->timeout(3)));
        $proof->check(str_contains($unauthorized, 'HTTP 403') || str_contains($unauthorized, 'HTTP 401'), 'Actual Meili rejected the credential');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $closed = stream_socket_get_name($socket, false);
        fclose($socket);
        $network = deny($proof, ConnectionException::class, static fn () => ExternalResources::observe($target, Http::baseUrl('http://'.$closed)->timeout(2)));
        $unknown = deny($proof, RuntimeException::class, static fn () => ExternalResources::observe($other));

        return ['auth' => $unauthorized, 'network' => $network, 'foreign' => $unknown];
    });
    $proof->report($observer, [Inventory::MODULE, $foreign], [], ['sets' => $ran, 'owned' => Installation::read(Scenario::ACCOUNT_A), 'absence_controls' => $controls]);

    return;
}

if ($mode === 'native-search') {
    $result = NativeSearch::run($proof);
    $proof->report(Scenario::connection('b'), [$result['target']->uid, $result['task']['uid']], [], $result);

    return;
}

if ($mode === 'stale') {
    $account = Scenario::account(Scenario::ACCOUNT_B);
    $physical = Scenario::connection('b');
    $route = resolve(DatabaseScopeControl::class);
    $module = new ModuleId(Inventory::MODULE);
    $fields = Inventory::enter($account->id, Scenario::W2, static function (DataScope $scope) use ($proof): array {
        $inventory = array_find(iterator_to_array(app()->tagged(ModuleReferences::class)), static fn ($p) => $p->owner() === 'axiomasoft/corex-custom-fields');
        $cursor = null;
        $blocks = [];
        do {
            $page = $inventory->scan(new ModuleRef(Inventory::MODULE), $scope, $cursor);
            array_push($blocks, ...$page->blockers);
            $cursor = $page->next;
        } while ($cursor !== null);
        $proof->same(3, count(array_filter($blocks, static fn ($b) => $b->resourceKind === 'sys_attribute_definitions' && $b->scope == $scope)),
            'The second owner retains all three materialized code-field references under its real workspace');

        return $blocks;
    });
    [$old, $plan] = Inventory::enter($account->id, null, static fn (DataScope $s) => [$s, resolve(LifecyclePlans::class)->plan($s, $module, LifecycleAction::Disable)]);
    $original = $route->resolve($account);
    $closed = $route->drain($account->id, $route->close($account->id, $original->controlRevision));
    $next = Scenario::binding($original->bindingId, $original->bindingGeneration, 'b', $account->id);
    $route->reopen($account->id, $closed, $next, static function ($binding) use ($physical): bool {
        PhysicalMarker::assert($binding, $physical, $physical->getDatabaseName());

        return true;
    });
    $before = $physical->table('sys_lifecycle_plans')->where('plan_id', $plan->ref->id)->sole();
    $new = Inventory::enter($account->id, null, static function (DataScope $scope) use ($proof, $plan, $old): DataScope {
        $plans = resolve(LifecyclePlans::class);
        FixtureLifecycleOperators::grant($scope, Inventory::MODULE, 'disable');
        FixtureLifecycleOperators::grant($scope, Inventory::MODULE, 'inspect');
        deny($proof, ScopeStale::class, static fn () => $plans->apply($old, $plan->ref, $plan->digest));
        deny($proof, LifecycleBlocked::class, static fn () => $plans->apply($scope, $plan->ref, $plan->digest));
        $proof->same(ModuleState::Enabled, resolve(ModuleLifecycle::class)->status(Inventory::MODULE)->state, 'The stale plan cannot advance Packman under fresh grants');

        return $scope;
    });
    $proof->same((array) $before, (array) $physical->table('sys_lifecycle_plans')->where('plan_id', $plan->ref->id)->sole(), 'Refusal leaves the prior plan and its receipts unchanged');
    $proof->report($physical, [$plan], ['scope' => $old], ['scope' => $new, 'fields' => $fields]);

    return;
}

$workspace = in_array($mode, ['writer', 'denied-writer', 'lost-writer'], true) ? Scenario::W1 : null;
Inventory::enter(Scenario::ACCOUNT_A, $workspace, static function (DataScope $scope) use ($mode, $proof, $observer, $control): void {
    $module = new ModuleId(Inventory::MODULE);
    $target = new ModuleRef(Inventory::MODULE);
    $plans = resolve(LifecyclePlans::class);
    $lifecycle = resolve(ModuleLifecycle::class);
    $before = [];
    $after = [];
    switch ($mode) {
        case 'prerequisites':
            $dependency = new ModuleRef('axiomasoft/packman-panels');
            [$installed, $proposed] = Deployment::digests($dependency->package);
            $required = deny($proof, RuntimeException::class, static fn () => resolve(ProviderRemovalGate::class)->prepare($dependency, $installed, $proposed));
            $composer = json_decode($required, true, flags: JSON_THROW_ON_ERROR);
            $proof->same($dependency->package, $composer['required'], 'Composer identifies the exact package selected for removal');
            $proof->check(isset($composer['dependents'][Inventory::PROVIDER]), 'The installed Panels provider retains the prerequisite with its version constraint');
            $lifecycle->enable(HostModule::PACKAGE);
            $refusal = deny($proof, LifecycleBlocked::class, static fn () => $lifecycle->disable(Inventory::MODULE, DisableReason::Manual));
            $consumer = resolve(ModuleRegistry::class)->get(HostModule::PACKAGE);
            $requirement = array_find($consumer->requires, static fn (array $r) => $r['kind'] === 'module' && $r['package'] === Inventory::MODULE);
            $proof->check($requirement !== null && $lifecycle->status($consumer->composerName)->state === ModuleState::Enabled,
                'The actual enabled runtime dependent declares the refused module in the compiled registry');
            $proof->same(null, resolve(DatabaseScopeControl::class)->moduleClosure($scope, $target), 'The prerequisite refusal precedes opening a destructive plan');
            $lifecycle->disable(HostModule::PACKAGE, DisableReason::Manual);
            $after = ['composer' => ['scope' => $scope, 'owner' => Inventory::PROVIDER, 'ref' => $dependency, 'constraints' => $composer['dependents']],
                'runtime' => ['scope' => $scope, 'owner' => $consumer->composerName, 'ref' => $module, 'requirement' => $requirement, 'refusal' => $refusal]];

            break;

        case 'writer':
        case 'lost-writer':
            if ($mode === 'lost-writer') {
                $admission = new ObservedAdmission(resolve(ScopeAdmission::class));
                app()->instance(ScopeAdmission::class, $admission);
            }
            DB::beginTransaction();
            $view = savedView();
            $compiled = resolve(SavedViewRepository::class)->compile(Views::context(), $view->id);
            $proof->check(count($compiled->projection) === 1, 'Packman compiled the active saved view inside the real outer transaction');
            $ids = resolve(DatabaseScopeControl::class)->outstandingReferences($scope, $target);
            $proof->same(1, count($ids), 'One active transaction retains target membership');
            $before = ['scope' => $scope, 'view' => $view, 'ticket' => $ids[0], 'compiled' => $compiled->projection, 'backend' => Scenario::describe(DB::connection())];

            if ($mode === 'lost-writer') {
                $before['original'] = $admission->tickets[$ids[0]];
                $before['xid'] = DB::scalar('SELECT pg_current_xact_id()::text');
                $proof->report(DB::connection(), [$view->id], [], $before);
                Scenario::barrier('abandoned-write', $before);

                throw new RuntimeException('The outer writer must die at the barrier.');
            }
            Scenario::barrier('before-commit', $before);
            DB::commit();
            $proof->same([], resolve(DatabaseScopeControl::class)->outstandingReferences($scope, $target), 'Outermost commit settled the admission');
            $after = ['committed' => $view->id];

            break;

        case 'fence':
            $writer = Scenario::barrierRead('before-commit');
            $error = deny($proof, LifecycleBlocked::class, static fn () => $lifecycle->disable(Inventory::MODULE, DisableReason::Manual));
            $ref = new LifecyclePlanRef(resolve(DatabaseScopeControl::class)->moduleClosure($scope, $target));
            $status = $plans->inspect($scope, $ref);
            $proof->same([$writer['ticket']], array_column($status->blockers, 'resourceId'), 'The live before-fence writer blocks transition');
            $proof->same(ModuleState::Enabled, $lifecycle->status(Inventory::MODULE)->state, 'Blocked transition keeps the module enabled');
            Installation::record('disable', $status);
            $after = ['status' => $status, 'direct' => $error];

            break;

        case 'denied-writer':
            $reason = deny($proof, LifecycleBlocked::class, savedView(...));
            $proof->same([], resolve(SavedViewRepository::class)->viewsFor(Views::context(), Views::scopes()[1]), 'Second connection sees neither an uncommitted view nor a denied write');
            $after = ['denial' => $reason, 'scope' => $scope];

            break;

        case 'blockers':
            $plan = $plans->plan($scope, $module, LifecycleAction::Disable);
            $status = $plans->inspect($scope, $plan->ref);
            $initial = Installation::read(Scenario::ACCOUNT_A);
            $view = Scenario::barrierRead('before-commit')['view'];
            $expected = [['axiomasoft/packman-panels', 'saved-view', $view['id']], [ExternalResources::OWNER, 'meili-task', $initial['task']],
                [ExternalResources::OWNER, 'retained-generation', $initial['uid']], [ExternalResources::OWNER, 'invalid-cic', $initial['index']['oid']]];
            foreach ($expected as [$owner, $kind, $id]) {
                $matching = array_filter($status->blockers, static fn ($b) => $b->owner === $owner && $b->resourceKind === $kind && $b->resourceId === $id
                    && $b->scope->accountId === Scenario::ACCOUNT_A && $b->scope->workspaceId === Scenario::W1 && $b->requiredAction !== '');
                $proof->same(1, count($matching), 'Exact scoped blocker '.$kind.' '.$id);
            }
            Installation::record('blockers', $status);
            $after = ['status' => $status, 'composer' => Deployment::digests(), 'runtime' => resolve(ModuleRegistry::class)->compiled()->fingerprint];

            break;

        case 'owner-off':
        case 'lease-off':
            if ($mode === 'owner-off') {
                app()->offsetUnset(SavedViewInventory::class);
            } else {
                $observer->statement('ALTER TABLE scenario_view_directory_leases RENAME TO scenario_inaccessible_leases');
            }

            try {
                $plan = $plans->plan($scope, $module, LifecycleAction::Disable);
                $status = $plans->inspect($scope, $plan->ref);
                $proof->check(array_any($status->blockers, static fn ($b) => $b->owner === 'axiomasoft/packman-panels' && $b->resourceKind === 'owner-inventory'),
                    'Unavailable inventory or original lease blocks the real plan');
                $proof->same(ModuleState::Enabled, $lifecycle->status(Inventory::MODULE)->state, 'Unproved inventory cannot advance Packman');
                $after = ['status' => $status];
            } finally {
                if ($mode === 'lease-off') {
                    $observer->statement('ALTER TABLE scenario_inaccessible_leases RENAME TO scenario_view_directory_leases');
                }
            }

            break;

        case 'remediate':
            $plans->plan($scope, $module, LifecycleAction::Disable);
            $ref = new LifecyclePlanRef(Installation::read('blockers')['ref']['id']);
            $status = $plans->inspect($scope, $ref);
            $blocker = array_find($status->blockers, static fn ($b) => $b->resourceKind === 'saved-view');
            $proof->check($blocker !== null, 'Current operator can inspect the exact saved reference');
            $after = Inventory::enter(Scenario::ACCOUNT_A, Scenario::W1, static function (DataScope $workspace) use ($blocker, $status, $proof): array {
                $repo = resolve(SavedViewRepository::class);
                $view = $repo->viewsFor(Views::context(), Views::scopes()[1])[0];
                $proof->same([$blocker->resourceId, get_object_vars($blocker->scope)], [$view->id, get_object_vars($workspace)], 'Remedy names the original reference and fenced scope');
                DB::table('scenario_view_remedies')->insert(['id' => $view->id, 'revision' => $view->revision,
                    'scope_hash' => hash('sha256', json_encode($workspace, JSON_THROW_ON_ERROR)), 'plan' => $status->ref->id]);
                $repo->delete(Views::context(), $view->id);
                $proof->same([], $repo->viewsFor(Views::context(), Views::scopes()[1]), 'Packman confirms saved view removal');
                $index = ExternalResources::catalogue();
                $task = resolve(ExternalResources::class)->remediate($workspace);
                $retired = ExternalResources::catalogue();
                $proof->same(null, $retired, 'The PostgreSQL catalog confirms retired invalid CIC');

                return ['task' => $task, 'retired' => $view->id, 'pg_index_before' => $index, 'pg_index_after' => $retired];
            });
            $lifecycle->disable(Inventory::MODULE, DisableReason::Manual);
            $proof->same(ModuleState::Disabled, $lifecycle->status(Inventory::MODULE)->state, 'Resolved obligations permit disable');

            break;

        case 'detach':
            $rows = DB::table('cas_equipment')->orderBy('id')->get()->all();

            try {
                $lifecycle->uninstall(Inventory::MODULE, keepData: true);
            } catch (LifecycleBlocked $failure) {
                $ref = new LifecyclePlanRef(resolve(DatabaseScopeControl::class)->moduleClosure($scope, $target));

                throw new RuntimeException(json_encode($plans->inspect($scope, $ref), JSON_THROW_ON_ERROR), previous: $failure);
            }
            $proof->same(ModuleState::Archived, $lifecycle->status(Inventory::MODULE)->state, 'Public detach archives the disabled module');
            $proof->same(json_encode($rows), json_encode(DB::table('cas_equipment')->orderBy('id')->get()->all()), 'Detach retains the exact owner data');
            $after = ['rows' => $rows];

            break;

        case 'purge-plan':
            $initial = $plans->plan($scope, $module, LifecycleAction::Purge);
            $control->table('scenario_deployment')->increment('retention_revision');
            FixtureLifecycleOperators::grant($scope, Inventory::MODULE, 'purge', $initial->ref, $initial->digest);
            deny($proof, LifecyclePlanConflict::class, static fn () => $plans->apply($scope, $initial->ref, $initial->digest));
            $plan = $plans->plan($scope, $module, LifecycleAction::Purge);
            $proof->check($plan->digest != $initial->digest, 'New retention revision requires fresh approval');
            deny($proof, LifecycleBlocked::class, static fn () => $lifecycle->uninstall(Inventory::MODULE, keepData: false));
            $wrong = new PlanDigest(hash('sha256', 'incorrect digest'));
            FixtureLifecycleOperators::grant($scope, Inventory::MODULE, 'purge', $plan->ref, $wrong);
            deny($proof, LifecyclePlanConflict::class, static fn () => $plans->apply($scope, $plan->ref, $wrong));
            deny($proof, AuthorizationException::class, static fn () => $plans->apply($scope, $plan->ref, $plan->digest));
            FixtureLifecycleOperators::grant($scope, Inventory::MODULE, 'purge', $plan->ref, $plan->digest);
            DB::beginTransaction();

            try {
                deny($proof, LifecycleBlocked::class, static fn () => $plans->apply($scope, $plan->ref, $plan->digest));
            } finally {
                DB::rollBack();
            }
            $proof->same(0, $control->table('scenario_cleanup_receipts')->count(), 'All refusal controls precede external cleanup');
            $live = Inventory::enter(Scenario::ACCOUNT_A, Scenario::W1, static fn (DataScope $s) => ExternalResources::observe(ExternalResources::target($s)));
            $proof->same(false, $live['owner_absent'], 'The credentialed owner still sees its live index after every pre-effect refusal');
            Installation::record('purge', $plan);
            $after = ['old' => $initial, 'fresh' => $plan];

            break;

        case 'network':
            $approved = Installation::read('purge');
            $ref = new LifecyclePlanRef($approved['ref']['id']);
            $digest = new PlanDigest($approved['digest']['value']);
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $host = getenv('MEILISEARCH_HOST');
            putenv('MEILISEARCH_HOST=http://'.$address);

            try {
                $status = $plans->apply($scope, $ref, $digest);
            } finally {
                putenv('MEILISEARCH_HOST='.$host);
            }
            $proof->same(LifecyclePhase::Blocked, $status->phase, 'Loss of backend connectivity keeps the plan resumable');
            $proof->same($ref->id, resolve(DatabaseScopeControl::class)->moduleClosure($scope, $target), 'Network failure retains the same durable fence');
            $proof->same([], $status->receipts, 'The unavailable backend cannot produce an absence receipt');
            $after = ['status' => $status];

            break;

        case 'purge':
        case 'release-crash':
        case 'resume':
            $approved = Installation::read('purge');
            $ref = new LifecyclePlanRef($approved['ref']['id']);
            $digest = new PlanDigest($approved['digest']['value']);
            app()->scoped(LifecycleConfirmation::class, static fn () => new LifecycleConfirmation($ref, $digest));

            if ($mode === 'purge') {
                DB::listen(static function (QueryExecuted $event) use ($proof, $observer, $control, $ref): void {
                    if (str_contains($event->sql, 'sys_lifecycle_plans') && in_array('packman-transition', $event->bindings, true)) {
                        $receipts = $control->table('scenario_cleanup_receipts')->get()->all();
                        $stored = $observer->table('sys_lifecycle_plans')->where('plan_id', $ref->id)->sole();
                        $proof->same(1, count($receipts), 'Independent cleanup receipt is committed before Packman transition');
                        $proof->same('packman-transition', $stored->state, 'Independent observer sees resumable transition phase');
                        $checkpoint = ['receipts' => $receipts, 'checkpoint' => $stored->receipts];
                        $proof->report($observer, [$ref->id], [], $checkpoint);
                        Scenario::barrier('before-transition', $checkpoint);
                    }
                });
            }

            if ($mode === 'release-crash') {
                DB::listen(static function (QueryExecuted $event) use ($proof, $observer, $control, $ref, $digest): void {
                    if (! str_contains($event->sql, 'from "scenario_view_directory_lock"') || ! str_contains($event->sql, 'for update')) {
                        return;
                    }
                    $receipt = $control->table('scenario_exclusion_releases')->where(['owner' => 'axiomasoft/packman-panels', 'plan' => $ref->id])->first();

                    if ($receipt === null) {
                        return;
                    }
                    $key = ['token' => $receipt->token, 'plan' => $ref->id, 'namespace' => $receipt->namespace];
                    $partial = ['control' => $control->table('scenario_exclusions')->where($key)->count(),
                        'tenant' => $observer->table('scenario_view_directory_leases')->where($key)->count(),
                        'tenant_receipt' => $observer->table('scenario_view_directory_releases')->where($key)->count()];
                    $proof->same(['control' => 0, 'tenant' => 1, 'tenant_receipt' => 0], $partial, 'First release committed independently while the tenant still holds the original token');
                    $stored = $observer->table('sys_lifecycle_plans')->where('plan_id', $ref->id)->sole();
                    $proof->same(['done', $digest->value], [$stored->state, $stored->digest], 'The public purge completed before physical exclusion release');
                    $checkpoint = ['scope' => Inventory::scope(), 'receipt' => $receipt, 'counts' => $partial, 'digest' => $stored->digest,
                        'plan_receipts' => $stored->receipts];
                    $proof->report($observer, [$ref->id, $receipt->token], [], $checkpoint);
                    Scenario::barrier('partial-lease-release', $checkpoint);
                });
            }

            if ($mode === 'resume') {
                $partial = Scenario::barrierRead('partial-lease-release');
                $receipt = $partial['receipt'];
                $key = ['namespace' => $receipt['namespace'], 'plan' => $ref->id, 'token' => $receipt['token']];
                $proof->same(1, $observer->table('scenario_view_directory_leases')->where($key)->count(), 'Process death preserved the original tenant half');
                $proof->same(1, $control->table('scenario_exclusion_releases')->where([...$key, 'owner' => $receipt['owner']])->count(), 'Independent control release receipt survived death');
                $blocked = deny($proof, QueryException::class, static fn () => $observer->statement('UPDATE wsp_workspaces SET id=id WHERE false'));
                $proof->check(str_contains($blocked, '55006'), 'The actual directory trigger is still fenced after the split release');
            }
            $lifecycle->uninstall(Inventory::MODULE, keepData: false);
            $proof->same('resume', $mode, 'First process was killed before the Packman transition');
            $status = $plans->inspect($scope, $ref);
            $proof->same(LifecyclePhase::Done, $status->phase, 'Replay completed the original fenced plan');
            $proof->same(ModuleState::Purged, $lifecycle->status(Inventory::MODULE)->state, 'Packman reports purged');
            $proof->same(null, DB::scalar("select to_regclass('public.cas_equipment')"), 'Owner table was dropped');
            $checkpoint = Scenario::barrierRead('before-transition');
            $proof->same($checkpoint['receipts'], json_decode(json_encode($control->table('scenario_cleanup_receipts')->get()), true), 'Replay retained immutable prior-effect evidence');
            $receipt = json_decode($checkpoint['receipts'][0]['evidence'], true, flags: JSON_THROW_ON_ERROR);
            $drop = $control->table('scenario_drop_intents')->sole();
            $proof->same([$drop->uid, $drop->namespace, $drop->receipt, 404, 'index_not_found', true], [$receipt['task']['indexUid'], $receipt['namespace'],
                $receipt['receipt'], $receipt['http'], $receipt['code'], $receipt['owner_absent']], 'Replay retained one exact acknowledged Meili task and credentialed absence');
            $proof->same(json_encode($status->receipts), json_encode($plans->apply($scope, $ref, $digest)->receipts), 'Done replay returns the original evidence');
            FixtureLifecycleOperators::revoke($scope, Inventory::MODULE, 'repair');
            deny($proof, AuthorizationException::class, static fn () => Artisan::call('modules:repair', ['module' => Inventory::MODULE]));
            $proof->same(ModuleState::Purged, $lifecycle->status(Inventory::MODULE)->state, 'A verified operator without a repair grant cannot use the CLI');
            FixtureLifecycleOperators::grant($scope, Inventory::MODULE, 'repair');
            $proof->same(0, Artisan::call('modules:repair', ['module' => Inventory::MODULE]), 'Installed repair CLI uses the current account grant');
            $proof->same(ModuleState::Available, $lifecycle->status(Inventory::MODULE)->state, 'Repair returns availability');
            $lifecycle->enable(Inventory::MODULE);
            $proof->same(0, DB::table('cas_equipment')->count(), 'Re-enabled schema set contains no prior records');
            $proof->same(ModuleState::Enabled, $lifecycle->status(Inventory::MODULE)->state, 'Clean module is enabled');
            $foreign = Inventory::enter(Scenario::ACCOUNT_B, Scenario::W2, static fn (DataScope $s) => ExternalResources::observe(ExternalResources::target($s)));
            $proof->same(200, $foreign['http'], 'Replay leaves the independently owned foreign UID live');
            $proof->same(0, $observer->table('scenario_view_directory_leases')->where($key)->count(), 'Replay releases the surviving exact tenant half');
            $proof->same(1, $observer->table('scenario_view_directory_releases')->where($key)->count(), 'The local directory retains the matching completion receipt');
            $proof->check($observer->statement('UPDATE wsp_workspaces SET id=id WHERE false'), 'Directory trigger admits membership changes once every holder releases');
            $after = ['plan' => $status, 'foreign' => $foreign, 'partial_release' => $partial, 'released' => $key];

            break;

        case 'authority-off':
            app()->offsetUnset(DeploymentOwners::class);
            [$installed, $proposed] = Deployment::digests();
            $after = ['refusal' => deny($proof, LifecycleBlocked::class, static fn () => resolve(ProviderRemovalGate::class)->prepare(new ModuleRef(Inventory::PROVIDER), $installed, $proposed))];
            $proof->same(null, $control->table('scenario_deployment')->sole()->rollout, 'An absent fleet authority cannot certify an empty installation');

            break;

        case 'provider':
            [$installed, $proposed] = Deployment::digests();
            $gate = resolve(ProviderRemovalGate::class);
            $provider = new ModuleRef(Inventory::PROVIDER);
            $proof->check(! array_any(resolve(ModuleRegistry::class)->all(), static fn ($m) => $m->composerName === $provider->package), 'Provider has no module declaration');
            $control->table('scenario_unavailable')->insert(['account' => Scenario::ACCOUNT_B]);
            $why = deny($proof, RuntimeException::class, static fn () => $gate->prepare($provider, $installed, $proposed));
            $proof->same('Physical owner unavailable.', $why, 'Second owner unavailability blocks promotion');

            try {
                $control->table('scenario_binding_locations')->insert(['binding_id' => 'late-binding', 'account_id' => Scenario::ACCOUNT_B, 'role' => 'new']);

                throw new RuntimeException('Provisioning passed the rollout fence.');
            } catch (QueryException $error) {
                $proof->same('55006', $error->getCode(), 'Provisioning races serialize on the durable membership fence');
            }
            $control->table('scenario_unavailable')->where('account', Scenario::ACCOUNT_B)->delete();
            $approval = $gate->prepare($provider, $installed, $proposed);
            $proof->same(2, $control->table('scenario_owner_certificates')->count(), 'Every physical owner certified the composition');
            FixtureLifecycleOperators::revoke($scope, $provider->package, 'remove-provider');
            deny($proof, AuthorizationException::class, static fn () => $gate->consume($approval, $installed));
            FixtureLifecycleOperators::grant($scope, $provider->package, 'remove-provider', digest: $proposed);
            $second = Scenario::connection('b');
            $second->statement('ALTER TABLE scenario_view_directory_leases RENAME TO scenario_unreadable_lease');

            try {
                deny($proof, QueryException::class, static fn () => $gate->consume($approval, $installed));
                $proof->same($installed->value, $control->table('scenario_deployment')->sole()->composition, 'Losing access to one held physical lease prevents promotion');
            } finally {
                $second->statement('ALTER TABLE scenario_unreadable_lease RENAME TO scenario_view_directory_leases');
            }
            $partial = Scenario::barrierRead('partial-lease-release');
            $prior = $partial['receipt'];
            $replacement = Inventory::enter($partial['scope']['accountId'], $partial['scope']['workspaceId'], static function (DataScope $subject) use ($prior, $control, $proof): object {
                $views = resolve(SavedViewInventory::class);
                $current = $control->table('scenario_exclusions')->where(['namespace' => $prior['namespace'], 'target' => Inventory::PROVIDER])->sole();
                $newLease = new OwnerLease($prior['owner'], $current->namespace, $current->plan, $current->token);
                $proof->check($newLease->token !== $prior['token'] && $newLease->revision !== $prior['plan'], 'An independent deployment owns a fresh token in the same namespace');
                $views->release($subject, new OwnerLease($prior['owner'], $prior['namespace'], $prior['plan'], $prior['token']));
                $views->verify($subject, $newLease);
                $forged = new OwnerLease($prior['owner'], $prior['namespace'], $prior['plan'], (string) Str::uuid7());
                deny($proof, RuntimeException::class, static fn () => $views->release($subject, $forged));
                $views->verify($subject, $newLease);
                $proof->same(1, DB::table('scenario_view_directory_leases')->where('token', $current->token)->count(), 'Replayed and unknown tokens leave the replacement tenant holder intact');

                return $current;
            });
            $gate->consume($approval, $installed);
            $proof->same($proposed->value, $control->table('scenario_deployment')->sole()->composition, 'Promotion CAS committed under the original fence');
            $after = ['approval' => $approval, 'certificates' => $control->table('scenario_owner_certificates')->get(), 'replacement' => $replacement];

            break;

        case 'reconcile':
            $lost = Scenario::barrierRead('abandoned-write');
            $plan = $plans->plan($scope, $module, LifecycleAction::Disable);
            $status = $plans->inspect($scope, $plan->ref);
            $proof->check(in_array($lost['ticket'], array_column($status->blockers, 'resourceId'), true), 'Process death alone cannot settle the original writer ticket');
            $views = Inventory::enter(Scenario::ACCOUNT_A, Scenario::W1, static fn () => resolve(SavedViewRepository::class)->viewsFor(Views::context(), Views::scopes()[1]));
            $proof->same([], $views, 'Killed outer transaction left no durable view');
            $outcome = $observer->scalar('SELECT pg_xact_status(?::xid8)', [$lost['xid']]);
            $proof->same('aborted', $outcome, 'PostgreSQL confirms rollback of the exact killed transaction');
            $original = $lost['original'];
            $ticket = new AdmissionTicket($original['id'], new DataScope(...$original['scope']), $original['controlRevision'],
                new OperationKey(...$original['key']), AdmissionKind::from($original['kind']));
            resolve(ScopeAdmission::class)->settle($ticket, new AttemptEvidence('rolled-back', 'postgres:'.$lost['backend']['database'].'/xid:'.$lost['xid']));
            $proof->same([], resolve(DatabaseScopeControl::class)->outstandingReferences($scope, $target), 'Verified rollback settles the original ticket');
            $after = ['lost' => $lost, 'status' => $status, 'postgres_outcome' => $outcome];

            break;

        default:
            throw new RuntimeException('Unknown lifecycle checkpoint.');
    }
    $proof->report($observer, [$scope, Inventory::MODULE], $before, $after);
});
