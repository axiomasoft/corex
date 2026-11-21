<?php

declare(strict_types=1);

namespace CoreX\Tests\Modules\Fixtures;

use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ReferenceCleanup;
use CoreX\Foundation\Contracts\ReferenceExclusion;
use CoreX\Foundation\Data\CleanupAction;
use CoreX\Foundation\Data\CleanupReceipt;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Laravel\ReferenceWrites;
use Illuminate\Database\Connection;
use LogicException;

/** An independent PG owner of one physical record and its original deletion receipt. */
final class CommittedCleanupOwner implements ModuleReferences, ReferenceCleanup, ReferenceExclusion
{
    public bool $reachable = true;

    public readonly string $resource;

    public readonly CleanupAction $action;

    public function __construct(private Connection $db, private string $table, private DataScope $account)
    {
        $this->resource = 'original-resource';
        $this->action = new CleanupAction($this->owner(), $table, 'external-record', $this->resource, '1', 'remove-'.$table);
        $db->table($table)->insert(['id' => $this->resource, 'value' => 'original-content']);
    }

    public function owner(): string
    {
        return 'fixture/external-records';
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $this->available();
        $applies = $scope == $this->account && $module->package === 'fixture/packman-integration' && $this->db->table($this->table)->where('id', $this->resource)->exists();

        return new ReferencePage([], 'fixture-owned-table-v1', null, $applies ? [$this->action] : []);
    }

    public function hold(DataScope $scope, ModuleRef $module, LifecyclePlanRef $plan, ?OwnerLease $previous): OwnerLease
    {
        $this->available();
        $lane = $this->table.'/'.($scope->workspaceId ?? 'account').'/'.$module->package;
        $lease = $previous ?? new OwnerLease($this->owner(), $lane, '1', $plan->id);

        if ($previous === null) {
            $this->db->table($this->table)->insertOrIgnore(['id' => $lane, 'value' => $plan->id]);
        }

        if ($lease->namespace !== $lane || $lease->token !== $plan->id) {
            throw new LogicException('Different owner lease.');
        }
        $this->verify($scope, $lease);

        return $lease;
    }

    private function available(): void
    {
        if (! $this->reachable) {
            throw new LogicException('Bounded inventory offline.');
        }
    }

    public function verify(DataScope $scope, OwnerLease $lease): void
    {
        $this->available();

        if ($lease->owner !== $this->owner() || $this->db->table($this->table)->where('id', $lease->namespace)->value('value') !== $lease->token) {
            throw new LogicException('Lost original lease.');
        }
    }

    public function release(DataScope $scope, OwnerLease $lease): void
    {
        $this->db->table($this->table)->where(['id' => $lease->namespace, 'value' => $lease->token])->delete();
    }

    public function lookup(DataScope $scope, CleanupAction $action): ?CleanupReceipt
    {
        ReferenceWrites::assertCleanup($scope, $action);
        $this->available();

        if ($scope != $this->account || $action != $this->action) {
            throw new LogicException('Foreign cleanup identity.');
        }
        $evidence = $this->db->table($this->table)->where('id', $action->effectKey)->value('value');

        if ($evidence === null) {
            return null;
        }

        if (! is_string($evidence) || $this->db->table($this->table)->where('id', $this->resource)->exists()) {
            throw new LogicException('Missing exact absence.');
        }

        return new CleanupReceipt($action, $evidence);
    }

    public function cleanup(DataScope $scope, CleanupAction $action): CleanupReceipt
    {
        $lease = ReferenceWrites::cleanupLease($scope, $action, $this->owner());
        $this->verify($scope, $lease);

        if ($scope != $this->account || $action != $this->action || $this->db->transactionLevel() !== 0) {
            throw new LogicException('Invalid physical cleanup.');
        }
        $this->db->transaction(function () use ($action, $lease): void {
            if ($this->db->table($this->table)->where(['id' => $this->resource, 'value' => 'original-content'])->delete() !== 1) {
                throw new LogicException('Unexpected second deletion.');
            }
            $this->db->table($this->table)->insert(['id' => $action->effectKey, 'value' => 'original-content/'.$lease->token]);
        });

        throw new LogicException('Lost acknowledgement following independent owner COMMIT.');
    }
}
