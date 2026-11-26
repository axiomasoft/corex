<?php

declare(strict_types=1);

use App\OwnerEffects\Effects;
use App\OwnerEffects\MediaInventory;
use App\OwnerEffects\PinJournal;
use App\Scenario\FixtureLifecycleOperators;
use App\Scenario\Scenario;
use CoreX\Access\Roles\DirectGrants;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\LifecyclePlans;
use CoreX\Foundation\Contracts\ReferenceOwners;
use CoreX\Foundation\Data\ModuleId;
use CoreX\Foundation\Enums\LifecycleAction;
use CoreX\Foundation\Enums\LifecyclePhase;
use CoreX\ImportExport\Contracts\TransferResultReader;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\TransferRef;
use CoreX\ImportExport\Exceptions\ExportUnavailable;
use CoreX\ImportExport\Exceptions\ImportUnavailable;
use CoreX\ImportExport\Exceptions\SessionUnavailable;
use CoreX\Media\Attachments\Contracts\MediaAttachments;
use CoreX\Media\Conversions\Contracts\ConversionOperations;
use CoreX\Media\Conversions\Enums\ConversionState;
use CoreX\Media\Data\AttachmentInput;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaConversionRef;
use CoreX\Media\Exceptions\MediaAccessDenied;
use CoreX\Media\Exceptions\MediaOperationConflict;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Enums\Lifecycle\DisableReason;
use Packman\Modules\Enums\Lifecycle\ModuleState;
use Vaulter\Media\Contracts\Cas\BlobPins;
use Vaulter\Media\Contracts\Cas\BlobStore;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$mode = $argv[1];
$observer = Scenario::connection('a');
$budget = DB::connection('owner_budget');

function refused(Scenario $proof, array $types, Closure $operation): string
{
    try {
        $operation();
    } catch (Throwable $error) {
        $proof->check(in_array($error::class, $types, true), 'Expected scoped refusal, received '.$error::class.': '.$error->getMessage());

        return $error::class;
    }

    throw new RuntimeException('Foreign or revoked reference was accepted.');
}

function storedConversion(): MediaConversionRef
{
    return new MediaConversionRef(...Scenario::barrierRead('before-dispatch')['ref']);
}

function bytesFrom($stream): string
{
    try {
        return stream_get_contents($stream);
    } finally {
        fclose($stream);
    }
}

if ($mode === 'setup') {
    $sets = Effects::setup();
    $proof->check($sets['budget'] !== [] && $sets['a'] !== [] && $sets['b'] !== [], 'Real tenant and dedicated schema sets migrated');
    $proof->same(false, $observer->getSchemaBuilder()->hasTable('sys_api_budget_reservations'), 'Dedicated budget tables reside outside the tenant database');
    $proof->check($budget->getSchemaBuilder()->hasTable('sys_api_budget_reservations'), 'Dedicated owner has the budget reservations');
    $file = Effects::enter(Scenario::ACCOUNT_A, Scenario::W1, static function (DataScope $scope, MediaContext $context) use ($proof): string {
        $file = Effects::upload();
        $links = resolve(MediaAttachments::class);
        $links->attach($context, new AttachmentInput($file, Effects::ENTITY, Scenario::RECORD, 'evidence'));
        $proof->same([$file], array_column($links->usages($context, $file), 'fileId'), 'Verified principal attached the real stored file');
        $proof->same(['corex.media.file' => 1], resolve(BlobPins::class)->countByOwner(), 'The original exact reference is pinned by Vaulter');

        return $file;
    });
    $proof->report($observer, [$file], ['sets' => $sets], ['file' => $file, 'pins' => $observer->table('scenario_vaulter_journal')->get()]);

    return;
}

if ($mode === 'retention') {
    $db = Scenario::connection('b');
    $prior = Effects::enter(Scenario::ACCOUNT_B, Scenario::W2, static function (DataScope $scope, MediaContext $context) use ($proof, $db): array {
        $id = Effects::upload();
        $files = resolve(MediaLibrary::class);
        $ref = $files->requestConversion($context, $id, 'thumbnail');
        $proof->same(0, Artisan::call('corex:media:sweep', ['--limit' => 5]), 'Poller reconciles the B-site conversion');
        $conversion = resolve(ConversionOperations::class)->inspect($context, $ref);
        $proof->same(ConversionState::Ready, $conversion->state, 'Site B owns a confirmed conversion before retirement');
        $proof->check($files->delete($context, $id)->purgePending, 'The public file API durably schedules pin releases');
        $proof->same(0, Artisan::call('corex:media:sweep', ['--limit' => 5]), 'The file owner confirms its exact releases');
        $purge = $db->table('med_operations')->where(['file_id' => $id, 'kind' => 'purge'])->sole();
        $proof->same('ready', $purge->state, 'The file cleanup receipt is ready before the module transition');

        return ['file' => $id, 'conversion' => $ref->id, 'blob' => $conversion->blobRef, 'cleanup' => $purge->operation_key,
            'pins' => $purge->released_pins, 'journal' => $db->table('scenario_vaulter_journal')->orderBy('id')->get()->toJson(), 'scope' => $scope];
    });
    app()->bind(ReferenceOwners::class, MediaInventory::class);
    $status = Effects::enter(Scenario::ACCOUNT_B, null, static function (DataScope $scope) use ($proof, $prior, $db) {
        foreach (['enable', 'inspect', 'disable', 'purge', 'repair'] as $ability) {
            FixtureLifecycleOperators::grant($scope, Effects::MEDIA, $ability);
        }
        $lifecycle = resolve(ModuleLifecycle::class);
        $plans = resolve(LifecyclePlans::class);
        $module = new ModuleId(Effects::MEDIA);
        $disable = $plans->plan($scope, $module, LifecycleAction::Disable);
        $inspection = $plans->inspect($scope, $disable->ref);
        $proof->same([], $inspection->blockers, 'Installed owners verified the retired Media work before disable');
        $lifecycle->disable(Effects::MEDIA, DisableReason::Manual);
        $plan = $plans->plan($scope, $module, LifecycleAction::Purge);
        FixtureLifecycleOperators::grant($scope, Effects::MEDIA, 'purge', $plan->ref, $plan->digest);
        $status = $plans->apply($scope, $plan->ref, $plan->digest);
        $proof->check($status->phase === LifecyclePhase::Done, 'Media module purge remains unresolved: '.json_encode($status, JSON_THROW_ON_ERROR));
        $proof->same(ModuleState::Purged, $lifecycle->status(Effects::MEDIA)->state, 'Packman confirms the actual Media module purge');
        foreach (['med_files', 'med_conversions', 'med_operations', 'med_relations'] as $table) {
            $proof->same(null, $db->scalar('SELECT to_regclass(?)', ['public.'.$table]), 'PostgreSQL confirms the owner relation was dropped: '.$table);
        }
        $receipt = array_find($status->receipts, static fn ($r) => $r->action->owner === Effects::MEDIA && $r->action->resourceId === $prior['file']);
        $proof->check($receipt !== null, 'The real purge retained its original Media-owned lifecycle receipt');
        $history = json_decode($receipt->evidence, true, flags: JSON_THROW_ON_ERROR);
        $operations = array_column($history['operations'], null, 0);
        $proof->same($prior['blob'], $operations[$prior['conversion']][5], 'Confirmed original conversion blob retained beyond the Media table drop');
        $proof->same($prior['pins'], $operations[$prior['cleanup']][6], 'Prior exact pin-release receipts survive the physical drop');
        $proof->same($prior['journal'], $db->table('scenario_vaulter_journal')->orderBy('id')->get()->toJson(), 'Lifecycle cleanup did not release the original references twice');
        $proof->same(json_encode($status->receipts), json_encode($plans->apply($scope, $plan->ref, $plan->digest)->receipts), 'Purge replay uses the same retained evidence');
        $lifecycle->repair(Effects::MEDIA);
        $lifecycle->enable(Effects::MEDIA);
        $proof->same(0, $db->table('med_files')->count(), 'The reinstalled site B Media set is clean');

        return $status;
    });
    $proof->report($db, [$prior['scope'], $prior['file']], $prior, ['retained' => $status]);

    return;
}

if ($mode === 'isolation') {
    $ref = storedConversion();
    $known = Effects::enter(Scenario::ACCOUNT_A, Scenario::W1, static function (DataScope $scope, MediaContext $context) use ($proof, $ref): array {
        $known = Effects::knownReferences($scope);
        $proof->same(Effects::MANIFEST, $known['result']['manifestId'], 'A published result is readable before the foreign reference test');
        $proof->same($known['result']['parts'][0]['sha256'], $known['sha256'], 'The authorized owner emitted the known sealed result bytes');
        $proof->check($known['result']['parts'] !== [] && resolve(BulkOperationStore::class)->find($scope, Effects::BULK) !== null, 'Both known references have actual owner records');
        $input = new AttachmentInput($ref->fileId, Effects::ENTITY, Scenario::RECORD, 'restricted');
        $links = resolve(MediaAttachments::class);
        $record = DB::table('scenario_field_grants')->where(['workspace_id' => $scope->workspaceId, 'principal_id' => $scope->principalId,
            'entity' => Effects::ENTITY, 'record_id' => Scenario::RECORD, 'field_id' => 'record']);
        $writeGrant = (array) (clone $record)->where('field_use', 'write')->sole();
        (clone $record)->where('field_use', 'write')->delete();
        refused($proof, [MediaAccessDenied::class], static fn () => $links->attach($context, $input));
        DB::table('scenario_field_grants')->insert($writeGrant);
        $readGrant = (array) (clone $record)->where('field_use', 'read')->sole();
        (clone $record)->where('field_use', 'read')->delete();
        $proof->same([], $links->usages($context, $ref->fileId), 'Record read revocation removes the usage with file read still granted');
        DB::table('scenario_field_grants')->insert($readGrant);
        $files = resolve(MediaLibrary::class);
        $read = $files->readConversion($context, $ref->fileId, $ref->profile);
        $fileGrant = DB::table('scenario_file_grants')->where(['scope' => Effects::identity($scope), 'file' => $ref->fileId]);
        (clone $fileGrant)->where('ability', 'attach')->delete();
        refused($proof, [MediaAccessDenied::class], static fn () => $links->attach($context, $input));
        (clone $fileGrant)->where('ability', 'read')->delete();
        refused($proof, [MediaAccessDenied::class], static fn () => ($read->openStream)());
        refused($proof, [MediaAccessDenied::class], static fn () => resolve(ConversionOperations::class)->inspect($context, $ref));
        DB::table('scenario_file_grants')->insert(['scope' => Effects::identity($scope), 'file' => $ref->fileId, 'ability' => 'read']);
        resolve(DirectGrants::class)->revoke(auth()->user(), Effects::ENTITY, 'read');
        refused($proof, [MediaAccessDenied::class], static fn () => $files->read($context, $ref->fileId));
        resolve(DirectGrants::class)->grant(auth()->user(), Effects::ENTITY, 'read');

        return $known;
    });
    $results = [];
    foreach ([[Scenario::ACCOUNT_A, Scenario::W2], [Scenario::ACCOUNT_B, Scenario::W2]] as [$account, $workspace]) {
        $results[] = Effects::enter($account, $workspace, static function (DataScope $scope, MediaContext $context) use ($proof, $ref, $known): array {
            $own = Effects::upload();
            $proof->check($own !== $ref->fileId && resolve(MediaLibrary::class)->read($context, $own)->sizeBytes > 0, 'The foreign frame can read its own immutable file');
            $types = [MediaAccessDenied::class, MediaOperationConflict::class];
            $links = resolve(MediaAttachments::class);
            $input = new AttachmentInput($ref->fileId, Effects::ENTITY, Scenario::RECORD, 'evidence');
            $session = Effects::exportSession($scope, new TransferRef(Effects::LOCAL_TRANSFER), Effects::LOCAL_MANIFEST);
            $denials = [
                refused($proof, $types, static fn () => $links->attach($context, $input)),
                refused($proof, $types, static fn () => $links->detach($context, $input)),
                refused($proof, $types, static fn () => $links->usages($context, $ref->fileId)),
                refused($proof, $types, static fn () => resolve(ConversionOperations::class)->inspect($context, $ref)),
                refused($proof, $types, static fn () => resolve(MediaLibrary::class)->readConversion($context, $ref->fileId, $ref->profile)),
                refused($proof, [ImportUnavailable::class, SessionUnavailable::class], static fn () => resolve(TransferResultReader::class)->result($scope, new TransferRef($known['transfer']))),
                refused($proof, [ExportUnavailable::class, SessionUnavailable::class], static fn () => $session->runner->emit($scope, $known['sealed'],
                    new ExportCursor('0', '0', $known['sealed']->partsHash))),
            ];
            $proof->same(null, resolve(BulkOperationStore::class)->find($scope, $known['operation']), 'Foreign operation lookup discloses no known operation');

            return ['scope' => $scope, 'denials' => $denials, 'local_file' => $own];
        });
    }
    $proof->report($observer, [$ref, $known], ['owner' => $known['result']], $results);

    return;
}

Effects::enter(Scenario::ACCOUNT_A, Scenario::W1, static function (DataScope $scope, MediaContext $context) use ($mode, $proof, $observer, $budget): void {
    $files = resolve(MediaLibrary::class);
    $statuses = resolve(ConversionOperations::class);
    $connection = DB::connection();
    $before = [];
    $after = [];
    switch ($mode) {
        case 'request':
            $id = $observer->table('med_files')->where('request_key', 'owner-effects-source')->sole()->id;
            $connection->beginTransaction();
            $ref = null;
            Event::listen(TransactionBeginning::class, static function ($event) use ($connection, $proof, $observer, &$ref): void {
                if ($event->connection !== $connection || $connection->transactionLevel() !== 2) {
                    return;
                }
                // Registered on the same nested transaction before Media appends its dispatch callback.
                $connection->afterCommit(static function () use ($proof, $observer, &$ref): void {
                    $row = $observer->table('med_operations')->where('operation_key', $ref->id)->sole();
                    $proof->same('pending', $row->state, 'Independent observer sees the committed intent before dispatch');
                    $proof->same(null, $row->blob_receipt, 'Conversion blob has not been published');
                    $proof->same(0, $observer->table('scenario_vaulter_journal')->where('owner', 'corex.media.conversion')->count(), 'No output pin preceded worker dispatch');
                    $snapshot = ['ref' => $ref, 'operation' => $row->id, 'digest' => $row->profile_digest, 'source_version' => $row->source_version,
                        'scope' => json_decode($row->conversion_scope, true, flags: JSON_THROW_ON_ERROR), 'state' => $row->state];
                    $proof->report($observer, [$ref], ['dispatch' => 0], $snapshot);
                    Scenario::barrier('before-dispatch', $snapshot);

                    throw new RuntimeException('The runner must kill the worker at the committed intent.');
                });
            });
            $ref = $files->requestConversion($context, $id, 'thumbnail');
            $proof->same(ConversionState::Pending, $statuses->inspect($context, $ref)->state, 'Request returns the stable immediately inspectable pending receipt');
            $proof->same(null, $files->readConversion($context, $id, 'thumbnail'), 'Pending conversion has no published bytes');
            $proof->same(0, $observer->table('med_operations')->where('operation_key', $ref->id)->count(), 'Separate PG session cannot read the uncommitted intent');
            $connection->commit();

            throw new RuntimeException('The intent barrier did not stop dispatch.');
        case 'recover':
            $ref = storedConversion();
            $initial = Scenario::barrierRead('before-dispatch');
            $proof->same(ConversionState::Pending, $statuses->inspect($context, $ref)->state, 'Lost dispatch leaves the original pending request');
            $proof->same(get_object_vars($ref), get_object_vars($files->requestConversion($context, $ref->fileId, $ref->profile)), 'Repeat returns the same receipt');
            $proof->same(0, Artisan::call('corex:media:sweep', ['--limit' => 5]), 'Normal durable poller completes');
            $status = $statuses->inspect($context, $ref);
            $proof->same(ConversionState::Ready, $status->state, 'Poller publishes the original conversion');
            $read = $files->readConversion($context, $ref->fileId, $ref->profile);
            $output = bytesFrom(($read->openStream)());
            $proof->same([$status->sha256, $status->width, $status->height], [hash('sha256', $output), ...array_slice(getimagesizefromstring($output), 0, 2)], 'Published digest and dimensions match bytes');
            $proof->check($output === bytesFrom(resolve(BlobStore::class)->read($status->blobRef)), 'Actual Vaulter stream matches authorized Media read');
            $op = $observer->table('med_operations')->where('operation_key', $ref->id)->sole();
            $proof->same([$initial['operation'], $initial['digest'], $initial['source_version']], [$op->id, $op->profile_digest, $op->source_version], 'Same durable operation retains its profile revision and source version');
            $conversion = $observer->table('med_conversions')->where('file_id', $ref->fileId)->sole();
            $pin = $observer->table('scenario_vaulter_journal')->where(['owner' => 'corex.media.conversion', 'action' => 'pin'])->sole();
            $proof->same([(string) $conversion->id, $status->blobRef], [$pin->reference, $pin->blob], 'Exact output pin committed with the correct provider reference');
            $proof->same(0, Artisan::call('corex:media:sweep', ['--limit' => 5]), 'Second sweep cannot duplicate the conversion');
            $proof->same(1, $observer->table('scenario_vaulter_journal')->where('owner', 'corex.media.conversion')->count(), 'Exactly one conversion pin was performed');
            $after = ['receipt' => $op, 'status' => $status, 'pin' => $pin];

            break;

        case 'charge':
            $budget->beforeExecuting(static function ($sql, $bindings) use ($proof, $observer, $budget): void {
                if (! str_starts_with(strtolower($sql), 'update "sys_api_budget_reservations"') || ! in_array('settled', $bindings, true)) {
                    return;
                }
                $owner = $observer->table('sys_api_idempotency')->where('state', 'completed')->sole();
                $receipt = json_decode($owner->outcome_json, true, flags: JSON_THROW_ON_ERROR)['budget'];
                $hold = $budget->table('sys_api_budget_reservations')->where('id', $receipt['reservation_id'])->sole();
                $proof->same('held', $hold->state, 'Dedicated reservation is still held');
                $proof->same(1, $observer->table('scenario_charged_effects')->where('effect', 'charge-thumbnail')->count(), 'Independent PostgreSQL session sees committed tenant effect before settlement');
                $snapshot = ['receipt' => $receipt, 'hold' => $hold, 'owner_digest' => hash('sha256', $owner->outcome_json)];
                $proof->report($observer, [$receipt['reservation_id']], [], $snapshot);
                Scenario::barrier('before-settlement', $snapshot);

                throw new RuntimeException('The runner must kill the process before settlement.');
            });
            resolve(OperationInvoker::class)->invoke(Effects::invocation());

            throw new RuntimeException('Dedicated settlement barrier was never reached.');
        case 'replay':
            $original = Scenario::barrierRead('before-settlement');
            $receipt = $original['receipt'];
            $result = resolve(OperationInvoker::class)->invoke(Effects::invocation());
            $proof->same(true, $result->replayed, 'Public invoker recovered the committed tenant outcome');
            $hold = $budget->table('sys_api_budget_reservations')->where('id', $receipt['reservation_id'])->sole();
            $proof->same(['settled', $receipt['actual_units'] * 1000000], [$hold->state, (int) $hold->charged], 'One charge settled against the original dedicated hold');
            $proof->same(1, $observer->table('scenario_charged_effects')->count(), 'Replay did not perform another tenant effect');
            $proof->same($original['owner_digest'], hash('sha256', $observer->table('sys_api_idempotency')->sole()->outcome_json), 'Tenant budget receipt remains immutable');
            $reservations = $budget->table('sys_api_budget_reservations')->orderBy('id')->get()->all();
            $proof->same([$receipt['reservation_id']], array_column($reservations, 'id'), 'The whole dedicated ledger contains only the original hold');
            $again = resolve(OperationInvoker::class)->invoke(Effects::invocation());
            $proof->same(true, $again->replayed, 'A settled receipt remains replayable');
            $proof->same(json_encode($reservations), json_encode($budget->table('sys_api_budget_reservations')->orderBy('id')->get()->all()), 'Settled replay leaves the entire dedicated ledger unchanged');
            $proof->same(1, $observer->table('scenario_charged_effects')->count(), 'Settled replay does not enter the tenant handler');
            $ledger = resolve(ProviderEffectLedger::class);
            $effect = new ProviderEffectRef($scope->accountId, $scope->accountIncarnation, $scope->workspaceId, 'axiomasoft/corex-operations', 'budget-reservation', $hold->id);
            $evidence = $ledger->inspect($effect);
            $proof->same(ProviderObservationKind::Confirmed, $evidence?->observation?->kind, 'Independent provider ledger confirms the original dedicated reservation');
            $proof->same($hold->id, $evidence->observation->reference, 'Provider receipt retains the exact reservation key');
            $foreign = new ProviderEffectRef($scope->accountId, $scope->accountIncarnation, $scope->workspaceId, $effect->owner, 'foreign-reservation', $hold->id);
            $proof->same(null, $ledger->inspect($foreign), 'The same receipt key is unknown in another owner namespace');
            $after = ['original' => $hold, 'reservations' => $reservations, 'receipt' => $receipt, 'scope' => $scope, 'provider_effect' => $evidence];

            break;

        case 'cleanup-unknown':
            $ref = storedConversion();
            $original = $statuses->inspect($context, $ref);
            $links = resolve(MediaAttachments::class);
            $links->detach($context, new AttachmentInput($ref->fileId, Effects::ENTITY, Scenario::RECORD, 'evidence'));
            $proof->same([], $links->usages($context, $ref->fileId), 'The related record reference was detached');
            $proof->check($files->delete($context, $ref->fileId)->purgePending, 'Public delete accepted a durable cleanup obligation');
            PinJournal::$interruptRelease = true;

            try {
                Artisan::call('corex:media:sweep', ['--limit' => 5]);
            } catch (RuntimeException $exception) {
                $proof->same('Observer interrupted the Vaulter release before owner transaction commit.', $exception->getMessage(), 'A real pin operation lost its response');
            }
            $cleanup = $observer->table('med_operations')->where(['kind' => 'purge', 'file_id' => $ref->fileId])->sole();
            $proof->same('unknown', $cleanup->state, 'The purger persists an uncertain receipt on the original operation');
            $proof->same(0, $observer->table('scenario_vaulter_journal')->where('action', 'unpin')->count(), 'PostgreSQL rolled back the interrupted Vaulter transaction');
            $proof->same($original->blobRef, $observer->table('med_operations')->where('operation_key', $ref->id)->sole()->blob_receipt, 'Original conversion receipt survives an interrupted cleanup');
            $after = ['unknown' => $cleanup, 'original' => $original];

            break;

        case 'cleanup-ack':
            $ref = storedConversion();
            $unknown = $observer->table('med_operations')->where(['kind' => 'purge', 'file_id' => $ref->fileId])->sole();
            $proof->same('unknown', $unknown->state, 'Retry uses the same uncertain owner receipt');
            Date::setTestNow(Date::parse($unknown->next_attempt_at)->addSecond());
            Event::listen(TransactionCommitted::class, static function ($event) use ($ref, $observer, $proof, $unknown): void {
                if ($event->connection->transactionLevel() !== 0) {
                    return;
                }
                $row = $observer->table('med_operations')->where('id', $unknown->id)->sole();

                if ($row->state === 'ready') {
                    $proof->same(2, count(json_decode($row->released_pins, true, flags: JSON_THROW_ON_ERROR)), 'Independent observer sees both cleanup receipts before caller acknowledgement');
                    $snapshot = ['operation' => $row, 'prior' => $observer->table('med_operations')->where('operation_key', $ref->id)->sole()];
                    $proof->report($observer, [$ref, $row->id], ['unknown_epoch' => $unknown->claim_epoch], $snapshot);
                    Scenario::barrier('cleanup-committed', $snapshot);

                    throw new RuntimeException('The runner must kill the worker after commit.');
                }
            });
            Artisan::call('corex:media:sweep', ['--limit' => 5]);

            throw new RuntimeException('The committed release barrier was never reached.');
        case 'cleanup':
            $ref = storedConversion();
            $pins = $observer->table('scenario_vaulter_journal')->where('action', 'pin')->orderBy('id')->get();
            $checkpoint = Scenario::barrierRead('cleanup-committed');
            $proof->same(0, Artisan::call('corex:media:sweep', ['--limit' => 5]), 'The normal poller reconciles the lost caller acknowledgement');
            $cleanup = $observer->table('med_operations')->where(['kind' => 'purge', 'file_id' => $ref->fileId])->sole();
            $proof->same($checkpoint['operation'], (array) $cleanup, 'Replay preserves the original cleanup and claim epoch');
            $proof->same('ready', $cleanup->state, 'Cleanup has a committed Media receipt');
            $released = json_decode($cleanup->released_pins, true, flags: JSON_THROW_ON_ERROR);
            $proof->same(2, count($released), 'Both original and conversion releases are recorded');
            foreach ($released as [$blob, $owner, $reference]) {
                $pair = $observer->table('scenario_vaulter_journal')->where(compact('owner', 'reference', 'blob'))->orderBy('id')->pluck('action')->all();
                $proof->same(['pin', 'unpin'], $pair, 'Exact committed reference was acquired and released once');
            }
            $proof->same($checkpoint['prior'], (array) $observer->table('med_operations')->where('operation_key', $ref->id)->sole(), 'File purge retained the confirmed prior effect');
            $proof->same(1, $observer->table('scenario_charged_effects')->count(), 'Owner cleanup preserves the original business charge evidence');
            $after = ['prior_pins' => $pins, 'cleanup' => $cleanup, 'journal' => $observer->table('scenario_vaulter_journal')->orderBy('id')->get()];

            break;

        default:
            throw new RuntimeException('Unknown owner-effects checkpoint.');
    }
    $proof->report($observer, [$scope], $before, $after);
});
