<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleTableReferences;
use CoreX\Foundation\Contracts\ReferenceCleanup;
use CoreX\Foundation\Data\CleanupAction;
use CoreX\Foundation\Data\CleanupReceipt;
use CoreX\Foundation\Data\LifecycleBlocker;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Foundation\Laravel\SqlReferenceInventory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use stdClass;

#[Internal]
final readonly class MediaModuleReferences implements ModuleTableReferences, ReferenceCleanup
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current, private MediaBlobs $blobs, private ClockInterface $clock) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-media';
    }

    public function moduleTables(): array
    {
        return ['med_files', 'med_relations', 'med_operations', 'med_conversions'];
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert(scope: $scope, connection: $tenant);
        $own = $module->package === $this->owner();

        return SqlReferenceInventory::scan(owner: $this->owner(), tables: [
            'med_files' => [$tenant, ['kind', 'state', 'version', 'sha256'], function (array $row, string $id) use ($scope, $own): LifecycleBlocker|CleanupAction {
                if (! $own) {
                    return new LifecycleBlocker(owner: $this->owner(), resourceKind: 'media-file', resourceId: $id, scope: $scope, requiredAction: 'remove-or-rebind-reference');
                }

                return $this->action(scope: $scope, file: $id, version: Value::integer($row['version']), hash: Value::text($row['sha256']));
            }],
            'med_relations' => [$tenant, ['entity_handle']],
            'med_operations' => [$tenant, ['profile_key', 'profile_digest', 'source_version', 'kind', 'state'], function (array $row, string $id) use ($scope, $own): ?LifecycleBlocker {
                if ($own && in_array($row['state'], ['ready', 'superseded'], true)) {
                    return null;
                }

                return new LifecycleBlocker(owner: $this->owner(), resourceKind: 'media-operation', resourceId: $id, scope: $scope, requiredAction: 'reconcile-original-owner-effect');
            }],
            'med_conversions' => [$tenant, ['profile_key', 'profile_digest', 'source_version', 'state'], function (array $row, string $id) use ($scope, $own): ?LifecycleBlocker {
                if ($own && in_array($row['state'], ['ready', 'deleted', 'superseded'], true)) {
                    return null;
                }

                return new LifecycleBlocker(owner: $this->owner(), resourceKind: 'media-conversion', resourceId: $id, scope: $scope, requiredAction: 'reconcile-original-conversion');
            }],
        ], module: $module, scope: $scope, after: $after);
    }

    private function action(DataScope $scope, string $file, int $version, string $hash): CleanupAction
    {
        $key = hash('sha256', json_encode([$scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->restoreEpoch,
            $scope->accountId, $scope->accountIncarnation, $scope->workspaceId, $file, $version, $hash], JSON_THROW_ON_ERROR));

        return new CleanupAction(owner: $this->owner(), namespace: 'corex.media.file', resourceKind: 'release-file-pins', resourceId: $file, version: (string) $version, effectKey: 'lifecycle:'.$key);
    }

    private function rows(string $table, DataScope $scope): Builder
    {
        return $this->connections->connection()->table($table)->where(['account_id' => $scope->accountId,
            'account_incarnation' => $scope->accountIncarnation, 'workspace_scope' => $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId]);
    }

    public function lookup(DataScope $scope, CleanupAction $action): ?CleanupReceipt
    {
        $this->current->assert(scope: $scope, connection: $this->connections->connection());
        $file = $this->rows(table: 'med_files', scope: $scope)->where('id', $action->resourceId)->firstOrFail();

        if (get_object_vars($action) !== get_object_vars($this->action(scope: $scope, file: Value::text($file->id), version: Value::integer($file->version), hash: Value::text($file->sha256)))) {
            throw new LifecycleBlocked('Media namespace, content or file version changed.');
        }
        $evidence = $this->rows(table: 'med_operations', scope: $scope)->where('operation_key', $action->effectKey)->where('file_id', $action->resourceId)
            ->where('kind', 'lifecycle')->where('state', 'ready')->value('released_pins');

        if ($evidence === null) {
            return null;
        }

        if ($file->state !== 'deleted' || $file->blob_id !== null || $this->rows(table: 'med_conversions', scope: $scope)->where('file_id', $file->id)->whereNotNull('blob_id')->exists()) {
            throw new LifecycleBlocked('Released Media references reappeared.');
        }

        return new CleanupReceipt(action: $action, evidence: Value::text($evidence));
    }

    public function cleanup(DataScope $scope, CleanupAction $action): CleanupReceipt
    {
        ReferenceWrites::assertCleanup(scope: $scope, action: $action);
        $db = $this->connections->connection();

        if ($db->transactionLevel() !== 0) {
            throw new LifecycleBlocked('Independent Media release transaction required.');
        }
        $this->blobs->transaction(connection: $db, callback: function () use ($scope, $action): void {
            $file = $this->rows(table: 'med_files', scope: $scope)->where('id', $action->resourceId)->lockForUpdate()->firstOrFail();

            if ($this->lookup(scope: $scope, action: $action) !== null) {
                return;
            }
            $operations = $this->rows(table: 'med_operations', scope: $scope)->where('file_id', $action->resourceId)->orderBy('id')->lockForUpdate()->get();

            if ($this->rows(table: 'med_relations', scope: $scope)->where('file_id', $action->resourceId)->exists() || $operations->contains(
                static fn (stdClass $op): bool => ! in_array($op->state, ['ready', 'superseded'], true))) {
                throw new LifecycleBlocked('Unresolved Media attachment, conversion or release.');
            }
            $released = [];

            if ($file->blob_id !== null) {
                $released[] = [Value::text($file->blob_id), 'corex.media.file', $action->resourceId];
            }
            $conversions = $this->rows(table: 'med_conversions', scope: $scope)->where('file_id', $action->resourceId);
            foreach ((clone $conversions)->orderBy('id')->lockForUpdate()->get() as $conversion) {
                if ($conversion->blob_id !== null) {
                    $released[] = [Value::text($conversion->blob_id), 'corex.media.conversion', (string) Value::integer($conversion->id)];
                }
            }
            foreach ($released as [$id, $owner, $reference]) {
                $this->blobs->unpin(blobId: $id, ownerType: $owner, referenceKey: $reference);
            }
            // Retain the exact upload key, prior effect versions, original blob receipts
            // and prior pin releases even when the Packman transition drops these tables.
            $history = $operations->map(static fn (stdClass $op): array => [$op->operation_key, $op->kind, $op->state, $op->profile_digest,
                $op->source_version, $op->blob_receipt, $op->released_pins])->all();
            $evidence = json_encode(['file' => $file->id, 'request' => $file->request_key, 'request_digest' => $file->request_digest,
                'version' => $file->version, 'content' => $file->sha256, 'released' => $released, 'operations' => $history], JSON_THROW_ON_ERROR);
            $conversions->update(['blob_id' => null, 'state' => 'deleted']);
            $this->rows(table: 'med_files', scope: $scope)->where('id', $action->resourceId)->update(['blob_id' => null, 'state' => 'deleted', 'deleted_at' => $this->clock->now()]);
            $this->connections->connection()->table('med_operations')->insert([
                'account_id' => $scope->accountId, 'account_incarnation' => $scope->accountIncarnation,
                'workspace_scope' => $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId,
                'deployment_id' => $scope->deploymentId, 'binding_id' => $scope->bindingId, 'binding_generation' => $scope->bindingGeneration,
                'restore_epoch' => $scope->restoreEpoch, 'operation_key' => $action->effectKey, 'file_id' => $action->resourceId,
                'kind' => 'lifecycle', 'state' => 'ready', 'released_pins' => $evidence, 'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
            ]);
        });

        return $this->lookup(scope: $scope, action: $action) ?? throw new LifecycleBlocked('Media release receipt unavailable.');
    }
}
