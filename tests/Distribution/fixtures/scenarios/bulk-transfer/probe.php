<?php

declare(strict_types=1);

use App\BulkTransfer\BulkDeclaration;
use App\BulkTransfer\CrashRecovery;
use App\BulkTransfer\FixtureSetup;
use App\BulkTransfer\InputIdentity;
use App\BulkTransfer\Journal;
use App\BulkTransfer\PrivateStorage;
use App\BulkTransfer\Runtime;
use App\Scenario\FixtureLifecycleOperators;
use App\Scenario\PhysicalMarker;
use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use Composer\InstalledVersions;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Exceptions\ImportUnavailable;
use CoreX\ImportExport\Exceptions\SessionUnavailable;
use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Bulk\Contracts\BulkOwner;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use Equipment\Registry\DeclaredEquipmentFields;
use Equipment\Registry\Equipment;
use Equipment\Registry\TransferBulkAdapter;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

$mode = $argv[1] ?? throw new InvalidArgumentException('Expected a bulk-transfer probe phase.');
putenv('COREX_BULK_MODE='.($mode === 'work' ? $argv[2] : $mode));
require_once getcwd().'/vendor/autoload.php';
Scenario::boot();
$test = new Scenario;
$db = Runtime::tenant();
$before = FixtureSetup::verifyTargets();
$refs = [];
$after = ['phase' => $mode, 'php' => PHP_VERSION];
$test->check(in_array(PHP_VERSION, ['8.4.27', '8.5.12'], true), 'Unexpected installed PHP version.');

switch ($mode) {
    case 'setup':
        FixtureSetup::install();
        Runtime::scoped(static function (DataScope $scope) use ($test): void {
            $lifecycle = app(ModuleLifecycle::class);
            $packages = [BulkDeclaration::MODULE];

            if (getenv('COREX_SCENARIO_VARIANT') === 'equipment') {
                $packages[] = 'equipment/registry';
            }
            foreach ($packages as $package) {
                FixtureLifecycleOperators::grant($scope, $package, 'enable');
                FixtureLifecycleOperators::grant($scope, $package, 'inspect');
                $lifecycle->enable($package, cascade: true);
                $test->check(app(ModuleRegistry::class)->isActive($package, app(TenantContextResolver::class)->current()),
                    'Installed host declaration was not activated through ModuleLifecycle.');
            }

            if (getenv('COREX_SCENARIO_VARIANT') === 'equipment') {
                $test->check(app(BulkOwner::class) instanceof TransferBulkAdapter, 'Equipment owner port must resolve to the installed adapter.');
                $test->same(Equipment::Handle, InputIdentity::ENTITY, 'Fixture export must use the actual declared equipment entity.');
                $test->same(DeclaredEquipmentFields::Serial, InputIdentity::SERIAL, 'Owner grants and codec must use the actual stable equipment field ID.');
            }
        }, null);
        $test->same(0, $db->table('sys_api_bulk_operations')->count(), 'Fixture starts with no bulk admissions.');
        $test->same([], Runtime::budget(), 'Fixture starts with no dedicated budget reservations.');

        break;

    case 'control':
        $hasOwner = getenv('COREX_SCENARIO_VARIANT') === 'without-adapter';
        $test->same($hasOwner, InstalledVersions::isInstalled('axiomasoft/corex-import-export'), 'Composer owner presence differs from this independent control.');
        $test->same($hasOwner, interface_exists(TransferResultReader::class), 'The installed autoloader leaked an unavailable optional package.');
        $test->check(! app()->bound(BulkExecutor::class) && ! app()->bound(BulkOwner::class), 'Control unexpectedly supplies a host transfer adapter.');
        $rejected = [];
        foreach (['import', 'export'] as $kind) {
            $rejected[$kind] = Runtime::scoped(static fn () => Runtime::submit($kind, 'fixture-control-'.$kind));
            $test->same(503, $rejected[$kind]['status'], 'Missing host capability should refuse '.$kind.'.');
            $test->same('operations.capability_unavailable', $rejected[$kind]['body']['code'] ?? null, 'Missing capability must use its typed code.');
        }
        $test->same(0, $db->table('sys_api_bulk_operations')->count(), 'Capability check follows an unexpected Bulk write.');
        $test->same(0, $db->table('sys_api_idempotency')->count(), 'Capability check follows an unexpected idempotency write.');
        $test->same([], Runtime::budget(), 'Capability check follows an unexpected independent budget write.');

        if ($hasOwner) {
            $test->same(0, $db->table('corex_transfer_jobs')->count(), 'Capability check follows an unexpected transfer write.');
        }
        $ping = Runtime::scoped(static fn () => Runtime::http('GET', '/api/v2/fixture-ping'));
        $test->same(200, $ping['status'], 'Ordinary declared Operations query must remain usable.');
        $test->check(str_contains(json_encode($ping['body'], JSON_THROW_ON_ERROR), 'ordinary-operation'), 'The ordinary operation did not invoke its installed handler.');
        $after += ['rejected' => $rejected, 'ordinary' => $ping];

        break;

    case 'submit-a':
    case 'submit-b':
        $response = $mode === 'submit-a'
            ? Runtime::scoped(static fn () => $db->transaction(static function () use ($db): array {
                $response = Runtime::submit('import', 'fixture-import');
                Scenario::barrier('bulk-outer', [
                    ...Scenario::describe($db), 'response' => $response, 'levels' => $db->transactionLevel(), 'budget' => Runtime::budget(),
                ]);

                return $response;
            }))
            : Runtime::scoped(static fn () => Runtime::submit('import', 'fixture-import'));
        $id = accepted($test, $response);

        if ($mode === 'submit-b') {
            $test->same(Scenario::barrierRead('bulk-outer')['response']['location'], $response['location'], 'Concurrent same-key replay must return the committed Location.');
        }
        $refs['operation'] = $id;
        $after['response'] = $response;

        break;

    case 'observe-outer':
        $owner = Scenario::barrierRead('bulk-outer');
        $peer = Scenario::barrierRead('bulk-concurrent');
        $test->same(202, $owner['response']['status'], 'Nested HTTP admission did not succeed.');
        $test->same(1, $owner['levels'], 'The caller must still own the outer transaction.');
        $test->check($owner['session'] !== $peer['session'] && $owner['session'] !== Scenario::describe($db)['session'], 'HTTP contenders and observer must use independent PostgreSQL sessions.');
        $deadline = hrtime(true) + 10_000_000_000;
        do {
            $wait = $db->selectOne('SELECT pg_blocking_pids(?) AS blockers', [$peer['session']])->blockers;

            if ($wait !== '{}') {
                break;
            }
            usleep(25_000);
        } while (hrtime(true) < $deadline);
        $test->same('{'.$owner['session'].'}', $wait, 'Concurrent request did not wait on the first PostgreSQL transaction.');
        $test->same(0, $db->table('sys_api_bulk_operations')->count(), 'Bulk admission escaped its caller transaction.');
        $test->same(0, $db->table('sys_api_idempotency')->count(), 'Idempotency receipt escaped the outer transaction.');
        $test->same(0, $db->table('corex_transfer_jobs')->count(), 'Submitting a reference unexpectedly executed the owner.');
        $budget = Runtime::budget();
        $test->same(['held'], array_column($budget, 'state'), 'Independent budget must remain held until tenant commit.');
        $tickets = targets('local-write', 'outstanding');
        $test->check(hasTargets($tickets, ['equipment/registry', 'axiomasoft/corex-operations', 'axiomasoft/corex-import-export']), 'Module memberships must remain enlisted while the outer transaction is open.');
        $after += ['budget' => $budget, 'tickets' => $tickets, 'owner' => $owner, 'peer' => $peer];

        break;

    case 'import-conflict':
        $bulk = Runtime::bulk('import');
        $budget = Runtime::budget();
        $test->same(1, $db->table('sys_api_bulk_operations')->count(), 'Concurrent same-key requests created more than one Bulk operation.');
        $test->same(1, $db->table('sys_api_idempotency')->count(), 'Concurrent same-key requests created more than one owner idempotency result.');
        $admitted = Scenario::barrierRead('bulk-outer')['budget'];
        $ownerReceipt = json_decode($db->table('sys_api_idempotency')->sole()->outcome_json, true, flags: JSON_THROW_ON_ERROR)['budget'];
        $test->same(['settled'], array_column($budget, 'state'), 'The entire dedicated ledger contains one settled reservation.');
        $test->same([$ownerReceipt['reservation_id']], array_column($budget, 'id'), 'The settlement belongs to the original tenant receipt.');
        $test->same(array_column($admitted, 'id'), array_column($budget, 'id'), 'Concurrent replay uses the original hold.');
        $operation = array_find(app(OperationRegistry::class)->all('v2'),
            static fn ($declaration): bool => $declaration->id === 'equipment.bulk.transfer');
        $test->same([1_000_000 * ($operation->cost['base'] ?? 1)], array_column($budget, 'charged'), 'The business reservation is charged once.');
        $test->check(! hasTargets(targets('local-write', 'outstanding'), ['equipment/registry']), 'Lifecycle admission was not settled after outer commit.');
        $changed = Runtime::scoped(static fn () => Runtime::submit('import', 'fixture-import', 'equipment-alternate'));
        $test->same(409, $changed['status'], 'Same key with changed semantic input must conflict.');
        $test->same('operations.idempotency_conflict', $changed['body']['code'] ?? null, 'Semantic mismatch must return the idempotency conflict code.');
        $test->same($budget, Runtime::budget(), 'Changed input reserved or settled budget.');
        $test->same(0, $db->table('corex_transfer_jobs')->count(), 'Conflict started transfer work.');
        $test->same([1, 4], [(int) $bulk->row_limit, (int) $bulk->time_limit_seconds], 'Durable admission lost requested stricter bounds.');
        Journal::record('original-business-reservation', $budget[0]);
        $after += ['budget' => $budget, 'tenant_receipt' => $ownerReceipt, 'changed' => $changed];

        break;

    case 'work':
        $worker = $argv[2];
        $kind = str_starts_with($worker, 'import') ? 'import' : 'export';
        $workBefore = Runtime::snapshot(Runtime::bulk($kind));
        $exit = Runtime::work((int) $argv[3]);
        $test->same(0, $exit, 'Installed bounded worker failed.');
        $workAfter = Runtime::snapshot(Runtime::bulk($kind));
        $dispatch = eventsFor('bulk-dispatched', Runtime::id($kind));
        $test->check($dispatch !== [], 'The installed worker never invoked the host BulkExecutor.');
        $latest = array_find($dispatch, static fn (array $row): bool => $row['mode'] === $worker && $row['pid'] === getmypid());
        $test->check($latest !== null, 'The current worker did not dispatch its step.');

        if ($worker === 'import-unknown') {
            $test->same($workBefore['checkpoint'], $workAfter['checkpoint'], 'Unknown sink outcome advanced the Bulk checkpoint.');
            $test->check(in_array($workAfter['status'], ['pending', 'running'], true), 'Unknown sink outcome was terminally misclassified.');
            $test->same(['code' => 'bulk_reconciliation_required'], $workAfter['error'], 'Unknown outcome lost its typed reconciliation code.');
            $test->same('0', (string) transferJob('import')->checkpoint_seq, 'Unknown sink outcome advanced the owner checkpoint.');
            $test->same(1, sink()->table('bulk_sink_journal')->where('action', 'apply')->count(), 'Unknown outcome caused a blind resend.');
        }

        if ($worker === 'import-recover') {
            $test->same(1, (int) transferJob('import')->checkpoint_seq, 'Lost owner receipt acknowledgment duplicated the owner checkpoint.');
            $test->same(1, $workAfter['checkpoint']['sequence'], 'Replay should acknowledge exactly one original step.');
            $test->same($latest['token'], Scenario::barrierRead('import-owner-committed')['token'], 'Replay changed the step token.');
        }

        if ($worker === 'import-final') {
            $test->same('completed', $workAfter['status'], 'The second bounded row did not complete the import.');
            $test->same(2, $workAfter['checkpoint']['sequence'], 'The final row did not advance exactly one checkpoint.');
        }

        if (in_array($worker, ['export-recover', 'export-reclaim'], true)) {
            $test->same('completed', $workAfter['status'], 'Export acknowledgment replay did not complete Bulk.');
            $barrier = $worker === 'export-reclaim' ? 'export-stale-ack' : 'export-published';
            $test->same($latest['token'], Scenario::barrierRead($barrier)['token'], 'Export reclaim changed the admitted token.');
            Journal::record('export-winner', $workAfter);
        }

        if ($worker === 'export-stale') {
            $winner = array_last(Journal::events('export-winner'));
            $test->same($workAfter, array_intersect_key($winner, $workAfter), 'Superseded worker changed Bulk after the replacement committed.');
        }
        $after += ['exit' => $exit, 'dispatch' => $latest, 'bulk_before' => $workBefore, 'bulk_after' => $workAfter];

        break;

    case 'observe-sink':
        $effect = Scenario::barrierRead('sink-committed');
        $test->same('applied', $effect['status'], 'External target did not commit the expected typed effect.');
        $test->same(1, sink()->table('bulk_sink_equipment')->count(), 'Independent sink did not commit exactly one equipment row.');
        $test->same('EQ-001', sink()->table('bulk_sink_equipment')->value('serial'), 'Wrong independent effect payload.');
        $receipt = sink()->table('bulk_sink_journal')->where('action', 'apply')->sole();
        $test->same($effect['provider_pid'], (int) $receipt->provider_pid, 'Independent provider process identity differs.');
        $test->check((int) $receipt->provider_pid !== getmypid() && (int) $receipt->backend_pid !== Scenario::describe($db)['session'], 'Sink commit shared the worker process or tenant session.');
        $test->same($effect['receipt'], sink()->table('bulk_sink_receipts')->value('receipt'), 'Sink effect and receipt missed the same durable boundary.');
        $test->same('0', (string) transferJob('import')->checkpoint_seq, 'Owner checkpoint advanced ahead of the committed sink observation.');
        $test->same([], Runtime::snapshot(Runtime::bulk('import'))['checkpoint'], 'Bulk checkpoint was committed before the sink acknowledgment.');
        $step = $db->table('corex_transfer_steps')->where('job_id', transferJob('import')->id)->sole();
        $dispatch = array_last(Journal::events('bulk-dispatched'));
        $test->same($dispatch['token'], $step->step_key, 'A durable owner step with the exact token must predate the provider effect.');
        $tickets = targets('provider-send', 'outstanding');
        $test->check(hasTargets($tickets, ['equipment/registry', 'axiomasoft/corex-import-export']), 'Independent send lacks durable lifecycle target membership.');
        $after += ['sink' => $effect, 'owner_checkpoint' => '0', 'token' => $step->step_key, 'tickets' => $tickets];

        break;

    case 'observe-owner':
        $owner = Scenario::barrierRead('import-owner-committed');
        $job = transferJob('import');
        $test->same('1', (string) $job->checkpoint_seq, 'Owner step was not independently committed before Bulk acknowledgment.');
        $test->same([], Runtime::snapshot(Runtime::bulk('import'))['checkpoint'], 'Bulk acknowledged before the owner-return barrier.');
        $step = $db->table('corex_transfer_steps')->where('job_id', $job->id)->sole();
        $test->same(Scenario::barrierRead('sink-committed')['key'], sink()->table('bulk_sink_receipts')->value('effect_key'), 'Independent receipt changed across recovery.');
        $test->same($owner['token'], $step->step_key, 'Recovered owner receipt changed the original step token.');
        $test->check(is_string($step->receipt) && json_decode($step->receipt, true, flags: JSON_THROW_ON_ERROR) !== [], 'Terminal owner step receipt did not commit.');
        $test->same(1, sink()->table('bulk_sink_journal')->where('action', 'apply')->count(), 'Owner recovery blindly resubmitted the already committed effect.');
        $lookups = sink()->table('bulk_sink_journal')->where('action', 'lookup')->orderBy('sequence')->pluck('outcome')->all();
        $test->check(in_array('unknown', $lookups, true) && array_last($lookups) === 'applied', 'Owner recovery did not reconcile unknown then committed receipt.');
        $journal = sink()->table('bulk_sink_journal')->orderBy('sequence')->get()->all();
        $after += ['job' => $job->id, 'receipt_sha256' => hash('sha256', $step->receipt), 'token' => $step->step_key, 'lookups' => $lookups, 'sink_journal' => $journal];
        Journal::record('verified-owner-receipt', ['token' => $step->step_key, 'sha256' => $after['receipt_sha256']]);

        break;

    case 'import-result':
        $id = Runtime::id('import');
        $job = transferJob('import');
        $expected = ['records' => [['ordinal' => '2', 'code' => 'invalid_serial', 'path' => '/serial']], 'next' => null];
        $priorBudget = array_intersect_key(array_last(Journal::events('original-business-reservation')), array_flip(['id', 'state', 'charged']));
        $actualBudget = Runtime::budget();
        $test->same([$priorBudget], $actualBudget, 'Independent worker retries preserve the complete original submission ledger before public reads.');
        $after['worker_budget'] = $actualBudget;
        $result = Runtime::scoped(static fn () => Runtime::http('GET', Runtime::BASE.'/'.$id.'/result'));
        $failures = Runtime::scoped(static fn () => Runtime::http('GET', Runtime::BASE.'/'.$id.'/failures?limit=1'));
        $test->same(200, $result['status'], 'Authorized import owner result unavailable through normal route.');
        $test->same($job->id, $result['body']['id'], 'Bulk result refers to a different transfer.');
        $test->same(1, $result['body']['stats']['applied'], 'Applied count duplicated after retries.');
        $test->same(1, $result['body']['stats']['invalid'], 'Failed row count duplicated after retries.');
        $test->same(200, $failures['status'], 'Typed import failure page unavailable.');
        $test->same($expected, $failures['body'], 'Second row did not retain its exact typed failure and path.');
        $test->check(str_contains($failures['cache'], 'private') && str_contains($failures['cache'], 'no-store'), 'Failure page is publicly cacheable.');
        $direct = Runtime::scoped(static fn (DataScope $scope) => app(TransferResultReader::class)->failures($scope, new TransferRef($job->id), null, 1));
        $test->same($expected, $direct, 'Direct owner failure read differs from the Bulk projection.');
        $bulk = Runtime::bulk('import');
        foreach (['owner_result_ref', 'failure_page_ref'] as $field) {
            $ref = json_decode($bulk->{$field}, true, flags: JSON_THROW_ON_ERROR);
            $test->same($job->id, $ref['id'], 'Persisted operation linkage did not retain the same owner ref.');
        }
        $test->same(1, sink()->table('bulk_sink_equipment')->count(), 'Import produced duplicate target rows.');
        $reads = Journal::events('import-read');
        foreach ($reads as $read) {
            $test->same([1, 4], [$read['rowLimit'], $read['seconds']], 'Source did not receive the admitted stricter limits.');
            $test->same(1, count($read['ids']), 'Source processed more than the one-row bounded step.');
        }
        $dispatched = eventsFor('bulk-dispatched', $id);
        $test->same(1, count(array_unique(array_column(array_slice($dispatched, 0, 4), 'token'))), 'Recoveries changed the original admitted step token.');
        $test->check($dispatched[4]['token'] !== $dispatched[0]['token'], 'A truly new checkpoint reused its predecessor token.');
        $stepReceipts = $db->table('corex_transfer_steps')->where('job_id', $job->id)->get();
        $test->same(2, $stepReceipts->count(), 'Expected one durable terminal receipt per progressed step.');
        $original = array_last(Journal::events('verified-owner-receipt'));
        $sameStep = $stepReceipts->firstWhere('step_key', $original['token']);
        $test->same($original['sha256'], hash('sha256', $sameStep->receipt), 'Receipt changed after the same-token replay and a later owner step.');
        $refs = ['operation' => $id, 'owner' => $job->id];
        $after += ['result' => $result['body'], 'failures' => $failures['body'], 'dispatch' => $dispatched, 'reads' => $reads];

        break;

    case 'drift':
        $original = $db->table('bulk_fixture_revisions')->sole();
        $effects = sink()->table('bulk_sink_journal')->count();
        $ownerJobCount = $db->table('corex_transfer_jobs')->count();
        $observed = [];
        foreach (['schema_hash' => hash('sha256', 'equipment-schema/2'), 'codec' => 'equipment-ndjson/2', 'mapping' => 'equipment-mapping/2'] as $field => $changed) {
            $id = accepted($test, Runtime::scoped(static fn () => Runtime::submit('import', 'fixture-drift-'.$field)));
            $db->table('bulk_fixture_revisions')->update([$field => $changed]);

            try {
                $exit = Runtime::work(100 + count($observed) * 35);
                $observed[$field] = ['exit' => $exit, 'output' => Artisan::output()];
            } catch (Throwable $failure) {
                $observed[$field] = ['error' => $failure::class, 'message' => $failure->getMessage()];
            } finally {
                $db->table('bulk_fixture_revisions')->update([$field => $original->{$field}]);
            }
            $row = $db->table('sys_api_bulk_operations')->where('id', $id)->sole();
            $dispatches = eventsFor('bulk-dispatched', $id);
            $test->same(1, count($dispatches), 'Pinned '.$field.' drift did not reach the real worker preflight.');
            $refusal = array_last(eventsFor('owner-refused', $id));
            $test->same('operations.idempotency_contract_mismatch', $refusal['code'] ?? null, 'Pinned '.$field.' drift did not return its typed contract refusal.');
            $observed[$field]['refusal'] = $refusal;
            $snapshot = Runtime::snapshot($row);
            $test->same(['code' => 'contract_drift'], $snapshot['error'], 'Worker failed to record the deterministic refusal for the fresh admission.');
            $test->same([], $snapshot['checkpoint'], 'Pinned '.$field.' drift advanced the Bulk checkpoint.');
            $refs[$field] = $id;
            $test->same($ownerJobCount, $db->table('corex_transfer_jobs')->count(), 'Pinned '.$field.' drift created a new owner job.');
            $test->same($effects, sink()->table('bulk_sink_journal')->count(), 'Pinned '.$field.' drift reached the independent sink.');
        }
        $after['refusals'] = $observed;

        break;

    case 'export-submit':
    case 'stale-submit':
        $response = Runtime::scoped(static fn () => Runtime::submit('export', 'fixture-'.$mode));
        $refs['operation'] = accepted($test, $response);
        $after['response'] = $response;

        break;

    case 'observe-part':
        $part = Scenario::barrierRead('export-part-committed');
        $file = partPath($part['ref']);
        $test->same('private', $part['visibility'], 'The uploaded part was not private.');
        $test->same($part['sha256'], hash_file('sha256', $file), 'The first staged part checksum differs on independent readback.');
        $test->same($part['bytes'], filesize($file), 'The first staged part bytes were not fully committed.');
        $test->same(0600, fileperms($file) & 0777, 'The staged file is readable outside the private mode.');
        $job = transferJob('export');
        $test->check($job->state !== 'completed', 'Export was completed before the first part was acknowledged.');
        $test->same([], Runtime::snapshot(Runtime::bulk('export'))['checkpoint'], 'Bulk advanced before the first part was acknowledged.');
        $test->same(0, $db->table('corex_export_parts')->count(), 'A partially uploaded capture exposed an acknowledged part.');
        $test->check(hasTargets(targets('provider-send', 'outstanding'), ['axiomasoft/corex-import-export', 'equipment/registry']), 'Private upload must retain durable owner admission.');
        $result = Runtime::scoped(static fn () => Runtime::http('GET', Runtime::BASE.'/'.Runtime::id('export').'/result'));
        $test->same(409, $result['status'], 'A partial upload must not be externally ready.');
        $after += ['part' => $part, 'job_state' => $job->state, 'result' => $result];

        break;

    case 'write-snapshot':
        $exporter = Scenario::barrierRead('export-snapshot-open');
        $test->check(Scenario::describe($db)['session'] !== $exporter['session'], 'Contending export writer must use an independent session.');
        $test->same(1, $db->table('bulk_fixture_export')->where('ordinal', 2)->update(['serial' => 'EQ-999', 'revision' => 'v2']), 'Competing equipment update did not commit.');
        $test->same('EQ-999', $db->table('bulk_fixture_export')->where('ordinal', 2)->value('serial'), 'Competing equipment update is not visible on its owner connection.');

        break;

    case 'observe-publication':
        $job = transferJob('export');
        $manifest = $db->table('corex_export_manifests')->where('job_id', $job->id)->sole();
        $test->same('published', $manifest->state, 'The owner did not publish before the Bulk acknowledgment barrier.');
        $test->same('completed', $job->state, 'Published manifest was not committed with the owner result.');
        $test->same(2, $db->table('corex_export_parts')->where('manifest_id', $manifest->id)->count(), 'Snapshot did not publish exactly two parts.');
        $test->check(Runtime::bulk('export')->status !== 'completed', 'Bulk completed before the owner acknowledgment.');
        $first = Scenario::barrierRead('export-part-committed');
        $writes = Journal::events('private-put');
        $reads = Journal::events('private-read');
        $test->same(1, count(array_filter($writes, static fn (array $row): bool => $row['ref'] === $first['ref'])), 'Recovery rewrote the previously committed part.');
        $test->check(array_any($reads, static fn (array $row): bool => $row['ref'] === $first['ref'] && $row['sha256'] === $first['sha256']), 'Recovery did not checksum-read the exact committed part.');
        $snapshots = Journal::events('export-read');
        $lastTwo = array_slice($snapshots, -2);
        $test->same([1, 2], array_column($lastTwo, 'ordinal'), 'Resumed export did not capture the two expected rows.');
        $test->same('EQ-999', $db->table('bulk_fixture_export')->where('ordinal', 2)->value('serial'), 'The competing commit did not occur before the export finished.');
        $test->same(['EQ-101', 'EQ-102'], array_column($lastTwo, 'serial'), 'The coherent capture mixed rows across the concurrent commit.');
        $test->same(1, count(array_unique(array_column($lastTwo, 'snapshot'))), 'Multipart capture spanned different PostgreSQL snapshots.');
        $test->same([2, 2], array_column($lastTwo, 'rowLimit'), 'Owner did not receive the admitted capture bound.');
        $test->same([4, 4], array_column($lastTwo, 'seconds'), 'Owner did not receive the admitted runtime bound.');
        $after += ['manifest' => $manifest->id, 'parts_hash' => $manifest->parts_hash, 'snapshot' => $lastTwo];
        $db->table('bulk_fixture_export')->where('ordinal', 2)->update(['serial' => 'EQ-102', 'revision' => 'v1']);

        break;

    case 'export-result':
        $id = Runtime::id('export');
        verifyExport($test, $id, $after);
        $test->same(1, $db->table('corex_export_manifests')->count(), 'Lost publication acknowledgment created a second manifest.');
        $test->same(2, count(Journal::events('private-put')), 'Lost publication acknowledgment rewrote persisted private parts.');

        break;

    case 'export-schema-read':
        $id = Runtime::id('export');
        $transfer = new TransferRef(Runtime::transfer($id));
        $original = $db->table('bulk_fixture_revisions')->sole()->schema_hash;
        $snapshot = Runtime::snapshot(Runtime::bulk('export'));
        $privateReads = count(Journal::events('private-read'));
        $db->table('bulk_fixture_revisions')->update(['schema_hash' => hash('sha256', 'equipment-schema/2')]);

        try {
            $observed = Runtime::scoped(static function (DataScope $scope) use ($test, $id, $transfer): array {
                $record = new RecordRef(InputIdentity::ENTITY, InputIdentity::SECOND);
                $test->check(app(DataScopeAuthorizer::class)->authorize($scope, $record, new FieldSelection([InputIdentity::SERIAL]), FieldUse::Export)->allowed,
                    'Schema drift control requires the underlying current Export grant.');
                $reader = app(TransferResultReader::class);

                return [
                    'result' => Runtime::http('GET', Runtime::BASE.'/'.$id.'/result')['status'],
                    'failures' => Runtime::http('GET', Runtime::BASE.'/'.$id.'/failures')['status'],
                    'owner_result_denied' => ownerUnavailable(static fn () => $reader->result($scope, $transfer)),
                    'owner_failures_denied' => ownerUnavailable(static fn () => $reader->failures($scope, $transfer, null, 1)),
                ];
            });
            Journal::record('published-schema-drift', $observed);
            $test->same(['result' => 404, 'failures' => 404, 'owner_result_denied' => true, 'owner_failures_denied' => true], $observed,
                'Known result refs must refuse the old schema after a current owner schema change.');
            $test->same($privateReads, count(Journal::events('private-read')), 'Schema drift attempted to expose private parts.');
            $test->same($snapshot, Runtime::snapshot(Runtime::bulk('export')), 'Schema read refusal changed durable execution progress.');
        } finally {
            $db->table('bulk_fixture_revisions')->update(['schema_hash' => $original]);
        }
        $after['schema_denial'] = $observed;

        break;

    case 'export-revoke':
        $id = Runtime::id('export');
        $transfer = new TransferRef(Runtime::transfer($id));
        $grants = $db->table('scenario_field_grants')->where('workspace_id', Scenario::W1)
            ->where('field_use', 'export')->where('entity', InputIdentity::ENTITY)->where('record_id', InputIdentity::SECOND);
        foreach ([InputIdentity::SERIAL, 'record'] as $field) {
            $grant = (clone $grants)->where('field_id', $field);
            $grant->delete();
            Runtime::scoped(static function (DataScope $scope) use ($test, $id, $transfer): void {
                $record = new RecordRef(InputIdentity::ENTITY, InputIdentity::SECOND);
                $test->check(app(DataScopeAuthorizer::class)->authorize($scope, $record, new FieldSelection([InputIdentity::SERIAL]), FieldUse::Read)->allowed, 'Read should survive independent Export revocation.');
                $test->same(404, Runtime::http('GET', Runtime::BASE.'/'.$id.'/result')['status'], 'Revoked export field or record was accessible by known Bulk ref.');
                $test->same(404, Runtime::http('GET', Runtime::BASE.'/'.$id.'/failures')['status'], 'Revoked export remained visible through the failure route.');
                expectOwnerDenied($test, static fn () => app(TransferResultReader::class)->result($scope, $transfer));
                expectOwnerDenied($test, static fn () => app(TransferResultReader::class)->failures($scope, $transfer, null, 1));
            });
            $db->table('scenario_field_grants')->insert([
                'principal_id' => Scenario::USER_A, 'workspace_id' => Scenario::W1, 'entity' => InputIdentity::ENTITY,
                'record_id' => InputIdentity::SECOND, 'field_use' => 'export', 'field_id' => $field,
            ]);
        }

        break;

    case 'foreign':
        $ids = [$db->table('sys_api_bulk_operations')->where('submission_id', hash_hmac('sha256', 'fixture-import', config('app.key')))->sole()->id, Runtime::id('export')];
        foreach ($ids as $id) {
            $ref = new TransferRef(Runtime::transfer($id));
            Runtime::scoped(static function (DataScope $scope) use ($id, $ref, $test): void {
                foreach (['/result', '/failures'] as $suffix) {
                    $test->same(404, Runtime::http('GET', Runtime::BASE.'/'.$id.$suffix)['status'], 'Foreign workspace read leaked a known operation.');
                }
                expectOwnerDenied($test, static fn () => app(TransferResultReader::class)->result($scope, $ref));
                expectOwnerDenied($test, static fn () => app(TransferResultReader::class)->failures($scope, $ref, null, 1));
            }, Scenario::W2);
            Runtime::scoped(static function (DataScope $scope) use ($id, $ref, $test): void {
                $foreign = new DataScope(...[...get_object_vars($scope), 'accountId' => Scenario::ACCOUNT_B]);
                expectOwnerDenied($test, static fn () => app(TransferResultReader::class)->result($foreign, $ref));
                expectOwnerDenied($test, static fn () => app(TransferResultReader::class)->failures($foreign, $ref, null, 1));
                putenv('COREX_BULK_CLIENT=other-client');

                try {
                    $test->same(404, Runtime::http('GET', Runtime::BASE.'/'.$id.'/result')['status'], 'Another client read a known result ref.');
                } finally {
                    putenv('COREX_BULK_CLIENT');
                }
            });
        }
        $test->same(2, count($ids), 'Foreign test requires both committed owner modes.');

        break;

    case 'export-tamper':
        $id = Runtime::id('export');
        $manifest = $db->table('corex_export_manifests')->where('job_id', Runtime::transfer($id))->sole();
        $ref = $db->table('corex_export_parts')->where(['manifest_id' => $manifest->id, 'part_no' => 0])->sole()->immutable_ref;
        $path = partPath($ref);
        $old = file_get_contents($path);
        $test->check(is_string($old), 'Missing verified file to exercise checksum tamper.');
        $snapshot = Runtime::snapshot(Runtime::bulk('export'));
        foreach (['tampered', null] as $replacement) {
            if ($replacement === null) {
                unlink($path);
            } else {
                file_put_contents($path, $replacement, LOCK_EX);
            }

            try {
                $test->same(404, Runtime::scoped(static fn () => Runtime::http('GET', Runtime::BASE.'/'.$id.'/result'))['status'], 'Unverifiable provider bytes were served.');
                $test->same($snapshot, Runtime::snapshot(Runtime::bulk('export')), 'Result verification advanced or changed the checkpoint.');
            } finally {
                file_put_contents($path, $old, LOCK_EX);
                chmod($path, 0600);
            }
        }

        break;

    case 'expiry':
        $id = Runtime::id('export');
        $result = Runtime::scoped(static function () use ($id): array {
            app(ScenarioClock::class)->advance(5000);

            return [
                Runtime::http('GET', Runtime::BASE.'/'.$id.'/result'),
                Runtime::http('GET', Runtime::BASE.'/'.$id.'/failures'),
            ];
        });
        $test->same([410, 410], array_column($result, 'status'), 'An authorized caller must receive a typed expiration for retained operation metadata.');
        $after['responses'] = $result;

        break;

    case 'restore':
        $id = Runtime::id('export');
        $ref = new TransferRef(Runtime::transfer($id));
        $oldScope = Runtime::scoped(static fn (DataScope $scope): DataScope => $scope);
        $pending = Runtime::scoped(static fn () => Runtime::submit('import', 'fixture-restore'));
        $pendingId = accepted($test, $pending);
        $control = app(DatabaseScopeControl::class);
        $account = Scenario::account(Scenario::BOXED);
        $prior = $control->resolve($account);
        $closing = $control->close($account->id, $prior->controlRevision);

        try {
            $control->drain($account->id, $closing);
            $test->check(false, 'Unreconciled killed-worker transactions must block restore.');
        } catch (ScopeContextMismatch $blocked) {
            $test->check(str_contains($blocked->getMessage(), 'Outstanding work'), 'Drain must report the unresolved local work.');
        }
        $after['reconciled_local_tickets'] = CrashRecovery::reconcile($test, $oldScope);
        $revision = $control->drain($account->id, $closing);
        $next = Scenario::binding($prior->bindingId, $prior->bindingGeneration, 'b', $account->id);
        $control->reopen($account->id, $revision, $next, static function ($binding) use ($db): bool {
            PhysicalMarker::assert($binding, $db, $db->getDatabaseName());

            $schema = $db->getSchemaBuilder();

            return $schema->hasTable('sys_api_bulk_operations') && $schema->hasTable('corex_transfer_jobs');
        });
        $after['restore_epochs'] = [$oldScope->restoreEpoch, $control->resolve($account)->restoreEpoch];
        $test->check($after['restore_epochs'][0] !== $after['restore_epochs'][1], 'Actual control authority did not advance the restore epoch.');
        Runtime::scoped(static function (DataScope $scope) use ($test, $id, $ref, $oldScope): void {
            foreach (['/result', '/failures'] as $suffix) {
                $test->same(404, Runtime::http('GET', Runtime::BASE.'/'.$id.$suffix)['status'], 'Old-epoch result remained visible through the new route.');
            }
            expectOwnerDenied($test, static fn () => app(TransferResultReader::class)->result($scope, $ref));
            expectOwnerDenied($test, static fn () => app(TransferResultReader::class)->result($oldScope, $ref));
        });
        $snapshot = Runtime::snapshot($db->table('sys_api_bulk_operations')->where('id', $pendingId)->sole());
        $effects = sink()->table('bulk_sink_journal')->count();
        $test->same(0, Runtime::work(1800), 'Worker failed while refusing stale scope.');
        $test->same($effects, sink()->table('bulk_sink_journal')->count(), 'Old-epoch operation reached the sink.');
        $test->same($snapshot['checkpoint'], Runtime::snapshot($db->table('sys_api_bulk_operations')->where('id', $pendingId)->sole())['checkpoint'], 'Old-epoch worker advanced progress.');

        break;

    default:
        throw new InvalidArgumentException('Unknown bulk-transfer probe phase '.$mode.'.');
}

$test->report($db, $refs, $before, $after);

function accepted(Scenario $test, array $response): string
{
    $test->same(202, $response['status'], 'HTTP submission must return 202. Body '.json_encode($response['body'], JSON_THROW_ON_ERROR));
    $id = $response['body']['operation_id'];
    $test->same(Runtime::BASE.'/'.$id, $response['location'], 'Accepted response must publish its stable poll Location.');
    $test->same($response['location'], $response['body']['status_url'], 'Accepted response body and Location differ.');

    return $id;
}

function targets(string $kind, string $status): array
{
    return Scenario::connection('control')->table('corex_scope_tickets')->where(['kind' => $kind, 'state' => $status])
        ->whereNotNull('module_targets')->get()->map(static fn (object $row): array => [
            'ticket' => $row->ticket_id, 'effect' => $row->effect_key, 'targets' => json_decode($row->module_targets, true, flags: JSON_THROW_ON_ERROR),
        ])->all();
}

function hasTargets(array $tickets, array $expected): bool
{
    return array_any($tickets, static fn (array $ticket): bool => array_diff($expected, $ticket['targets']) === []);
}

function sink(): Connection
{
    return Scenario::connection('sink');
}

function transferJob(string $kind): object
{
    return Runtime::tenant()->table('corex_transfer_jobs')->where('id', Runtime::transfer(Runtime::id($kind)))->sole();
}

function eventsFor(string $event, string $operation): array
{
    return array_values(array_filter(Journal::events($event), static fn (array $row): bool => $row['operation'] === $operation));
}

function partPath(string $ref): string
{
    if (preg_match('/\Acorex-exports\/[a-f0-9-]{36}\/[0-9]+-[a-f0-9]{64}\.ndjson\z/D', $ref) !== 1) {
        throw new RuntimeException('Unexpected export provider object identity.');
    }

    return PrivateStorage::root().'/'.Scenario::BOXED.'/durable/'.$ref;
}

function expectOwnerDenied(Scenario $test, Closure $read): void
{
    $test->check(ownerUnavailable($read), 'Owner read accepted an inaccessible opaque reference.');
}

function ownerUnavailable(Closure $read): bool
{
    try {
        $read();

        return false;
    } catch (SessionUnavailable|ExportUnavailable|ImportUnavailable|ScopeContextMismatch|ScopeStale) {
        return true;
    }
}

function verifyExport(Scenario $test, string $id, array &$after): void
{
    $ref = new TransferRef(Runtime::transfer($id));
    $response = Runtime::scoped(static fn () => Runtime::http('GET', Runtime::BASE.'/'.$id.'/result'));
    $test->same(200, $response['status'], 'Authorized published export result unavailable.');
    $test->check(str_contains($response['cache'], 'private') && str_contains($response['cache'], 'no-store'), 'The public endpoint permits caching of the private export result.');
    $result = $response['body'];
    $test->same($result, Runtime::scoped(static fn (DataScope $scope) => app(TransferResultReader::class)->result($scope, $ref)), 'Bulk changed the owner result projection.');
    $test->same(TransferBulkAdapter::manifestId($id), $result['manifestId'], 'The public result refers to an unrelated manifest.');
    $test->same(2, count($result['parts']), 'Expected a real multipart manifest.');
    $tuples = [];
    foreach ($result['parts'] as $index => $part) {
        $keys = array_keys($part);
        sort($keys);
        $test->same(['byteLength', 'number', 'records', 'sha256'], $keys, 'Public part leaks non-public metadata.');
        $test->same((string) $index, (string) $part['number'], 'Public manifest parts are not ordered by number.');
        $file = partPath('corex-exports/'.$result['manifestId'].'/'.$part['number'].'-'.$part['sha256'].'.ndjson');
        $test->same($part['sha256'], hash_file('sha256', $file), 'Published checksum differs from independent file read.');
        $test->same($part['byteLength'], filesize($file), 'Published byte length differs from the private file.');
        $rows = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $test->same($part['records'], count($rows), 'Public part declares a different record count than its verified bytes.');
        $test->same('EQ-10'.($index + 1), json_decode($rows[0], true, flags: JSON_THROW_ON_ERROR)['values'][InputIdentity::SERIAL], 'The coherent snapshot did not produce the expected canonical row.');
        $tuples[] = $part['number'].':'.$part['sha256'].':'.$part['byteLength'];
    }
    $test->same(hash('sha256', implode("\n", $tuples)), $result['partsHash'], 'Manifest digest disagrees with ordered verified part tuples.');
    $test->check(! str_contains(json_encode($result, JSON_THROW_ON_ERROR), 'corex-exports/'), 'Private storage paths leaked into the result.');
    $after['result'] = $result;
}
