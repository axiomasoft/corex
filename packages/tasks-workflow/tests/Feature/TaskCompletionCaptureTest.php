<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Tests\Feature;

use Closure;
use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\CoreServiceProvider;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Task\Contracts\CompleteTaskCommand;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\TaskCompletionCandidate;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Enums\TaskStatus;
use CoreX\Tasks\TasksModule;
use CoreX\Tasks\TasksServiceProvider;
use CoreX\TasksWorkflow\Completion\Internal\TasksWorkflowActivationGate;
use CoreX\TasksWorkflow\TasksWorkflowServiceProvider;
use CoreX\Tests\Support\WorkScopeFixture;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\DefinitionRequest;
use CoreX\Workflow\Data\EngineProvider;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Enums\CaptureStatus;
use CoreX\Workflow\WorkflowServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\TestCase;
use Override;
use Packman\Config\Exceptions\InvalidConfiguration;
use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Packman\Modules\Contracts\Lifecycle\ModuleActivationGate;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Contracts\Registry\RegistryIntrospection;
use Packman\Modules\Data\Compiler\CompiledModule;
use Packman\Modules\Data\Compiler\InstalledPackageVersion;
use Packman\Modules\Data\Extenders\Workflow;
use Packman\Modules\Data\Lifecycle\ModuleActivationDecision;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Enums\Lifecycle\ModuleState;
use Packman\Modules\Internal\Discovery\ModuleDiscovery as PackmanModuleDiscovery;
use Packman\Modules\ModulesServiceProvider;
use Packman\Schema\Testing\MigratesSchemaSets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;

final readonly class PassThroughActivationGate implements ModuleActivationGate
{
    public function __construct(private ModuleActivationGate $host) {}

    #[Override]
    public function check(ContextScopeInterface $ctx, string $module, string $edition): ModuleActivationDecision
    {
        return $this->host->check($ctx, $module, $edition);
    }
}

final class MutableHostActivationGate implements ModuleActivationGate
{
    public ?ModuleActivationDecision $denial = null;

    public int $checks = 0;

    #[Override]
    public function check(ContextScopeInterface $ctx, string $module, string $edition): ModuleActivationDecision
    {
        $this->checks++;

        return $this->denial ?? ModuleActivationDecision::allow();
    }
}

final class ActivationGateRefreshObserver
{
    public ?ModuleActivationGate $gate = null;

    public function setGate(ModuleActivationGate $gate): void
    {
        $this->gate = $gate;
    }
}

final class TaskCompletionCaptureTest extends TestCase
{
    use MigratesSchemaSets;
    use RefreshDatabase {
        beginDatabaseTransaction as private beginFixtureTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Public lifecycle commits owner transitions. Rebuild between tests.
            RefreshDatabaseState::$migrated = false;

            return;
        }
        $this->beginFixtureTransaction();
    }

    private const string DEFINITION = 'task-completion';

    private const string VERSION = 'task-completion/7';

    private string $moduleCache;

    private RegistryIntrospection $earlyIntrospection;

    private MutableHostActivationGate $lastPermanentHost;

    private MutableHostActivationGate $warmPermanentHost;

    private ?ActivationGateRefreshObserver $refreshObserver = null;

    private int $permanentHostConstructions = 0;

    private ModuleActivationDecision $firstBootedDecision;

    private bool $registrationScopeReady = false;

    private bool $workspaceEdition = false;

    /** @param Application $app */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, TasksServiceProvider::class, WorkflowServiceProvider::class];
    }

    /** @param Application $app */
    #[Override]
    protected function defineEnvironment($app): void
    {
        $this->configureModuleCache($app);
        $app['config']->set('corex.audit.enabled', false);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');

        $database = getenv('COREX_TASKS_WORKFLOW_PG_DATABASE');

        if ($database === false) {
            return;
        }

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException('COREX_TASKS_WORKFLOW_PG_DATABASE must name a disposable _test database.');
        }

        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => $database,
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'prefix' => '',
        ]);
    }

    private function configureModuleCache(Application $app): void
    {
        if (! isset($this->moduleCache)) {
            $relativeCache = 'bootstrap/cache/test-task-workflow-'.bin2hex(random_bytes(12)).'.php';
            $this->moduleCache = $app->basePath($relativeCache);
            $app['config']->set('modules.cache_path', $relativeCache);
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../../tasks/tests/database/migrations');
    }

    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        $this->migrateSchemaSets(['axiomasoft/corex-tasks:tenant', 'axiomasoft/corex-workflow:tenant'], DB::getDefaultConnection());
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->beforeApplicationDestroyed(function (): void {
            if (is_file($this->moduleCache)) {
                unlink($this->moduleCache);
            }
        });

        $access = Mockery::mock(TaskAccess::class);
        $access->shouldReceive('authorizeCreate')->andReturnNull();
        $this->app->instance(TaskAccess::class, $access);

        $access = Mockery::mock(TaskMutationAccess::class);
        $access->shouldReceive('authorizeComplete')->andReturnNull();
        $this->app->instance(TaskMutationAccess::class, $access);
    }

    public function test_disabled_bridge_allows_normal_completion_without_capture(): void
    {
        $this->registerModules();
        $this->app->register(TasksWorkflowServiceProvider::class);
        $task = $this->openTask();

        $this->complete($task);
        $this->assertTaskStatus($task, 'done');
        self::assertSame(0, DB::table('sys_workflow_intents')->count());
        self::assertSame(0, DB::table('corex_task_operations')->count());
    }

    public function test_enabled_bridge_requires_explicit_definition_and_version_at_boot(): void
    {
        config(['corex-tasks-workflow.enabled' => true]);
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('definition_ref: must be a non-empty stable reference; version_ref: must be a non-empty stable reference');

        $this->app->register(TasksWorkflowServiceProvider::class);
    }

    public function test_enabled_bridge_requires_the_module_registry_at_boot(): void
    {
        $this->app->offsetUnset(ModuleRegistry::class);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires WorkflowBinding and the Packman module registry');

        $this->enableBridge();
    }

    public function test_enabled_bridge_rejects_duplicate_contributor_registration_at_boot(): void
    {
        $this->app->tag('another-capture', TaskCompletionCapture::TAG);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('exactly one TaskCompletionCapture contributor');

        $this->enableBridge();
    }

    public function test_enabled_bridge_preserves_an_instance_only_host_gate(): void
    {
        $this->registerModules();
        $context = resolve(TenantContextResolver::class)->current();
        $denial = ModuleActivationDecision::deny('Host declined edition', 'plus');
        $host = Mockery::mock(ModuleActivationGate::class);
        $host->shouldReceive('check')->once()->with($context, TasksModule::NAME, 'standard')->andReturn($denial);
        $this->app->instance('fixture.permanent-policy', $host);
        $this->app->alias('fixture.permanent-policy', ModuleActivationGate::class);

        $this->enableBridge();
        $gate = resolve(ModuleActivationGate::class);
        self::assertInstanceOf(TasksWorkflowActivationGate::class, $gate);
        $this->app->forgetScopedInstances();

        self::assertSame($gate, resolve(ModuleActivationGate::class));
        self::assertSame($denial, $gate->check($context, TasksModule::NAME, 'standard'));
        self::assertFalse($this->app->resolved(EngineAdapter::class));
    }

    public static function permanentHostBindings(): array
    {
        return [
            'mutable singleton' => ['singleton'],
            'instance-only alias' => ['instance'],
            'manual instance over the Packman factory' => ['overridden'],
            'mutable singleton with a native refresh subscriber' => ['refresh-singleton'],
            'manual instance with a dormant tenant factory and refresh' => ['refresh-manual'],
        ];
    }

    protected function warmPermanentActivationRegistry(Application $app): void
    {
        [$lifetime] = $this->providedData();
        $app['config']->set('corex-tasks-workflow', ['enabled' => true, 'definition_ref' => self::DEFINITION, 'version_ref' => self::VERSION]);

        if (in_array($lifetime, ['singleton', 'refresh-singleton'], true)) {
            $app->singleton(ModuleActivationGate::class, function (): MutableHostActivationGate {
                $this->permanentHostConstructions++;

                return $this->lastPermanentHost = new MutableHostActivationGate;
            });
        } else {
            $this->lastPermanentHost = new MutableHostActivationGate;

            if ($lifetime === 'refresh-manual') {
                $app->singleton(ModuleActivationGate::class, static fn () => throw new LogicException('Dormant tenant factory ran.'));
            }

            if ($lifetime === 'instance') {
                $app->instance('fixture.permanent-policy', $this->lastPermanentHost);
                $app->alias('fixture.permanent-policy', ModuleActivationGate::class);
            } else {
                self::assertArrayHasKey(ModuleActivationGate::class, $app->getBindings());
                $app->instance(ModuleActivationGate::class, $this->lastPermanentHost);
            }
        }

        if (str_starts_with($lifetime, 'refresh-')) {
            $this->refreshObserver = new ActivationGateRefreshObserver;
            $app->refresh(ModuleActivationGate::class, $this->refreshObserver, 'setGate');
        }

        $app->booting(function (Application $app): void {
            $this->writeWarmRegistry($app);
            $this->earlyIntrospection = $app->make(RegistryIntrospection::class);
            self::assertNotEmpty($this->earlyIntrospection->extensionPoints()->extensionPoints);
            $this->warmPermanentHost = $this->lastPermanentHost;
            $this->warmPermanentHost->denial = ModuleActivationDecision::deny('Warm host denied edition.', 'plus');
        });

        $app->register(TasksWorkflowServiceProvider::class);
        $app->register(new class($app) extends ServiceProvider
        {
            public function boot(): void
            {
                $this->app->extend(ModuleActivationGate::class, static fn (ModuleActivationGate $host): ModuleActivationGate => new PassThroughActivationGate($host));
            }
        });
    }

    #[Group('pg')]
    #[DataProvider('permanentHostBindings')]
    #[DefineEnvironment('warmPermanentActivationRegistry')]
    public function test_warm_registry_preserves_permanent_host_gate_identity_and_state(string $lifetime): void
    {
        $context = resolve(TenantContextResolver::class)->current();
        $gate = resolve(ModuleActivationGate::class);
        self::assertInstanceOf(TasksWorkflowActivationGate::class, $gate);
        self::assertSame($this->warmPermanentHost, $this->lastPermanentHost);
        self::assertSame((int) in_array($lifetime, ['singleton', 'refresh-singleton'], true), $this->permanentHostConstructions);
        self::assertNotSame($this->earlyIntrospection, resolve(RegistryIntrospection::class));

        if ($this->refreshObserver !== null) {
            self::assertSame($gate, $this->refreshObserver->gate);
        }

        if ($lifetime === 'instance') {
            self::assertSame($gate, resolve('fixture.permanent-policy'));
        }

        self::assertSame($this->warmPermanentHost->denial, $gate->check($context, TasksModule::NAME, 'standard'));
        $this->app->forgetScopedInstances();

        self::assertSame($gate, resolve(ModuleActivationGate::class));
        self::assertSame($this->warmPermanentHost->denial, $gate->check($context, TasksModule::NAME, 'standard'));
        self::assertSame(2, $this->warmPermanentHost->checks);
        self::assertFalse($this->app->resolved(EngineAdapter::class));
    }

    protected function configureEarlyBootedGateConsumer(Application $app): void
    {
        $this->configureModuleCache($app);
        $this->writeWarmRegistry($app);
        $app['config']->set('corex-tasks-workflow', ['enabled' => true, 'definition_ref' => self::DEFINITION, 'version_ref' => 'unknown-version']);
        $engine = Mockery::mock(EngineAdapter::class);
        $engine->shouldReceive('provider')->twice()->withArgs(static fn (DefinitionRequest $request): bool => $request->context == $app->make(TenantContextResolver::class)->current()
            && $request->module === TasksModule::NAME && $request->entityHandle === 'tsk.task' && $request->eventName === 'completed'
            && $request->definitionRef === self::DEFINITION && $request->versionRef === 'unknown-version')->andReturnNull();
        $app->scoped(EngineAdapter::class, static fn (): EngineAdapter => $engine);
        $app->booted(function (Application $app): void {
            self::assertTrue($app->isBooted());
            $this->firstBootedDecision = $app->make(ModuleActivationGate::class)->check(
                $app->make(TenantContextResolver::class)->current(),
                TasksModule::NAME,
                'standard',
            );
        });
        $app->register(TasksWorkflowServiceProvider::class);
        $app->instance(ModuleActivationGate::class, new MutableHostActivationGate);
    }

    #[Group('pg')]
    #[DefineEnvironment('configureEarlyBootedGateConsumer')]
    public function test_first_booted_listener_checks_the_same_pin_as_the_post_boot_caller(): void
    {
        $current = resolve(ModuleActivationGate::class)->check(resolve(TenantContextResolver::class)->current(), TasksModule::NAME, 'standard');
        self::assertFalse($this->firstBootedDecision->allowed);
        self::assertSame('The pinned task workflow definition and version have no active engine.', $this->firstBootedDecision->reason);
        self::assertEquals($this->firstBootedDecision, $current);
    }

    public function test_complete_uses_the_task_declaration_and_pins_the_confirmed_workflow_intent(): void
    {
        $this->registerModules();
        $this->enableBridge();
        $task = $this->openTask();
        $this->registerEngine();

        $this->complete($task);
        $this->complete($task);

        $this->assertTaskStatus($task, 'done');
        $this->assertCapturedReceipt();
    }

    public function test_identical_prior_workflow_intent_satisfies_first_task_completion(): void
    {
        $this->registerModules();
        $this->enableBridge();
        $task = $this->openTask();
        $this->registerEngine();
        $this->captureFirst($task, 'done');

        $this->complete($task);

        $this->assertTaskStatus($task, 'done');
        $this->assertCapturedReceipt();
    }

    public function test_conflicting_prior_workflow_intent_leaves_task_open_without_receipt(): void
    {
        $this->registerModules();
        $this->enableBridge();
        $task = $this->openTask();
        $this->registerEngine();
        $this->captureFirst($task, 'different');
        $originalIntent = DB::table('sys_workflow_intents')->first();

        try {
            $this->complete($task);
            self::fail('Conflicting workflow intent was accepted.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('Conflicting completion intent', $exception->getMessage());
        }

        $this->assertTaskStatus($task, 'open');
        self::assertEquals([$originalIntent], DB::table('sys_workflow_intents')->get()->all());
        self::assertSame(0, DB::table('corex_task_operations')->count());
    }

    public function test_deactivation_during_completion_refuses_admission(): void
    {
        $this->registerModules([true, true, false]);
        $this->enableBridge();
        $task = $this->openTask();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires the active Tasks module');

        $this->complete($task);
    }

    protected function coldEnabledBridge(Application $app): void
    {
        $app['config']->set('corex-tasks-workflow', ['enabled' => true, 'definition_ref' => self::DEFINITION, 'version_ref' => self::VERSION]);
        $discovery = Mockery::mock(ModuleDiscovery::class);
        $discovery->shouldReceive('discover')->andReturn([(new TasksModule)->manifest()]);
        $app->instance(ModuleDiscovery::class, $discovery);
        $app->scoped(ModuleActivationGate::class, static function (): ModuleActivationGate {
            throw new LogicException('The host activation policy requires a tenant context.');
        });
        $app->scoped(EngineAdapter::class, static function (): EngineAdapter {
            throw new LogicException('The workflow engine requires a tenant context.');
        });
        $app->register(TasksWorkflowServiceProvider::class);
    }

    #[DefineEnvironment('coldEnabledBridge')]
    public function test_cold_activation_validates_installed_declarations_before_packman_publishes_the_cache(): void
    {
        self::assertTrue($this->app->isBooted());
        self::assertFileDoesNotExist($this->moduleCache);

        $this->artisan('modules:compile')->assertSuccessful();

        self::assertFileExists($this->moduleCache);
        self::assertFalse($this->app->resolved(ModuleActivationGate::class));
        self::assertFalse($this->app->resolved(EngineAdapter::class));
    }

    public function test_cold_activation_rejects_installed_tasks_without_the_declaration(): void
    {
        $this->discover([$this->tasksWithoutCompletionTrigger()]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('installed tsk.task/completed trigger owned by Tasks');

        $this->enableBridge();
    }

    public function test_packman_can_refresh_a_stale_cache_when_the_bridge_is_enabled(): void
    {
        $this->discover([$this->tasksWithoutCompletionTrigger()]);
        $this->artisan('modules:compile')->assertSuccessful();
        $before = file_get_contents($this->moduleCache);
        $this->app->forgetInstance(ModuleRegistry::class);
        $this->discover([(new TasksModule)->manifest()]);

        $originalArguments = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['artisan', 'modules:compile'];

        try {
            $this->enableBridge();
            self::assertSame($before, file_get_contents($this->moduleCache));
            $this->artisan('modules:compile')->assertSuccessful();
        } finally {
            $_SERVER['argv'] = $originalArguments;
        }

        self::assertNotSame($before, file_get_contents($this->moduleCache));
    }

    /** @return array<string, array{bool}> */
    public static function unavailableTriggers(): array
    {
        return ['missing declaration' => [false], 'wrong owner' => [true]];
    }

    #[DataProvider('unavailableTriggers')]
    public function test_enabled_boot_refuses_a_compiled_registry_without_the_tasks_owned_trigger(bool $wrongOwner): void
    {
        $manifests = [$this->tasksWithoutCompletionTrigger()];

        if ($wrongOwner) {
            $manifests[] = ModuleManifest::make('fixture/other-owner')
                ->title('Other owner')
                ->description('Declares a trigger for Tasks.')
                ->tablePrefix('foo')
                ->extends([Workflow::trigger('tsk.task', ['completed'])]);
        }

        $this->discover($manifests);
        $this->artisan('modules:compile')->assertSuccessful();
        $this->app->forgetInstance(ModuleRegistry::class);

        // The published registry takes precedence even when the currently installed declarations are valid.
        $this->discover([(new TasksModule)->manifest()]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('compiled tsk.task/completed trigger owned by Tasks');

        $this->enableBridge();
    }

    /** @return array<string, array{string, string, bool}> */
    public static function definitionPins(): array
    {
        return [
            'unknown definition' => ['unknown-definition', self::VERSION, false],
            'unknown version' => [self::DEFINITION, 'unknown-version', false],
            'supported pin' => [self::DEFINITION, self::VERSION, true],
        ];
    }

    #[Group('pg')]
    #[DataProvider('definitionPins')]
    public function test_packman_lifecycle_checks_the_workflow_pin_before_enabling_tasks(string $definition, string $version, bool $available): void
    {
        $this->prepareLifecycle();
        $context = resolve(TenantContextResolver::class)->current();
        $host = Mockery::mock(ModuleActivationGate::class);
        $host->shouldReceive('check')->with(Mockery::on(static fn (mixed $actual): bool => $actual == $context), TasksModule::NAME, 'standard')->twice()->andReturn(
            ModuleActivationDecision::deny('Host edition unavailable.', 'plus'),
            ModuleActivationDecision::allow(),
        );
        $this->app->singleton(ModuleActivationGate::class, static fn (): ModuleActivationGate => $host);
        $engine = Mockery::mock(EngineAdapter::class);
        $engine->shouldReceive('provider')->once()->withArgs(static fn (DefinitionRequest $request): bool => $request->context == $context
            && $request->module === TasksModule::NAME && $request->entityHandle === 'tsk.task' && $request->eventName === 'completed'
            && $request->definitionRef === $definition && $request->versionRef === $version)
            ->andReturn($available ? new EngineProvider('fixture.task-workflow') : null);
        $this->app->scoped(EngineAdapter::class, static fn (): EngineAdapter => $engine);
        $this->enableBridge($definition, $version);
        $lifecycle = resolve(ModuleLifecycle::class);
        $modules = resolve(ModuleRegistry::class);

        $this->assertActivationDenied($lifecycle, 'Host edition unavailable.');
        self::assertSame(ModuleState::Available, $lifecycle->status(TasksModule::NAME)->state);
        self::assertFalse($modules->isActive(TasksModule::NAME, $context));

        if ($available) {
            $lifecycle->enable(TasksModule::NAME, cascade: true);
        } else {
            $this->assertActivationDenied($lifecycle, 'The pinned task workflow definition and version have no active engine.');
        }

        self::assertSame($available ? ModuleState::Enabled : ModuleState::Available, $lifecycle->status(TasksModule::NAME)->state);
        self::assertSame($available, $modules->isActive(TasksModule::NAME, $context));
    }

    private function writeWarmRegistry(Application $app): void
    {
        // The Testbench skeleton has its own Composer metadata. Supply the same
        // Tasks declaration to the compiler and Packman's lifecycle discovery.
        $discovery = new PackmanModuleDiscovery(fallbackModules: [TasksModule::class]);
        $app->instance(ModuleDiscovery::class, $discovery);
        $manifests = $discovery->discover();
        $compiled = $app->make(ModuleCompilation::class)->compile(
            manifests: $manifests,
            installedPackages: [TasksModule::NAME],
            installedVersions: [TasksModule::NAME => new InstalledPackageVersion(TasksModule::NAME, '2.0.0', 'fixture')],
        );
        $cache = [
            'modules' => array_map(static fn (ModuleManifest $manifest): array => CompiledModule::fromManifest($manifest)->toArray(), $manifests),
            'registry' => $compiled->toArray(),
        ];
        file_put_contents($this->moduleCache, '<?php return '.var_export($cache, true).';');
    }

    public static function finalHostRegistrations(): array
    {
        return [
            'reader first supported' => ['reader', 'instance', self::VERSION, false],
            'gate first supported' => ['gate', 'instance', self::VERSION, false],
            'reader first unsupported' => ['reader', 'instance', 'unknown-version', false],
            'gate first unsupported' => ['gate', 'instance', 'unknown-version', false],
            'reader first denied by instance-only alias' => ['reader', 'instance-alias', self::VERSION, true],
            'reader first with rebound scoped factory' => ['reader', 'scoped', self::VERSION, false],
            'reader first with scoped alias' => ['reader', 'scoped-alias', self::VERSION, false],
        ];
    }

    protected function registerFinalHostAfterCatalogue(Application $app): void
    {
        [$order, $wiring, $version, $denies] = $this->providedData();
        $app['config']->set('corex-tasks-workflow', ['enabled' => true, 'definition_ref' => self::DEFINITION, 'version_ref' => $version]);
        $this->configureModuleCache($app);
        $this->writeWarmRegistry($app);
        $app->register(TasksWorkflowServiceProvider::class);

        $app->register(new class($app, $order) extends ServiceProvider
        {
            public function __construct(Application $app, private readonly string $order)
            {
                parent::__construct($app);
            }

            public function register(): void
            {
                if ($this->order === 'reader') {
                    $reader = $this->app->make(RegistryIntrospection::class);
                    $reader->extensionPoints();
                    $this->app->instance('fixture.registration-reader', $reader);
                } else {
                    $this->app->make(ModuleActivationGate::class);
                }
            }
        });

        $host = new MutableHostActivationGate;
        $host->denial = $denies ? ModuleActivationDecision::deny('Final host declined edition.', 'plus') : null;
        $this->lastPermanentHost = $host;
        $factory = fn (): MutableHostActivationGate => $this->registrationScopeReady ? $host : throw new LogicException('Final policy resolved before tenant work.');

        $app->register(new class($app, $wiring, $host, $factory) extends ServiceProvider
        {
            /** @param Closure(): MutableHostActivationGate $factory */
            public function __construct(Application $app, private readonly string $wiring, private readonly ModuleActivationGate $host, private readonly Closure $factory)
            {
                parent::__construct($app);
            }

            public function register(): void
            {
                $binding = str_ends_with($this->wiring, '-alias') ? 'fixture.final-policy' : ModuleActivationGate::class;

                if (str_starts_with($this->wiring, 'scoped')) {
                    $this->app->scoped($binding, $this->factory);
                } else {
                    $this->app->instance($binding, $this->host);
                }

                if ($binding !== ModuleActivationGate::class) {
                    $this->app->alias($binding, ModuleActivationGate::class);
                }
            }
        });
    }

    #[Group('pg')]
    #[DataProvider('finalHostRegistrations')]
    #[DefineEnvironment('registerFinalHostAfterCatalogue')]
    public function test_lifecycle_validates_the_final_host_after_catalogue_registration(string $order, string $wiring, string $version, bool $denies): void
    {
        $this->migrateLifecycleSchema();
        $this->registrationScopeReady = true;
        $context = resolve(TenantContextResolver::class)->current();
        $available = $version === self::VERSION;
        $gate = resolve(ModuleActivationGate::class);

        if (str_ends_with($wiring, '-alias')) {
            self::assertSame($gate, resolve('fixture.final-policy'));
        }

        if ($denies) {
            self::assertSame($this->lastPermanentHost->denial, $gate->check($context, TasksModule::NAME, 'standard'));
        } else {
            $engine = Mockery::mock(EngineAdapter::class);
            $engine->shouldReceive('provider')->times($order === 'reader' && $available ? 2 : 1)
                ->withArgs(static fn (DefinitionRequest $request): bool => $request->context == $context
                    && $request->module === TasksModule::NAME && $request->entityHandle === 'tsk.task' && $request->eventName === 'completed'
                    && $request->definitionRef === self::DEFINITION && $request->versionRef === $version)
                ->andReturn($available ? new EngineProvider('fixture.final-policy') : null);
            $this->app->scoped(EngineAdapter::class, static fn (): EngineAdapter => $engine);
        }

        $lifecycle = resolve(ModuleLifecycle::class);

        if ($available && ! $denies) {
            $lifecycle->enable(TasksModule::NAME, cascade: true);
            self::assertTrue(resolve(ModuleRegistry::class)->isActive(TasksModule::NAME, $context));

            if ($order === 'reader') {
                self::assertSame('active', resolve('fixture.registration-reader')->why(TasksModule::NAME, $context)->status);
            }
        } else {
            $this->assertActivationDenied($lifecycle, $denies ? 'Final host declined edition.' : 'The pinned task workflow definition and version have no active engine.');
            self::assertSame(ModuleState::Available, $lifecycle->status(TasksModule::NAME)->state);

            foreach (['tsk_tasks', 'corex_task_operations', 'sys_workflow_intents', 'mod_modules', 'mod_lifecycle_log'] as $table) {
                self::assertSame(0, DB::table($table)->count(), $table);
            }
        }

        self::assertSame($denies || ($order === 'reader' && $available) ? 2 : 1, $this->lastPermanentHost->checks);

        if ($denies) {
            self::assertFalse($this->app->resolved(EngineAdapter::class));
        }
    }

    protected function warmActivationRegistry(Application $app): void
    {
        [$definition, $version] = $this->providedData();
        $app['config']->set('corex-tasks-workflow', ['enabled' => true, 'definition_ref' => $definition, 'version_ref' => $version]);
        $app->scoped('fixture.activation-policy', fn (): ModuleActivationGate => new class($this->workspaceEdition) implements ModuleActivationGate
        {
            public function __construct(private readonly bool $workspaceEdition) {}

            public function check(ContextScopeInterface $ctx, string $module, string $edition): ModuleActivationDecision
            {
                return $edition === 'restricted' || ($module === TasksModule::NAME && ! $this->workspaceEdition)
                    ? ModuleActivationDecision::deny('Host edition unavailable.', 'plus')
                    : ModuleActivationDecision::allow();
            }
        });
        $app->alias('fixture.activation-policy', ModuleActivationGate::class);
        $app->booting(function (Application $app): void {
            $this->writeWarmRegistry($app);

            self::assertFalse($app->resolved(ModuleActivationGate::class));
            $this->earlyIntrospection = $app->make(RegistryIntrospection::class);
            self::assertNotEmpty($this->earlyIntrospection->extensionPoints()->extensionPoints);
            self::assertTrue($app->resolved(ModuleActivationGate::class));
        });

        $app->register(TasksWorkflowServiceProvider::class);
        $app->register(new class($app) extends ServiceProvider
        {
            public function boot(Application $app): void
            {
                $binding = $app->getAlias(ModuleActivationGate::class);
                $current = $app->make(ModuleActivationGate::class);
                $app->forgetInstance($binding);
                $app->extend($binding, static fn (ModuleActivationGate $host): ModuleActivationGate => new PassThroughActivationGate($host));
                $app->instance($binding, new PassThroughActivationGate($current));

                $readerFactory = $app->getBindings()[RegistryIntrospection::class]['concrete'];
                $app->singleton('fixture.registry-reader', $readerFactory);
                $app->alias('fixture.registry-reader', RegistryIntrospection::class);
            }
        });
    }

    #[Group('pg')]
    #[DataProvider('definitionPins')]
    #[DefineEnvironment('warmActivationRegistry')]
    public function test_warm_registry_boot_preserves_the_scoped_host_gate_across_a_reset(string $definition, string $version, bool $available): void
    {
        $this->migrateLifecycleSchema();
        $gateAtBoot = resolve(ModuleActivationGate::class);
        self::assertInstanceOf(TasksWorkflowActivationGate::class, $gateAtBoot);

        $context = resolve(TenantContextResolver::class)->current();
        $engine = Mockery::mock(EngineAdapter::class);
        $engine->shouldReceive('provider')->times($available ? 5 : 1)->withArgs(static fn (DefinitionRequest $request): bool => $request->context == $context
            && $request->module === TasksModule::NAME && $request->entityHandle === 'tsk.task' && $request->eventName === 'completed'
            && $request->definitionRef === $definition && $request->versionRef === $version)
            ->andReturn($available ? new EngineProvider('fixture.task-workflow') : null);
        $this->app->scoped(EngineAdapter::class, static fn (): EngineAdapter => $engine);
        $this->workspaceEdition = true;
        $this->app->forgetScopedInstances();
        $lifecycle = resolve(ModuleLifecycle::class);
        $modules = resolve(ModuleRegistry::class);

        if ($available) {
            $lifecycle->enable(TasksModule::NAME, cascade: true);
        } else {
            $this->assertActivationDenied($lifecycle, 'The pinned task workflow definition and version have no active engine.');
        }

        $gateInScope = resolve(ModuleActivationGate::class);
        self::assertInstanceOf(TasksWorkflowActivationGate::class, $gateInScope);
        self::assertNotSame($gateAtBoot, $gateInScope);
        $hostDenial = $gateInScope->check($context, TasksModule::NAME, 'restricted');
        self::assertSame([false, 'Host edition unavailable.', 'plus'], [$hostDenial->allowed, $hostDenial->reason, $hostDenial->upgradeHint]);
        self::assertTrue($gateInScope->check($context, 'fixture/other', 'standard')->allowed);
        $introspection = resolve(RegistryIntrospection::class);
        self::assertSame($introspection, resolve('fixture.registry-reader'));
        self::assertNotSame($this->earlyIntrospection, $introspection);
        self::assertSame($available ? 'active' : 'inactive', $introspection->why(TasksModule::NAME, $context)->status);
        self::assertSame($available ? ModuleState::Enabled : ModuleState::Available, $lifecycle->status(TasksModule::NAME)->state);
        self::assertSame($available, $modules->isActive(TasksModule::NAME, $context));

        if ($available) {
            self::assertSame('active', $this->earlyIntrospection->why(TasksModule::NAME, $context)->status);
            self::assertSame($introspection, resolve(RegistryIntrospection::class));
            $this->workspaceEdition = false;
            $this->app->forgetScopedInstances();
            $accountIntrospection = resolve(RegistryIntrospection::class);
            self::assertNotSame($introspection, $accountIntrospection);
            self::assertSame('inactive', $accountIntrospection->why(TasksModule::NAME, $context)->status);
            self::assertSame('inactive', $this->earlyIntrospection->why(TasksModule::NAME, $context)->status);
            $denied = $gateAtBoot->check($context, TasksModule::NAME, 'standard');
            self::assertSame([false, 'Host edition unavailable.', 'plus'], [$denied->allowed, $denied->reason, $denied->upgradeHint]);

            $this->workspaceEdition = true;
            $this->app->forgetScopedInstances();
            self::assertSame('active', resolve(RegistryIntrospection::class)->why(TasksModule::NAME, $context)->status);
            self::assertSame('active', $this->earlyIntrospection->why(TasksModule::NAME, $context)->status);
        }
    }

    #[Group('pg')]
    public function test_unsupported_pin_in_new_bridge_configuration_leaves_the_task_open_after_earlier_activation(): void
    {
        $this->prepareLifecycle();
        resolve(ModuleLifecycle::class)->enable(TasksModule::NAME, cascade: true);
        $this->enableBridge(version: 'unknown-version');
        $task = $this->openTask();
        $this->registerEngine();

        try {
            $this->complete($task);
            self::fail('Unsupported workflow version was accepted.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('no active engine', $exception->getMessage());
        }

        $this->assertTaskStatus($task, 'open');
        self::assertSame(0, DB::table('sys_workflow_intents')->count());
        self::assertSame(0, DB::table('corex_task_operations')->count());
    }

    public function test_bridge_rejects_a_different_connection_even_with_an_active_transaction(): void
    {
        $this->registerModules();
        $this->enableBridge();
        $task = $this->openTask();
        $context = resolve(TenantContextResolver::class)->current();
        $scope = WorkScopeFixture::install($this->app, principalId: $task->actor->id)->scope();
        $candidate = new TaskCompletionCandidate($context, $scope, $task->taskId, TaskStatus::Open, TaskStatus::Done, 'task-capture', $task->actor);
        [$capture] = iterator_to_array($this->app->tagged(TaskCompletionCapture::TAG));
        config(['database.connections.foreign' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        $foreign = DB::connection('foreign');

        $this->expectException(ScopeContextMismatch::class);

        $foreign->transaction(fn () => $capture->capture($candidate, $foreign));
    }

    private function prepareLifecycle(): void
    {
        $this->migrateLifecycleSchema();
        $this->discover([(new TasksModule)->manifest()]);
        $this->artisan('modules:compile')->assertSuccessful();
        $this->app->forgetInstance(ModuleRegistry::class);
    }

    private function migrateLifecycleSchema(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            self::markTestSkipped('Packman module lifecycle uses PostgreSQL transaction locks.');
        }

        $context = resolve(TenantContextResolver::class)->current();
        $scope = WorkScopeFixture::install($this->app, principalId: 'task-lifecycle-operator')->scope($context);
        foreach (['enable', 'inspect'] as $ability) {
            Gate::define('corex.lifecycle.'.$ability,
                static fn (ScopePrincipal $actor, DataScope $actual, ModuleRef $target): bool => $actor->kind === 'user' && $actor->id === $scope->principalId
                    && get_object_vars($actual) === get_object_vars($scope) && $target->package === TasksModule::NAME);
        }
        $this->migrateSchemaSets(['axiomasoft/packman-modules:tenant'], DB::getDefaultConnection());
    }

    private function assertActivationDenied(ModuleLifecycle $lifecycle, string $message): void
    {
        try {
            $lifecycle->enable(TasksModule::NAME, cascade: true);
            self::fail('Tasks activation was accepted.');
        } catch (AuthorizationException $exception) {
            self::assertSame($message, $exception->getMessage());
        }
    }

    private function enableBridge(string $definition = self::DEFINITION, string $version = self::VERSION): void
    {
        config(['corex-tasks-workflow' => ['enabled' => true, 'definition_ref' => $definition, 'version_ref' => $version]]);
        $this->app->register(TasksWorkflowServiceProvider::class);
    }

    /** @param list<bool> $activation */
    private function registerModules(array $activation = [true]): void
    {
        $manifest = (new TasksModule)->manifest();
        $registry = resolve(ModuleCompilation::class)->compile(
            manifests: [$manifest],
            installedPackages: [TasksModule::NAME],
            installedVersions: [TasksModule::NAME => new InstalledPackageVersion(TasksModule::NAME, '2.0.0', 'fixture')],
        );

        $modules = Mockery::mock(ModuleRegistry::class);
        $modules->shouldReceive('all')->andReturn([CompiledModule::fromManifest($manifest)]);
        $modules->shouldReceive('isActive')->with(TasksModule::NAME, Mockery::any())->andReturn(...$activation);
        $modules->shouldReceive('compiled')->andReturn($registry);
        $this->app->instance(ModuleRegistry::class, $modules);
    }

    private function tasksWithoutCompletionTrigger(): ModuleManifest
    {
        $tasks = (new TasksModule)->manifest();
        $tasks->extenders = array_values(array_filter($tasks->extenders, static fn (object $extender): bool => ! $extender instanceof Workflow));

        return $tasks;
    }

    /** @param list<ModuleManifest> $manifests */
    private function discover(array $manifests): void
    {
        $discovery = Mockery::mock(ModuleDiscovery::class);
        $discovery->shouldReceive('discover')->andReturn($manifests);
        $this->app->instance(ModuleDiscovery::class, $discovery);
    }

    private function registerEngine(): void
    {
        $engine = Mockery::mock(EngineAdapter::class);
        $engine->shouldReceive('provider')->withArgs(static fn (DefinitionRequest $request): bool => $request->module === TasksModule::NAME
            && $request->entityHandle === 'tsk.task'
            && $request->eventName === 'completed')->andReturnUsing(static fn (DefinitionRequest $request): ?EngineProvider => $request->definitionRef === self::DEFINITION && $request->versionRef === self::VERSION ? new EngineProvider('fixture.task-workflow') : null);
        $this->app->instance(EngineAdapter::class, $engine);
    }

    private function openTask(): CompleteTaskData
    {
        $workspace = (string) Str::uuid7();
        $actorId = (string) Str::uuid7();
        DB::table('wsp_workspaces')->insert(['id' => $workspace, 'name' => 'Capture']);
        DB::table('users')->insert(['id' => $actorId, 'name' => 'Actor']);
        WorkScopeFixture::install($this->app, principalId: $actorId)->scope();
        $actor = new ActorData(ActorType::User, $actorId);
        $task = resolve(CreateTaskCommand::class)->handle(new TaskDraft(workspaceId: $workspace, title: 'Atomic capture', actor: $actor));

        return new CompleteTaskData($task->id, $actor);
    }

    private function complete(CompleteTaskData $task): void
    {
        resolve(CompleteTaskCommand::class)->handle($task);
    }

    private function captureFirst(CompleteTaskData $task, string $status): void
    {
        $scope = WorkScopeFixture::install($this->app, principalId: $task->actor->id)->scope();
        $context = resolve(TenantContextResolver::class)->current();
        $taskRef = 'task:'.$task->taskId;
        $requestKey = hash('sha256', json_encode(['completion-capture/2', $scope->accountId, $scope->accountIncarnation, $task->taskId], JSON_THROW_ON_ERROR));
        $request = new TriggerRequest(
            context: $context,
            scope: $scope,
            module: TasksModule::NAME,
            entityHandle: 'tsk.task',
            eventId: $taskRef,
            eventName: 'completed',
            subjectRef: $taskRef,
            definitionRef: self::DEFINITION,
            versionRef: self::VERSION,
            scalarSnapshot: JsonDocuments::capture(['taskId' => $task->taskId, 'status' => $status]),
            requestKey: $requestKey,
        );

        $owner = DB::connection();
        $result = $owner->transaction(fn () => resolve(WorkflowBinding::class)->capture($request, $owner));

        self::assertSame(CaptureStatus::Created, $result->status);
    }

    private function assertTaskStatus(CompleteTaskData $task, string $status): void
    {
        self::assertSame($status, DB::table('tsk_tasks')->where('id', $task->taskId)->value('status'));
    }

    private function assertCapturedReceipt(): void
    {
        $intents = DB::table('sys_workflow_intents')->get();
        self::assertCount(1, $intents);
        $intent = $intents->sole();
        self::assertSame(self::DEFINITION, $intent->definition_ref);
        self::assertSame(self::VERSION, $intent->version_ref);
        self::assertSame('pending', $intent->state);

        $receipts = DB::table('corex_task_operations')->where('kind', 'completionCapture')->get();
        self::assertCount(1, $receipts);
        $receipt = $receipts->sole();
        self::assertSame($intent->payload_hash, $receipt->payload_hash);
        $reference = json_decode((string) $receipt->result_ref, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($intent->id, $reference['intentRef']);
        self::assertSame(self::VERSION, $reference['versionRef']);
    }
}
