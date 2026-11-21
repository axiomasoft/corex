<?php

declare(strict_types=1);

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Foundation\Contracts\LifecycleInventory;
use CoreX\Foundation\Contracts\LifecyclePlans;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ReferenceOwners;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleId;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Enums\LifecycleAction;
use CoreX\Foundation\Enums\LifecyclePhase;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Contracts\IndexLookupAccess;
use CoreX\Search\Index\Contracts\IndexNamespaceExclusion;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Internal\Index\LaneStore;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use CoreX\SearchMeilisearch\Internal\MeilisearchIndexAdapter;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use CoreX\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Meilisearch\Client;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Enums\Lifecycle\DisableReason;
use Packman\Schema\Contracts\Migration\SchemaMigrator;

abstract class SearchRetirementTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SearchServiceProvider::class];
    }
}

uses(SearchRetirementTestCase::class)->group('pg', 'meilisearch');

final class FixtureSearchRetirementModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('fixture/search-retirement')->title('Retired generation owner');
    }
}

/** Authority for the one generated lane of this test. The independent database holds the actual lease token. */
final readonly class FixtureSearchRetirementHost implements IndexNamespaceExclusion, LifecycleInventory, ReferenceOwners
{
    public function __construct(private IndexTarget $target, private string $lane, private Connection $owner, private string $table) {}

    public function scopes(DataScope $operator, ModuleRef $target): array
    {
        return [$this->target->scope];
    }

    public function owners(DataScope $scope, ModuleRef $target): array
    {
        return [$this->owner()];
    }

    public function revision(DataScope $operator, ModuleRef $target): string
    {
        return $this->target->allocationId;
    }

    public function run(DataScope $scope, Closure $operation): mixed
    {
        if ($scope != $this->target->scope) {
            throw new LogicException('Foreign namespace.');
        }

        return $operation();
    }

    public function resolve(DataScope $scope, string $producer, string $kind, array $witness): array
    {
        if ($producer !== 'axiomasoft/corex-search' || ! (($witness['record'] ?? null) === $this->lane
            || ($witness['lane_key'] ?? null) === $this->lane || ($witness['uid'] ?? null) === $this->target->uid)) {
            throw new LogicException('Unknown retained search provenance.');
        }

        return [new ModuleRef('fixture/search-retirement')];
    }

    public function owner(): string
    {
        return 'fixture/index-namespace';
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        return new ReferencePage([], $this->target->allocationId);
    }

    public function hold(DataScope $scope, ModuleRef $module, LifecyclePlanRef $plan, ?OwnerLease $previous): OwnerLease
    {
        $lease = $previous ?? new OwnerLease($this->owner(), $this->target->uid, $this->target->allocationId, $plan->id);

        if ($previous === null) {
            $this->owner->table($this->table)->insertOrIgnore(['uid' => $this->target->uid, 'token' => $plan->id]);
        }
        $this->verify($scope, $lease);

        return $lease;
    }

    public function verify(DataScope $scope, OwnerLease $lease): void
    {
        if ($scope != $this->target->scope || $lease->namespace !== $this->target->uid || $lease->owner !== $this->owner()
            || $lease->revision !== $this->target->allocationId || ! $this->owner->table($this->table)->where(['uid' => $lease->namespace, 'token' => $lease->token])->exists()) {
            throw new LogicException('Missing original UID lease.');
        }
    }

    public function verifyTarget(IndexTarget $target, OwnerLease $lease): void
    {
        $this->verify($target->scope, $lease);

        if ($target != $this->target) {
            throw new LogicException('Foreign immutable allocation.');
        }
    }

    public function release(DataScope $scope, OwnerLease $lease): void
    {
        $this->owner->table($this->table)->where(['uid' => $lease->namespace, 'token' => $lease->token])->delete();
    }
}

it('retires only the approved real Meili allocation and retains its Search submission receipt through public purge', function (): void {
    $endpoint = getenv('MEILISEARCH_HOST');

    if (! $endpoint || ! in_array(parse_url($endpoint, PHP_URL_HOST), ['localhost', '127.0.0.1', '[::1]'], true)) {
        throw new RuntimeException('Requires the disposable local keyed Meilisearch backend.');
    }
    $admin = new Client($endpoint, getenv('MEILISEARCH_MASTER_KEY') ?: throw new RuntimeException('Test credential unavailable.'));
    config(['database.default' => 'pgsql']);
    $db = DB::connection();
    $database = getenv('PGSQL_DATABASE');
    $controlDatabase = getenv('PGSQL_CONTROL_DATABASE');

    if (! $database || ! $controlDatabase || $database === $controlDatabase || ! str_ends_with($database, '_test') || ! str_ends_with($controlDatabase, '_test')
        || $db->scalar('select current_database()') !== $database || ! app()->environment('testing')) {
        throw new RuntimeException('Requires two independently verified disposable PG databases.');
    }
    config(['database.connections.search_lease' => [...config('database.connections.pgsql'), 'database' => $controlDatabase]]);
    $owner = DB::connection('search_lease');
    expect($owner->scalar('select current_database()'))->toBe($controlDatabase);
    $db->statement('DROP SCHEMA IF EXISTS public CASCADE');
    $db->statement('CREATE SCHEMA public');
    $original = $this->app->basePath();
    $files = new Filesystem;
    $temp = sys_get_temp_dir().'/corex-search-retire-'.bin2hex(random_bytes(12));
    $files->makeDirectory($temp.'/vendor/composer', 0700, true);
    $files->makeDirectory($temp.'/bootstrap/cache', 0700, true);
    $metadata = json_decode($files->get(dirname(__DIR__, 4).'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    $metadata['packages'] = array_values(array_filter($metadata['packages'], static fn (array $p): bool => in_array($p['name'], ['axiomasoft/corex', 'axiomasoft/packman-modules', 'axiomasoft/corex-search'], true)));
    $name = 'fixture/search-retirement';
    $metadata['packages'][] = ['name' => $name, 'version' => '1.0.0', 'type' => 'packman-module', 'extra' => ['packman' => ['module' => FixtureSearchRetirementModule::class]]];
    $files->put($temp.'/vendor/composer/installed.json', json_encode($metadata, JSON_THROW_ON_ERROR));
    $this->app->setBasePath($temp);
    $namespaceTable = 'lease_'.bin2hex(random_bytes(10));
    $owner->statement('create table '.$namespaceTable.' (uid text primary key, token text not null)');
    $uids = [];
    $keys = [];

    try {
        $this->app->forgetInstance(ModuleRegistry::class);
        expect(Artisan::call('modules:compile'))->toBe(0, Artisan::output());
        resolve(SchemaMigrator::class)->migrateSets(['axiomasoft/corex:tenant', 'axiomasoft/packman-modules:tenant', 'axiomasoft/corex-search:tenant'], 'pgsql');
        $contexts = LocalWorkContexts::install($this->app);
        $contexts->initialize(new AccountRef('A', 'account-a', 'active', [], []));
        $scope = WorkScopeFixture::install($this->app, principalId: 'operator')->scope();
        foreach (['inspect', 'enable', 'disable', 'purge'] as $ability) {
            Gate::define('corex.lifecycle.'.$ability, fn (ScopePrincipal $p, DataScope $current, ModuleRef $module): bool => $scope == $current && $p->id === 'operator' && $module->package === $name);
        }
        $lifecycle = resolve(ModuleLifecycle::class);
        $lifecycle->enable($name);
        $store = new LaneStore(resolve('db'), resolve(CurrentWorkScope::class), 'meilisearch', 'test');
        $this->app->instance(LaneStore::class, $store);
        $entity = new EntityHandle('machine');
        $identity = $store->laneIdentity($scope, $entity);
        $allocation = (string) Str::uuid7();
        $uid = IndexTarget::uidFor($scope, $entity, 'meilisearch', 'test', 1, $allocation);
        $target = new IndexTarget($scope, $entity, 'meilisearch', 'test', 1, $uid, str_repeat('b', 64), $allocation);
        $foreign = 'foreign_'.bin2hex(random_bytes(12));
        foreach ([$uid, $foreign] as $make) {
            $task = $admin->createIndex($make);
            $uids[] = $make;
            expect($admin->waitForTask($task['taskUid'])['status'])->toBe('succeeded');
        }
        $clients = [];
        foreach (['observe' => ['indexes.get', 'tasks.get'], 'index' => ['indexes.delete']] as $capability => $actions) {
            $key = $admin->createKey(['actions' => $actions, 'indexes' => [$uid], 'expiresAt' => null]);
            $keys[] = $key->getUid();
            $clients[$capability] = new Client($endpoint, $key->getKey());
        }
        $driver = new MeilisearchIndexAdapter(static fn (IndexTarget $actual, MeilisearchAccess $access): Client => $actual == $target ? $clients[$access->value] : throw new LogicException('Foreign credential.'), resolve(CurrentWorkScope::class), 'test', resolve(IndexLookupAccess::class));
        $this->app->instance(IndexDriver::class, $driver);
        $authority = new FixtureSearchRetirementHost($target, $identity['lane_key'], $owner, $namespaceTable);
        foreach ([LifecycleInventory::class, ReferenceOwners::class, IndexNamespaceExclusion::class] as $contract) {
            $this->app->instance($contract, $authority);
        }
        $this->app->tag(IndexNamespaceExclusion::class, ModuleReferences::class);
        $db->table(LaneStore::LANES)->insert([...$identity, 'last_generation' => 1]);
        $db->table(LaneStore::GENERATIONS)->insert(['lane_key' => $identity['lane_key'], 'generation' => 1, 'cluster' => 'test', 'uid' => $uid, 'allocation_id' => $allocation, 'mapping_digest' => $target->mappingDigest, 'policy_digest' => str_repeat('d', 64), 'state' => 'retired']);
        $lifecycle->disable($name, DisableReason::Manual);
        $plans = resolve(LifecyclePlans::class);
        $plan = $plans->plan($scope, new ModuleId($name), LifecycleAction::Purge);
        $status = $plans->apply($scope, $plan->ref, $plan->digest);
        $submission = $db->table(LaneStore::SUBMISSIONS)->where('uid', $uid)->sole();
        expect($submission->task_ref)->toBeString();
        $provider = $admin->waitForTask((int) explode('.', $submission->task_ref, 2)[0]);
        expect($provider['status'])->toBe('succeeded')->and($provider['indexUid'])->toBe($uid);
        $status = $plans->apply($scope, $plan->ref, $plan->digest);
        expect($status->phase)->toBe(LifecyclePhase::Done)->and($status->receipts)->toHaveCount(1);
        $receipt = json_decode($status->receipts[0]->evidence, true, flags: JSON_THROW_ON_ERROR);
        expect($receipt['task'])->toBe($submission->task_ref)->and($receipt['submission'])->toBe($submission->submission_id)
            ->and($receipt['allocation'])->toBe($allocation)->and($receipt['uid'])->toBe($uid)
            ->and($driver->absent($target))->toBeTrue()->and($admin->getRawIndex($foreign)['uid'])->toBe($foreign)
            ->and($db->table(LaneStore::SUBMISSIONS)->where('uid', $uid)->count())->toBe(1)
            ->and($plans->apply($scope, $plan->ref, $plan->digest)->receipts)->toEqual($status->receipts);
    } finally {
        foreach ($keys as $key) {
            $admin->deleteKey($key);
        }
        foreach ($uids as $remove) {
            $task = $admin->deleteIndex($remove);
            $admin->waitForTask($task['taskUid']);
        }
        $owner->statement('drop table '.$namespaceTable);
        $this->app->setBasePath($original);
        $files->deleteDirectory($temp);
    }
});
