<?php

declare(strict_types=1);

use App\BulkTransfer\BulkDeclaration;
use App\BulkTransfer\FixtureSetup;
use App\BulkTransfer\InputIdentity;
use App\BulkTransfer\Runtime;
use App\ProductProof\EquipmentTaskLink;
use App\Scenario\FixtureLifecycleOperators;
use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use Composer\InstalledVersions;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\SetField;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\Recurrence\Data\DateStart;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Scheduling\Data\WakeupWindow;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Data\SearchPage;
use CoreX\Search\Query\Data\SearchQuery;
use Equipment\Registry\DeclaredEquipmentFields;
use Equipment\Registry\Equipment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

const SERIES = 'installed-equipment-maintenance';
const SCHEDULE = 'installed-equipment-schedule';
const PACKAGES = ['equipment/registry', 'axiomasoft/corex-tasks', 'axiomasoft/corex-recurring-tasks', 'axiomasoft/corex-scheduler'];

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$mode = $argv[1] ?? throw new LogicException('Missing equipment phase.');
$db = Runtime::tenant();
$before = FixtureSetup::verifyTargets();
$after = ['phase' => $mode, 'php' => PHP_VERSION];
$refs = [];
$record = new RecordRef(Equipment::Handle, InputIdentity::FIRST);
$serial = DeclaredEquipmentFields::Serial;

switch ($mode) {
    case 'setup':
        FixtureSetup::install('product');
        $proof->same('asset_key', (new Equipment)->getKeyName(), 'Installed Equipment uses its actual string primary key.');
        $proof->check(InstalledVersions::isInstalled('axiomasoft/corex-search-custom-fields'), 'The effective search-to-fields bridge is installed.');
        $db->getSchemaBuilder()->create('installed_equipment_series', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('asset_key');
        });
        $db->getSchemaBuilder()->create('installed_equipment_task_receipts', static function (Blueprint $table): void {
            $table->uuid('task_id')->primary();
            $table->string('series_id');
            $table->uuid('workspace_id');
            $table->jsonb('receipt');
            $table->char('digest', 64);
            $table->string('operation_key');
        });
        Runtime::scoped(static function (DataScope $scope) use ($proof, $record): void {
            foreach ([...PACKAGES, BulkDeclaration::MODULE] as $package) {
                FixtureLifecycleOperators::grant($scope, $package, 'inspect');
                FixtureLifecycleOperators::grant($scope, $package, 'enable');
                resolve(ModuleLifecycle::class)->enable($package, cascade: true);
                $proof->check(resolve(ModuleRegistry::class)->isActive($package, resolve(TenantContextResolver::class)->current()), 'Actual module active '.$package);
            }
            Equipment::query()->forceCreate(['asset_key' => $record->id, 'name' => 'Registry compressor']);
        });
        $db->table('installed_equipment_series')->insert(['id' => SERIES, 'asset_key' => $record->id]);
        foreach (['read', 'search'] as $use) {
            foreach (['record', 'title'] as $field) {
                $db->table('scenario_field_grants')->insertOrIgnore([
                    'principal_id' => Scenario::USER_A, 'workspace_id' => Scenario::W1, 'entity' => $record->entity,
                    'record_id' => $record->id, 'field_id' => $field, 'field_use' => $use,
                ]);
            }
        }
        $set = RecurrenceSet::create(SERIES, 'revision-1', new DateStart('DATE', '2026-11-20', null), 'FREQ=DAILY;COUNT=1');
        $scope = Runtime::scoped(static fn (DataScope $scope): DataScope => $scope);
        $db->table('corex_task_series')->insert([
            'id' => (string) Str::uuid7(), 'series_key' => $set->seriesId, 'immutable_revision' => $set->immutableRevision,
            'definition_hash' => $set->definitionHash, 'definition' => json_encode($set, JSON_THROW_ON_ERROR),
            'template' => json_encode(['workspaceId' => Scenario::W1, 'title' => 'Registry compressor maintenance', 'authorId' => Scenario::USER_A], JSON_THROW_ON_ERROR),
            'actor' => json_encode(['type' => 'user', 'id' => Scenario::USER_A], JSON_THROW_ON_ERROR),
            'context_hash' => hash('sha256', Scenario::W1), 'restore_epoch' => $scope->restoreEpoch,
        ]);
        $db->table('corex_task_series_state')->insert([
            'series_key' => $set->seriesId, 'active_revision' => $set->immutableRevision, 'restore_epoch' => $scope->restoreEpoch,
        ]);
        $proof->same(0, $db->table('tsk_tasks')->count(), 'Setup seeds no emitted task.');
        $proof->same(0, $db->table('corex_task_occurrences')->count(), 'Setup seeds no occurrence.');
        $after['profile_openapi'] = Runtime::scoped(static fn (): array => Runtime::http('GET', '/api/v2/openapi.json'));
        $proof->same(200, $after['profile_openapi']['status'], 'The equipment product profile publishes its own mounted OpenAPI document.');
        $proof->same('equipment.bulk.transfer', $after['profile_openapi']['body']['paths']['/equipment/bulk-transfer']['post']['operationId'], 'The product document includes the installed equipment owner declaration.');

        break;

    case 'schedule':
        $series = $db->table('corex_task_series')->where('series_key', SERIES)->sole();
        [$scheduled, $replayed, $field, $queued] = Runtime::scoped(static function (DataScope $scope) use ($series, $record, $serial): array {
            $now = resolve(ScenarioClock::class)->now();
            $request = new WakeupRequest(
                scope: $scope, kind: 'task-recurrence.materialize', handlerVersion: 'task-materialization/1',
                requestKey: SCHEDULE, fireAt: $now, window: new WakeupWindow($now->modify('-1 minute'), $now, 'custom'),
                payload: JsonDocuments::capture([
                    'seriesId' => $series->series_key, 'immutableRevision' => $series->immutable_revision,
                    'definitionHash' => $series->definition_hash, 'start' => '2026-11-20', 'end' => '2026-11-21',
                    'aheadDays' => 1, 'pageLimit' => 10, 'maxSeconds' => 5,
                ]),
            );
            $scheduler = resolve(WakeupScheduler::class);
            $scheduled = $scheduler->schedule($request);
            $replayed = $scheduler->schedule($request);
            $values = resolve(FieldValues::class);
            $prior = $values->read($scope, $record, new FieldSelection([]));
            $patch = new FieldPatch([new SetField(new FieldId($serial), FieldInput::from('EQ-101'))], $prior->schemaRevision, $prior->valueRevision);
            $field = $values->update($scope, $record, $patch, $patch->token($scope, $record, 'equipment-installed-serial'));

            return [$scheduled, $replayed, $field, Runtime::submit('import', 'equipment-product-transfer')];
        });
        $proof->same('created', $scheduled->status, 'Public Scheduler accepted the due work.');
        $proof->same('existing', $replayed->status, 'Exact schedule replay reused the due work.');
        $proof->same($scheduled->id, $replayed->id, 'Duplicate schedule retains the original request identity.');
        $proof->same(202, $queued['status'], 'Real transfer admitted before a fresh worker. '.json_encode($queued, JSON_THROW_ON_ERROR));
        $proof->same($queued['location'], $queued['body']['status_url'], 'Transfer accepted URL is the owner status URL.');
        $proof->same(0, $db->table('corex_task_occurrences')->count(), 'Scheduling process has not produced a task occurrence.');
        $refs = ['wakeup' => $scheduled->id, 'operation' => $queued['body']['operation_id']];
        $after += ['scheduled' => $scheduled, 'replayed' => $replayed, 'field_write' => $field, 'transfer_acceptance' => $queued];

        break;

    case 'scheduler-worker':
        $before['wakeup'] = wakeup($db);
        $proof->same(0, $db->table('installed_equipment_task_receipts')->count(), 'No host receipt precedes the worker.');
        $exit = Runtime::scoped(static fn (): int => Artisan::call('corex:scheduler:sweep', ['--limit' => 1]));
        $proof->same(0, $exit, 'Registered Scheduler worker succeeds.');
        $done = wakeup($db);
        $proof->same('completed', $done['status'], 'The durable scheduled request is completed.');
        $joined = equipmentTask($db);
        $proof->same($joined['link']['task_id'], json_decode($joined['occurrence']['task_ref'], true, flags: JSON_THROW_ON_ERROR)['id'], 'Committed occurrence uses the returned public task ref.');
        $proof->same($joined['link']['task_id'], $joined['task']['id'], 'The task physically exists on the owner connection.');
        $proof->same($record->id, $joined['series']['asset_key'], 'Receipt links the task via the admitted equipment series.');
        $after += ['command_exit' => $exit, 'wakeup' => $done, 'join' => $joined];

        break;

    case 'bulk-worker':
        $job = Runtime::bulk('import');
        $before['bulk'] = Runtime::snapshot($job);
        for ($step = 0; $step < 4 && Runtime::bulk('import')->status !== 'completed'; $step++) {
            $proof->same(0, Runtime::work($step), 'Registered Bulk worker succeeds.');
        }
        $completed = Runtime::bulk('import');
        $proof->same('completed', $completed->status, 'Real import completed with durable owner results.');
        $sink = Scenario::connection('sink');
        $effect = (array) $sink->table('bulk_sink_equipment')->sole();
        $proof->same('EQ-001', $effect['serial'], 'Independent sink has the actual committed imported serial.');
        $after += ['bulk' => Runtime::snapshot($completed), 'transfer' => Runtime::transfer($completed->id),
            'sink' => ['connection' => Scenario::describe($sink), 'effect' => $effect, 'receipt' => (array) $sink->table('bulk_sink_receipts')->sole()]];

        break;

    case 'read':
        $join = equipmentTask($db);
        $after['owner'] = Runtime::scoped(static function (DataScope $scope) use ($proof, $record, $join, $serial): array {
            $task = readEquipmentTask($scope, $join['link']['task_id']);
            $fields = resolve(FieldValues::class)->read($scope, $record, new FieldSelection([$serial]));
            $page = findEquipment($scope, $record);
            $transfer = resolve(TransferResultReader::class)->result($scope, new TransferRef(Runtime::transfer(Runtime::id('import'))));
            $proof->same($join['task']['id'], $task['id'], 'Fresh public Task relationship resolves the persisted task ID.');
            $proof->check(isset($fields->values[$serial]), 'Fresh owner FieldValues read releases the explicitly granted field.');
            $proof->same([$record->id], array_map(static fn ($hit): string => $hit->record->id, $page->hits), 'Actual PostgreSQL search returns one installed equipment ref.');
            $proof->same(Runtime::transfer(Runtime::id('import')), $transfer['id'], 'Fresh public transfer read joins the actual Bulk operation.');

            return compact('task', 'fields', 'page', 'transfer');
        });
        $after['join'] = $join;

        break;

    case 'revoke':
        $before['grants'] = $db->table('scenario_field_grants')->where(['workspace_id' => Scenario::W1, 'principal_id' => Scenario::USER_A, 'record_id' => $record->id])->get()->all();
        $db->table('scenario_field_grants')->where(['workspace_id' => Scenario::W1, 'principal_id' => Scenario::USER_A, 'entity' => $record->entity])
            ->whereIn('field_use', ['read', 'search'])->delete();
        $db->table('scenario_field_grants')->where(['workspace_id' => Scenario::W1, 'principal_id' => Scenario::USER_A, 'entity' => 'import-export.transfer', 'field_use' => 'read'])->delete();
        $after['grants'] = $db->table('scenario_field_grants')->where(['workspace_id' => Scenario::W1, 'principal_id' => Scenario::USER_A, 'record_id' => $record->id])->get()->all();
        $proof->check(count($before['grants']) > count($after['grants']), 'Actual current rights changed.');

        break;

    case 'denial':
        $join = equipmentTask($db);
        foreach ([Scenario::W1, Scenario::W2] as $workspace) {
            $after['denials'][$workspace] = Runtime::scoped(static function (DataScope $scope) use ($proof, $join, $record, $serial): array {
                $task = $field = $search = false;

                try {
                    readEquipmentTask($scope, $join['link']['task_id']);
                } catch (AuthorizationException) {
                    $task = true;
                }

                try {
                    $fields = resolve(FieldValues::class)->read($scope, $record, new FieldSelection([$serial]));
                    $field = ! isset($fields->values[$serial]);
                } catch (FieldFailure) {
                    $field = true;
                }

                try {
                    $search = findEquipment($scope, $record)->hits === [];
                } catch (SearchFeatureUnavailable) {
                    $search = true;
                }
                $transfer = Runtime::http('GET', Runtime::BASE.'/'.Runtime::id('import').'/result');
                $proof->same([true, true, true, 404], [$task, $field, $search, $transfer['status']], 'Current rights refuse known product owner references.');

                return compact('task', 'field', 'search', 'transfer');
            }, $workspace);
        }
        $after['join'] = $join;

        break;

    default:
        throw new LogicException('Unknown equipment phase '.$mode);
}

$proof->report($db, $refs, $before, $after);

function wakeup(Connection $database): array
{
    return (array) $database->table('sys_wakeup_requests')->where('request_key', SCHEDULE)->sole();
}

function equipmentTask(Connection $database): array
{
    return [
        'task' => (array) $database->table('tsk_tasks')->sole(),
        'occurrence' => (array) $database->table('corex_task_occurrences')->sole(),
        'link' => (array) $database->table('installed_equipment_task_receipts')->sole(),
        'series' => (array) $database->table('installed_equipment_series')->sole(),
    ];
}

function readEquipmentTask(DataScope $scope, string $task): array
{
    $tenant = resolve(TenantContextResolver::class)->current();
    $link = EquipmentTaskLink::query()->whereKey($task)->firstOrFail();

    if ($link->getAttribute('workspace_id') !== $scope->workspaceId
        || ! resolve(ModuleRegistry::class)->isActive('axiomasoft/corex-tasks', $tenant)
        || ! resolve(DataScopeAuthorizer::class)->authorize(scope: $scope, record: new RecordRef('task-series', $link->getAttribute('series_id')), fields: new FieldSelection(['title']), use: FieldUse::Read)->allowed) {
        throw new AuthorizationException('The known task is unavailable in the current equipment scope.');
    }

    return $link->admittedTask();
}

function findEquipment(DataScope $scope, RecordRef $record): SearchPage
{
    return resolve(Search::class)->query($scope, new SearchQuery(
        entity: new EntityHandle($record->entity), text: 'compressor', searchFields: ['title'], fields: ['title'],
    ));
}
