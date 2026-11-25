<?php

declare(strict_types=1);

namespace App\Lifecycle;

use App\Scenario\Scenario;
use App\Scenario\SearchScenarioMeili;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Foundation\Contracts\ReferenceCleanup;
use CoreX\Foundation\Data\CleanupAction;
use CoreX\Foundation\Data\CleanupReceipt;
use CoreX\Foundation\Data\LifecycleBlocker;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Search\Index\Contracts\IndexNamespaceExclusion;
use CoreX\Search\Index\Contracts\IndexRemovalEvidence;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexReceipt;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\Search\Index\Enums\IndexOutcome;
use CoreX\SearchMeilisearch\Contracts\MeilisearchCredentialProvider;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use CoreX\SearchMeilisearch\Laravel\MeilisearchDriverFactory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class ExternalResources implements IndexNamespaceExclusion, ReferenceCleanup
{
    public const string OWNER = 'fixture/lifecycle-resources';

    private const string INDEX = 'scenario_cas_name_uq';

    public function owner(): string
    {
        return self::OWNER;
    }

    public static function target(DataScope $scope): IndexTarget
    {
        $entity = new EntityHandle(Inventory::ENTITY);
        $cluster = getenv('COREX_SCENARIO_MEILI_NAMESPACE');
        $uid = IndexTarget::uidFor($scope, $entity, 'meilisearch', $cluster, 1, 'retained');

        return new IndexTarget($scope, $entity, 'meilisearch', $cluster, 1, $uid, hash('sha256', 'inspection/1'), 'retained');
    }

    public static function provision(DataScope $scope, bool $foreign): array
    {
        $target = self::target($scope);
        $control = Scenario::connection('control');
        $namespace = self::namespace($scope);
        $control->transaction(static function () use ($control, $namespace, $target): void {
            $control->table('scenario_deployment')->lockForUpdate()->sole();

            if ($control->table('scenario_exclusions')->where('namespace', $namespace)->exists()) {
                throw new RuntimeException('External namespace closed.');
            }
            $control->table('scenario_generations')->insert(['uid' => $target->uid, 'namespace' => $namespace, 'preparing' => true,
                'confirmed' => false, 'retained' => false]);
        });
        $batch = new IndexBatch($target, 'fixture.create.'.$target->uid, IndexAction::Create, []);
        $driver = (new MeilisearchDriverFactory)->index(app());
        $receipt = $driver->submit($batch);
        $creation = self::terminalTask($target, $receipt->taskId);

        if ($driver->lookup($batch, $receipt) !== IndexOutcome::Confirmed || ! $driver->canary($target)) {
            throw new RuntimeException('The new physical generation is unavailable.');
        }
        $credentials = resolve(MeilisearchCredentialProvider::class)->forTarget($target, MeilisearchAccess::Index);
        $response = Http::baseUrl(getenv('MEILISEARCH_HOST'))->withToken($credentials->reveal())->timeout(3)
            ->patch('/indexes/'.$target->uid.'/settings', ['displayedAttributes' => ['*']])->throw();
        $ack = $response->json();

        if ($response->status() !== 202 || $ack['status'] !== 'enqueued' || $ack['type'] !== 'settingsUpdate'
            || $ack['indexUid'] !== $target->uid || ! is_int($ack['taskUid'])) {
            throw new RuntimeException('Meili did not acknowledge the exact queued generation update.');
        }
        $task = (string) $ack['taskUid'];
        $control->table('scenario_generations')->where('uid', $target->uid)->update(['task' => $task, 'retained' => true]);
        $index = null;

        if (! $foreign) {
            DB::table('cas_equipment')->insert([
                ['id' => (string) Str::uuid7(), 'name' => 'duplicate'],
                ['id' => (string) Str::uuid7(), 'name' => 'duplicate'],
            ]);

            try {
                DB::statement('CREATE UNIQUE INDEX CONCURRENTLY scenario_cas_name_uq ON public.cas_equipment(name)');

                throw new RuntimeException('Expected an interrupted unique index build.');
            } catch (QueryException $error) {
                if ($error->getCode() !== '23505') {
                    throw $error;
                }
            }
            $index = self::catalogue();

            if ($index === null || $index->valid || $index->parent !== 'cas_equipment') {
                throw new RuntimeException('PostgreSQL did not retain the expected invalid CIC object.');
            }
            $control->table('scenario_ddl_inventory')->insert(['namespace' => self::namespace($scope), 'index_oid' => $index->oid, 'parent_oid' => $index->parent_oid, 'definition' => $index->definition]);
        }
        $control->table('scenario_generations')->where('uid', $target->uid)->update(['preparing' => false]);

        return ['uid' => $target->uid, 'task' => $task, 'enqueued' => $ack, 'available_generation' => $creation, 'index' => $index];
    }

    public static function catalogue(): ?object
    {
        return DB::selectOne("SELECT i.indexrelid::text AS oid, i.indrelid::text AS parent_oid, p.relname AS parent,
            i.indisvalid AS valid, i.indisready AS ready, pg_get_indexdef(i.indexrelid) AS definition
            FROM pg_catalog.pg_index i JOIN pg_catalog.pg_class p ON p.oid=i.indrelid
            WHERE i.indexrelid=to_regclass('public.scenario_cas_name_uq')");
    }

    private static function namespace(DataScope $scope): string
    {
        return hash('sha256', json_encode([$scope->deploymentId, $scope->accountId, $scope->accountIncarnation, $scope->bindingId,
            $scope->bindingGeneration, $scope->restoreEpoch, $scope->workspaceId, getenv('COREX_SCENARIO_MEILI_NAMESPACE')], JSON_THROW_ON_ERROR));
    }

    public function hold(DataScope $scope, ModuleRef $module, LifecyclePlanRef $plan, ?OwnerLease $previous): OwnerLease
    {
        $key = ['namespace' => self::namespace($scope), 'target' => $module->package];
        $table = Scenario::connection('control')->table('scenario_exclusions');

        if ($previous === null) {
            $control = Scenario::connection('control');
            $control->transaction(static function () use ($control, $key, $table, $plan): void {
                $control->table('scenario_deployment')->lockForUpdate()->sole();

                if ($control->table('scenario_generations')->where('namespace', $key['namespace'])->where('preparing', true)->exists()) {
                    throw new RuntimeException('The original external writer requires reconciliation.');
                }
                $table->insertOrIgnore([...$key, 'plan' => $plan->id, 'token' => (string) Str::uuid7()]);
            });
        }
        $row = $table->where($key)->where('plan', $plan->id)->sole();
        $lease = new OwnerLease(self::OWNER, $row->namespace, $plan->id, $row->token);

        if ($previous !== null && $previous != $lease) {
            throw new RuntimeException('External exclusion changed.');
        }

        return $lease;
    }

    public function verify(DataScope $scope, OwnerLease $lease): void
    {
        if ($lease->namespace !== self::namespace($scope) || ! Scenario::connection('control')->table('scenario_exclusions')
            ->where(['namespace' => $lease->namespace, 'plan' => $lease->revision, 'token' => $lease->token])->exists()) {
            throw new RuntimeException('Missing external exclusion.');
        }
    }

    public function verifyTarget(IndexTarget $target, OwnerLease $lease): void
    {
        $this->verify($target->scope, $lease);
        SearchScenarioMeili::assertOwned($target);

        if ($lease->owner !== self::OWNER || $target != self::target($target->scope)
            || ! Scenario::connection('control')->table('scenario_generations')->where(['namespace' => $lease->namespace,
                'uid' => $target->uid, 'preparing' => false, 'confirmed' => true, 'retained' => false])->exists()) {
            throw new RuntimeException('The host lacks an exclusive, drained allocation for this UID.');
        }
    }

    public function release(DataScope $scope, OwnerLease $lease): void
    {
        if ($lease->owner !== self::OWNER || $lease->namespace !== self::namespace($scope)) {
            throw new RuntimeException('Foreign external lease.');
        }
        Inventory::releaseControl($lease);
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $namespace = self::namespace($scope);
        $revision = hash('sha256', $namespace.$module->package);

        if ($module->package !== Inventory::MODULE || $scope->workspaceId === null) {
            return new ReferencePage([], $revision);
        }
        $target = self::target($scope);
        $control = Scenario::connection('control');
        $generation = $control->table('scenario_generations')->where('namespace', $namespace)->where('uid', $target->uid)->sole();
        $blockers = [];

        if (! $generation->confirmed) {
            $blockers[] = new LifecycleBlocker(self::OWNER, 'meili-task', $generation->task, $scope, 'confirm-original-task');
        }

        if ($generation->retained) {
            $blockers[] = new LifecycleBlocker(self::OWNER, 'retained-generation', $target->uid, $scope, 'release-retention');
        }
        $ddl = $control->table('scenario_ddl_inventory')->where('namespace', $namespace)->first();

        if ($ddl !== null && ! $ddl->retired) {
            $actual = self::catalogue();

            if ($actual === null || $actual->oid !== $ddl->index_oid || $actual->parent_oid !== $ddl->parent_oid || $actual->definition !== $ddl->definition || $actual->valid) {
                throw new RuntimeException('The managed PostgreSQL index identity changed.');
            }
            $blockers[] = new LifecycleBlocker(self::OWNER, 'invalid-cic', $actual->oid, $scope, 'retire-invalid-index');
        }
        $action = new CleanupAction(self::OWNER, $namespace, 'meili-index', $target->uid, $target->mappingDigest, 'drop.'.$target->uid);

        return new ReferencePage($blockers, $revision, actions: [$action]);
    }

    public function remediate(DataScope $scope): array
    {
        $namespace = self::namespace($scope);
        $control = Scenario::connection('control');
        $row = $control->table('scenario_generations')->where('namespace', $namespace)->sole();
        $task = self::terminalTask(self::target($scope), $row->task);

        if ($task['indexUid'] !== self::target($scope)->uid || $task['status'] !== 'succeeded') {
            throw new RuntimeException('The original task did not confirm.');
        }
        $control->table('scenario_generations')->where('uid', $row->uid)->update(['confirmed' => true, 'retained' => false]);
        DB::transaction(function () use ($namespace): void {
            DB::statement('LOCK TABLE public.cas_equipment IN ACCESS EXCLUSIVE MODE');
            $ddl = Scenario::connection('control')->table('scenario_ddl_inventory')->where('namespace', $namespace)->sole();
            $actual = self::catalogue();

            if ($actual === null || $actual->oid !== $ddl->index_oid || $actual->parent_oid !== $ddl->parent_oid || $actual->definition !== $ddl->definition || $actual->valid) {
                throw new RuntimeException('The approved invalid index disappeared or changed.');
            }
            DB::statement('DROP INDEX public.scenario_cas_name_uq');
        });

        if (self::catalogue() !== null) {
            throw new RuntimeException('Invalid index persisted after owner cleanup.');
        }
        $control->table('scenario_ddl_inventory')->where('namespace', $namespace)->update(['retired' => true]);

        return $task;
    }

    private static function terminalTask(IndexTarget $target, string $receipt): array
    {
        SearchScenarioMeili::assertOwned($target);

        if ($target != self::target(Inventory::scope())) {
            throw new RuntimeException('Task belongs to another physical owner.');
        }
        $id = explode('.', $receipt, 2)[0];

        if (! ctype_digit($id)) {
            throw new RuntimeException('Invalid owner task receipt.');
        }
        $key = resolve(MeilisearchCredentialProvider::class)->forTarget($target, MeilisearchAccess::Observe);
        $client = Http::baseUrl(getenv('MEILISEARCH_HOST'))->withToken($key->reveal())->timeout(3);

        return SearchScenarioMeili::await('the scoped owner observes its original Meili task', static function () use ($client, $id, $target): ?array {
            $task = $client->get('/tasks/'.$id)->throw()->json();

            if ($task['indexUid'] !== $target->uid || (string) $task['uid'] !== $id || in_array($task['status'], ['failed', 'canceled'], true)) {
                throw new RuntimeException('Unknown or failed owner task.');
            }

            return $task['status'] === 'succeeded' ? $task : null;
        });
    }

    public static function observe(IndexTarget $target, ?PendingRequest $client = null): array
    {
        SearchScenarioMeili::assertOwned($target);

        if ($target != self::target(Inventory::scope())) {
            throw new RuntimeException('Observation requires the original physical owner and index namespace.');
        }
        $authoritative = null;

        if ($client === null) {
            $adapter = (new MeilisearchDriverFactory)->index(app());

            if (! $adapter instanceof IndexRemovalEvidence) {
                throw new RuntimeException('The installed search owner cannot verify removal.');
            }
            $authoritative = $adapter->absent($target);
            $key = resolve(MeilisearchCredentialProvider::class)->forTarget($target, MeilisearchAccess::Observe);
            $client = Http::baseUrl((string) getenv('MEILISEARCH_HOST'))->withToken($key->reveal())->timeout(3);
        }
        $response = $client->get('/indexes/'.$target->uid);

        if ($authoritative === false && $response->ok() && $response->json('uid') === $target->uid) {
            return ['uid' => $target->uid, 'http' => 200, 'owner_absent' => false];
        }

        if ($authoritative !== true || $response->status() !== 404 || $response->json('code') !== 'index_not_found') {
            throw new RuntimeException('Index disposition unavailable (HTTP '.$response->status().', '.($response->json('code') ?? 'unknown').').');
        }

        return ['uid' => $target->uid, 'http' => 404, 'code' => 'index_not_found', 'owner_absent' => true];
    }

    private function authorize(DataScope $scope, CleanupAction $action): IndexTarget
    {
        $target = self::target($scope);
        $lease = ReferenceWrites::cleanupLease($scope, $action, self::OWNER);
        $this->verifyTarget($target, $lease);

        if ($action->owner !== self::OWNER || $action->namespace !== self::namespace($scope) || $action->resourceId !== $target->uid
            || $action->effectKey !== 'drop.'.$target->uid || $action->version !== $target->mappingDigest || $action->resourceKind !== 'meili-index') {
            throw new RuntimeException('External cleanup authority is unavailable.');
        }

        return $target;
    }

    public function lookup(DataScope $scope, CleanupAction $action): ?CleanupReceipt
    {
        $target = $this->authorize($scope, $action);
        $row = Scenario::connection('control')->table('scenario_cleanup_receipts')->where('effect', $action->effectKey)->first();

        if ($row === null) {
            return null;
        }

        if (self::observe($target)['http'] !== 404) {
            throw new RuntimeException('A previously removed index reappeared.');
        }

        return new CleanupReceipt($action, $row->evidence);
    }

    public function cleanup(DataScope $scope, CleanupAction $action): CleanupReceipt
    {
        $target = $this->authorize($scope, $action);

        if (DB::connection()->transactionLevel() !== 0) {
            throw new RuntimeException('External cleanup inside an outer transaction.');
        }
        $control = Scenario::connection('control');
        $table = $control->table('scenario_cleanup_receipts');
        $existing = $this->lookup($scope, $action);

        if ($existing !== null) {
            return $existing;
        }
        $observed = self::observe($target);
        $key = ['effect' => $action->effectKey, 'namespace' => $action->namespace, 'uid' => $target->uid];
        $batch = new IndexBatch($target, $action->effectKey, IndexAction::Drop, []);
        $driver = (new MeilisearchDriverFactory)->index(app());
        $intents = $control->table('scenario_drop_intents');

        if ($observed['http'] === 200 && (clone $intents)->insertOrIgnore($key) === 1) {
            $ack = $driver->submit($batch);
            (clone $intents)->where($key)->whereNull('receipt')->update(['receipt' => $ack->taskId]);
        }
        $original = $intents->where($key)->value('receipt');

        if (! is_string($original)) {
            throw new RuntimeException('Original deletion acknowledgement requires provider reconciliation.');
        }
        $task = self::terminalTask($target, $original);

        if ($driver->lookup($batch, new IndexReceipt($original)) !== IndexOutcome::Confirmed) {
            throw new RuntimeException('External deletion awaits original task confirmation.');
        }
        $observed = self::observe($target);

        if ($observed['http'] !== 404) {
            throw new RuntimeException('External deletion is unconfirmed.');
        }
        $evidence = json_encode(['namespace' => $action->namespace, 'task' => $task, 'receipt' => $original, ...$observed], JSON_THROW_ON_ERROR);
        $table->insert(['effect' => $action->effectKey, 'evidence' => $evidence]);

        return new CleanupReceipt($action, $evidence);
    }
}
