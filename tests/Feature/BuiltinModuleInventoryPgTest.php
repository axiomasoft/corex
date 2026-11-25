<?php

declare(strict_types=1);

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\LifecycleInventory;
use CoreX\Foundation\Contracts\LifecyclePlans;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ModuleTableReferences;
use CoreX\Foundation\Data\ModuleId;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Enums\LifecycleAction;
use CoreX\Foundation\Enums\LifecyclePhase;
use CoreX\Scheduler\SchedulerServiceProvider;
use CoreX\Testing\CoreXTestCase;
use CoreX\Tests\Support\WorkScopeFixture;
use Flex\Crm\Tests\Support\AccountFixture;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Enums\Lifecycle\DisableReason;
use Packman\Modules\Enums\Lifecycle\ModuleState;
use Packman\Modules\Events\Lifecycle\ModulePurged;
use Packman\Schema\Contracts\Migration\SchemaSets;
use Packman\Schema\Enums\SchemaLifecycle;
use Packman\Schema\Enums\SchemaScope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class UnrelatedInventoryModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('fixture/unrelated')->title('Unrelated');
    }
}

#[Group('pg')]
final class BuiltinModuleInventoryPgTest extends CoreXTestCase
{
    private bool $inspectScheduler = true;

    protected function getPackageProviders($app): array
    {
        $providers = $this->providedData()[0]['providers'] ?? [SchedulerServiceProvider::class];

        return [...parent::getPackageProviders($app), ...$providers];
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::setDefaultConnection('pgsql');
        $db = DB::connection();

        if (! app()->environment('testing') || ! str_ends_with($db->getDatabaseName(), '_test') || $db->scalar('select current_database()') !== $db->getDatabaseName()) {
            throw new RuntimeException('Module inventory requires a verified PostgreSQL test database.');
        }
        $db->statement('DROP SCHEMA IF EXISTS public CASCADE');
        $db->statement('CREATE SCHEMA public');
        $this->migrateSchemaSets(['axiomasoft/corex:tenant', 'axiomasoft/packman-modules:tenant'], 'pgsql');
    }

    private function schedulerInstallation(): DataScope
    {
        $scheduler = 'axiomasoft/corex-scheduler';
        $other = 'fixture/unrelated';

        $files = new Filesystem;
        $site = database_path('scheduler-install');
        $base = $this->app->basePath();
        $this->beforeApplicationDestroyed(fn () => $this->app->setBasePath($base));
        $files->makeDirectory($site.'/vendor/composer', 0700, true);
        $files->makeDirectory($site.'/bootstrap/cache', 0700, true);
        $metadata = json_decode($files->get(dirname(__DIR__, 2).'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
        $metadata['packages'] = array_values(array_filter($metadata['packages'], static fn (array $row): bool => in_array($row['name'], [
            $scheduler, 'axiomasoft/corex', 'axiomasoft/packman', 'axiomasoft/packman-modules', 'axiomasoft/packman-schema',
        ], true)));
        $metadata['packages'][] = ['name' => $other, 'type' => 'packman-module', 'version' => '1.0.0', 'extra' => ['packman' => ['module' => UnrelatedInventoryModule::class]]];
        $files->put($site.'/vendor/composer/installed.json', json_encode($metadata, JSON_THROW_ON_ERROR));
        $this->app->setBasePath($site);
        $this->app->forgetInstance(ModuleRegistry::class);
        $this->artisan('modules:compile')->assertSuccessful();

        $scope = WorkScopeFixture::install($this->app, 'scheduler-operator')->scope();
        foreach (['enable', 'inspect', 'disable', 'purge'] as $ability) {
            Gate::define('corex.lifecycle.'.$ability, fn (ScopePrincipal $principal, DataScope $current, ModuleRef $target): bool => $current == $scope && $principal->kind === $scope->principalKind && $principal->id === $scope->principalId
                && ($target->package === $other || ($target->package === $scheduler && ($ability !== 'inspect' || $this->inspectScheduler))));
        }
        // This boxed installation has one verified account, no workspaces and no external owners.
        $this->app->instance(LifecycleInventory::class, new readonly class($scope) implements LifecycleInventory
        {
            public function __construct(private DataScope $account) {}

            public function scopes(DataScope $operator, ModuleRef $target): array
            {
                if ($operator != $this->account) {
                    throw new LogicException('Unknown boxed account.');
                }

                return [$this->account];
            }

            public function owners(DataScope $scope, ModuleRef $target): array
            {
                return [];
            }

            public function revision(DataScope $operator, ModuleRef $target): string
            {
                return 'boxed-scheduler-v1';
            }

            public function run(DataScope $scope, Closure $operation): mixed
            {
                resolve(CurrentWorkScope::class)->assert($scope, DB::connection());

                return $operation();
            }
        });

        return $scope;
    }

    public function test_an_unrelated_disable_completes_after_native_scheduler_purge_without_scheduler_inspect(): void
    {
        $scope = $this->schedulerInstallation();
        $scheduler = 'axiomasoft/corex-scheduler';
        $other = 'fixture/unrelated';
        $native = resolve(ModuleLifecycle::class);
        $plans = resolve(LifecyclePlans::class);
        $native->enable($other);
        $native->enable($scheduler);
        self::assertSame(ModuleState::Enabled, $native->status($scheduler)->state);
        self::assertSame(0, DB::table('sys_wakeup_requests')->count());
        $native->disable($scheduler, DisableReason::Manual);
        self::assertSame(ModuleState::Disabled, $native->status($scheduler)->state);
        $dropped = [];
        Event::listen(ModulePurged::class, static function (ModulePurged $event) use ($scheduler, &$dropped): void {
            if ($event->module === $scheduler) {
                $dropped = $event->droppedTables;
            }
        });
        $purge = $plans->plan($scope, new ModuleId($scheduler), LifecycleAction::Purge);
        $done = $plans->apply($scope, $purge->ref, $purge->digest);
        self::assertSame(LifecyclePhase::Done, $done->phase);
        self::assertSame(ModuleState::Purged, $native->status($scheduler)->state);
        self::assertSame(['sys_wakeup_requests'], $dropped);
        self::assertFalse(Schema::hasTable('sys_wakeup_requests'));
        $checkpoint = DB::table('sys_lifecycle_plans')->where('plan_id', $purge->ref->id)->sole();
        $cursor = json_decode($checkpoint->cursor, true, flags: JSON_THROW_ON_ERROR);
        $this->inspectScheduler = false;
        expect(fn () => $native->status($scheduler))->toThrow(AuthorizationException::class);
        $disable = $plans->plan($scope, new ModuleId($other), LifecycleAction::Disable);
        $result = $plans->apply($scope, $disable->ref, $disable->digest);
        self::assertSame($disable->ref->id, resolve(DatabaseScopeControl::class)->moduleClosure($scope, new ModuleRef($other)));
        self::assertSame([], $result->blockers, json_encode(['purge' => $checkpoint->transition_result, 'drop_checkpoint' => $cursor, 'unrelated' => $result], JSON_THROW_ON_ERROR));
        self::assertSame(LifecyclePhase::Done, $result->phase);
        self::assertSame(ModuleState::Disabled, $native->status($other)->state);
        self::assertSame($dropped, $cursor['dropped_tables']);
        self::assertSame(hash('sha256', '[[],[]]'), $cursor['cleanup_digest']);
        self::assertSame($purge->digest->value, $checkpoint->digest);
        self::assertSame('done', $checkpoint->state);
        expect(fn () => $native->status($scheduler))->toThrow(AuthorizationException::class);

        // An unrelated purge scan must also reject an exact but recreated empty relation.
        $set = resolve(SchemaSets::class)->get($scheduler.':tenant');
        foreach ((new Filesystem)->files($set->path) as $migration) {
            (require $migration->getPathname())->up();
        }
        self::assertTrue(Schema::hasTable('sys_wakeup_requests'));
        $next = $plans->plan($scope, new ModuleId($other), LifecycleAction::Purge);
        self::assertSame([$scheduler], array_column($plans->inspect($scope, $next->ref)->blockers, 'owner'));
    }

    /** @return iterable<string,array{array{name:string,providers:list<class-string>}}> */
    public static function nativeSchemaOwners(): iterable
    {
        $root = dirname(__DIR__, 2);
        foreach (glob($root.'/{packages,products}/*/composer.json', GLOB_BRACE) ?: [] as $path) {
            $package = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $module = $package['extra']['packman']['module'] ?? null;

            if ($package['type'] === 'packman-module' && is_string($module) && is_a($module, WithSchema::class, true)) {
                yield $package['name'] => [['name' => $package['name'], 'providers' => $package['extra']['laravel']['providers']]];
            }
        }
    }

    /** @param array{name:string,providers:list<class-string>} $package */
    #[DataProvider('nativeSchemaOwners')]
    public function test_registered_probe_declares_exactly_the_tables_its_schema_creates_and_its_scan_reads(array $package): void
    {
        // Tasks and CRM migrations reference the host's account membership tables.
        AccountFixture::migrate();
        $owner = $package['name'];
        $scope = WorkScopeFixture::install($this->app)->scope();
        $probe = collect($this->app->tagged(ModuleReferences::class))->sole(fn (ModuleReferences $probe): bool => $probe->owner() === $owner);
        self::assertInstanceOf(ModuleTableReferences::class, $probe);
        $set = resolve(SchemaSets::class)->moduleSet($owner);
        self::assertNotNull($set);
        self::assertSame(SchemaScope::Tenant, $set->scope);
        self::assertSame(SchemaLifecycle::Module, $set->lifecycle);
        $before = Schema::getTableListing('public', false);
        $this->migrateSchemaSets([$set->id()], 'pgsql');
        $created = array_values(array_diff(Schema::getTableListing('public', false), $before));
        self::assertNotEmpty($created);
        self::assertEqualsCanonicalizing($created, $probe->moduleTables());

        // Each page visits one empty table. PostgreSQL holds its read lock until outer commit.
        // Compare the actual physical read order with the certificate used by later scans.
        DB::transaction(function () use ($probe, $owner, $scope): void {
            $readTables = static fn (): array => array_column(DB::select(<<<'SQL'
                select c.relname from pg_locks l join pg_class c on c.oid = l.relation
                 where l.pid = pg_backend_pid() and l.mode = 'AccessShareLock' and l.granted
                   and c.relnamespace = 'public'::regnamespace and c.relkind in ('r', 'p')
                SQL), 'relname');
            $previous = $readTables();
            $cursor = null;
            $order = [];
            do {
                $page = $probe->scan(new ModuleRef($owner), $scope, $cursor);
                $locked = $readTables();
                $new = array_values(array_diff($locked, $previous));
                self::assertCount(1, $new);
                $order[] = $new[0];
                $previous = $locked;
                $cursor = $page->next;
            } while ($cursor !== null);
            self::assertSame($order, $probe->moduleTables());
        });
    }
}
