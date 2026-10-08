<?php

declare(strict_types=1);

// Short diagnostic reproductions. No full test suite, mutation or network delivery.
require dirname(__DIR__, 2).'/vendor/autoload.php';

// Testbench teardown expects the ordinary PHPUnit configuration registry.
PHPUnit\TextUI\Configuration\Registry::init(
    new PHPUnit\TextUI\CliArguments\Builder()->fromParameters([]),
    new PHPUnit\TextUI\XmlConfiguration\Loader()->load(dirname(__DIR__, 2).'/phpunit.xml'),
);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Database\Data\SchemaSet;
use CoreX\Database\Enums\SchemaLifecycle;
use CoreX\Database\Enums\SchemaScope;
use CoreX\Database\Internal\TaggedSchemaSets;
use CoreX\Database\Internal\LaravelSchemaMigrator;
use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Messaging\Internal\JsonCloudEventCodec;
use CoreX\Messaging\Internal\SqlInbox;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\CompiledModule;
use CoreX\Modules\Enums\DisableReason;
use CoreX\Modules\Internal\Discovery\ModuleDiscovery;
use CoreX\Modules\Internal\Lifecycle\DatabaseModuleLifecycle;
use CoreX\Modules\Internal\Lifecycle\DatabaseRecordsRegistrar;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Testing\MutatingHandler;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Tests\Feature\ToolInvocationTest;
use CoreX\Webhooks\Internal\WebhookFanout;
use CoreX\Webhooks\Internal\WebhookMessagePublisher;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

function method(object $object, string $name, mixed ...$arguments): mixed
{
    return new ReflectionMethod($object, $name)->invoke($object, ...$arguments);
}

function result(array $data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
}

final class AuditHarness extends CoreX\Testing\CoreXTestCase
{
    public function start(): void { parent::setUp(); }
    public function stop(): void { parent::tearDown(); }
}

final class DigestAuditHarness extends CoreX\Testing\CoreXTestCase
{
    public function start(): void { parent::setUp(); }
    public function stop(): void { parent::tearDown(); }
    protected function getPackageProviders($app): array
    {
        $app['config']->set('corex-notifications.digest.enabled', true);
        return [CoreX\CoreServiceProvider::class, CoreX\Wakeup\WakeupServiceProvider::class, CoreX\Notifications\NotificationsServiceProvider::class];
    }
}

final class TaskAuditHarness extends CoreX\TasksWorkflow\Tests\TestCase
{
    public static bool $wakeup = false;
    public static bool $hostHandler = false;
    public function start(): void { parent::setUp(); }
    public function stop(): void { parent::tearDown(); }
    protected function getPackageProviders($app): array
    {
        $app['config']->set('corex-tasks-workflow.wakeup.enabled', self::$wakeup);
        if (self::$hostHandler) {
            $app['config']->set('corex-wakeup.handlers', ['task-recurrence.materialize' => [
                'handler' => CoreX\TasksWorkflow\Internal\MaterializationWakeupHandler::class,
                'version' => CoreX\TasksWorkflow\Internal\MaterializationHasher::HANDLER_VERSION,
            ]]);
        }
        $providers = parent::getPackageProviders($app);
        if (self::$wakeup) { $providers[] = CoreX\Wakeup\WakeupServiceProvider::class; }
        return $providers;
    }
}

$case = $argv[1] ?? '';
if (in_array($case, ['task-resume', 'task-full-control', 'task-wakeup', 'task-wakeup-no-handler'], true)) {
    TaskAuditHarness::$wakeup = str_starts_with($case, 'task-wakeup');
    TaskAuditHarness::$hostHandler = $case === 'task-wakeup';
    $test = new TaskAuditHarness('audit');
    $test->start();
    try {
        app(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-tasks:tenant', 'axiomasoft/corex-tasks-workflow:tenant']);
        if (TaskAuditHarness::$wakeup) {
            app(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-wakeup:tenant']);
        }
        $workspace = (string) Illuminate\Support\Str::uuid7();
        $user = (string) Illuminate\Support\Str::uuid7();
        DB::table('wsp_workspaces')->insert(['id' => $workspace, 'name' => 'Audit']);
        DB::table('users')->insert(['id' => $user, 'name' => 'Audit']);
        $actor = new CoreX\Tasks\Task\Data\ActorData(CoreX\Audit\Enums\ActorType::User, $user);
        $project = CoreX\Tasks\Project\Internal\Models\Project::query()->create([
            'workspace_id' => $workspace, 'name' => 'Audit', 'created_by' => CoreX\Data\Laravel\JsonDocuments::decodeTree($actor->toArray()),
        ]);
        $stage = CoreX\Tasks\Task\Internal\Models\Stage::query()->create(['project_id' => $project->getKey(), 'code' => 'todo', 'name' => ['en' => 'Todo']]);
        $template = new CoreX\Tasks\Task\Data\TaskDraft($workspace, 'Audit recurring', $actor,
            projectId: (string) $project->getKey(), stageId: (string) $stage->getKey());
        $epoch = str_repeat('a', 64);
        $series = CoreX\TasksWorkflow\Tests\Support\SeriesSeeder::seed('audit-series',
            CoreX\TasksWorkflow\Tests\Support\SeriesSeeder::dailySet('audit-series', '1', 6), $template, $epoch);
        $scope = CoreX\Data\Data\DataScope::fromTenantContext(app(TenantContextResolver::class)->current(), 'sqlite::memory:', $user, $epoch);
        $scheduler = null;
        if (TaskAuditHarness::$wakeup) {
            app(ModuleRegistry::class)->shouldReceive('compiled')->andReturn(CoreX\Modules\Data\CompiledRegistry::empty());
            $scheduler = new class(app(CoreX\Scheduling\Contracts\WakeupScheduler::class)) implements CoreX\Scheduling\Contracts\WakeupScheduler {
                public array $statuses = [];
                public function __construct(private CoreX\Scheduling\Contracts\WakeupScheduler $inner) {}
                public function schedule(CoreX\Scheduling\Data\WakeupRequest $request): CoreX\Scheduling\Data\ScheduleResult
                {
                    $result = $this->inner->schedule($request);
                    $this->statuses[] = $result->status;
                    return $result;
                }
                public function cancel(CoreX\Context\Data\TenantContext $context, string $requestKey): CoreX\Scheduling\Data\CancelResult
                { return $this->inner->cancel($context, $requestKey); }
            };
            app()->instance(CoreX\Scheduling\Contracts\WakeupScheduler::class, $scheduler);
        }
        $windows = TaskAuditHarness::$wakeup
            ? [new CoreX\TasksWorkflow\Data\MaterializationWindow('2026-09-01', '2026-09-04', pageLimit: 10),
               new CoreX\TasksWorkflow\Data\MaterializationWindow('2026-09-04', '2026-09-08', pageLimit: 10)]
            : ($case === 'task-full-control'
                ? [new CoreX\TasksWorkflow\Data\MaterializationWindow('2026-09-01', '2026-09-10', pageLimit: 10)]
                : array_fill(0, 3, new CoreX\TasksWorkflow\Data\MaterializationWindow('2026-09-01', '2026-09-10', pageLimit: 2)));
        $materializer = app(CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand::class);
        $results = [];
        foreach ($windows as $i => $window) {
            $token = new CoreX\Data\Data\WriteToken('audit-page-'.$i, CoreX\TasksWorkflow\Internal\MaterializationHasher::payload($scope, $series, $window), '1');
            try {
                $receipt = $materializer->materialize($scope, $series, $window, $token);
            } catch (Throwable $error) {
                if ($case !== 'task-wakeup-no-handler') { throw $error; }
                $results[] = ['error' => $error->getMessage(), 'operation_receipt_committed' => app(CoreX\Tasks\Planning\Contracts\TaskOperationReceipts::class)->find(DB::connection(), $token->operationKey) !== null];
                break;
            }
            $results[] = ['created' => $receipt->created, 'noop' => $receipt->noop, 'cursor' => $receipt->cursor?->resumePosition];
        }
        $data = ['case' => $case, 'expected_tasks' => 6, 'actual_tasks' => CoreX\Tasks\Task\Internal\Models\Task::query()->count(), 'calls' => $results];
        if ($scheduler !== null) {
            $data['schedule_statuses'] = $scheduler->statuses;
            $data['wakeup_requests'] = DB::table('sys_wakeup_requests')->get(['request_key', 'fire_at', 'status'])->all();
        }
        result($data);
    } finally { $test->stop(); }
    exit;
}

if ($case === 'digest-stamp') {
    $test = new DigestAuditHarness('audit');
    $test->start();
    try {
        app(SchemaMigrator::class)->migrateSets(['axiomasoft/corex-wakeup:tenant', 'axiomasoft/corex-notifications:tenant']);
        $preferences = Mockery::mock(CoreX\Notifications\Preferences\Contracts\PreferenceRepository::class);
        $preferences->shouldReceive('get')->andReturn(new CoreX\Notifications\Preferences\Data\NotificationPreference('1', ['in_app'], 'hourly'));
        app()->instance(CoreX\Notifications\Preferences\Contracts\PreferenceRepository::class, $preferences);
        $user = '11111111-1111-7111-8111-111111111112';
        DB::table('ntf_notifications')->insert(['id' => '11111111-1111-7111-8111-111111111113', 'recipient_kind' => 'user', 'recipient_id' => $user,
            'request_key' => 'audit', 'request_digest' => str_repeat('a', 64), 'event_type' => 'document.ready', 'template_key' => 'document-ready',
            'payload' => '{}', 'revision' => '1', 'created_at' => '2026-10-07 00:30:00', 'updated_at' => '2026-10-07 00:30:00']);
        $context = new CoreX\Notifications\Inbox\Data\NotificationContext(app(TenantContextResolver::class)->current(), 'user', $user);
        $digest = app(CoreX\Notifications\Digest\Contracts\DigestBuilder::class)->build($context,
            new CoreX\Notifications\Digest\Data\DigestWindow(new DateTimeImmutable('2026-10-07T00:00:00Z'), new DateTimeImmutable('2026-10-07T01:00:00Z'), 'hourly'));
        $payload = json_decode(DB::table('sys_wakeup_requests')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        $payload['_corex']['extension']['version'] = 'obsolete-version';
        DB::table('sys_wakeup_requests')->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
        $sweep = app(CoreX\Wakeup\Contracts\DueWakeupSweeper::class)->sweep($context->tenant, 10);
        result(['case' => $case, 'wakeup_failure' => DB::table('sys_wakeup_requests')->value('failure_code'),
            'wakeup_state' => DB::table('sys_wakeup_requests')->value('status'), 'digest_state' => DB::table('ntf_digest_runs')->where('id', $digest->id)->value('state'),
            'delivery_rows' => DB::table('ntf_deliveries')->count(), 'sweep_failed' => $sweep->failed, 'sweep_completed' => $sweep->completed]);
    } finally { $test->stop(); }
    exit;
}

if ($case === 'tool-output') {
    $test = new ToolInvocationTest('test_output_schema_mismatch_rolls_back_local_transaction_invoke_08');
    method($test, 'setUp');
    try {
        (new ReflectionProperty($test, 'policy'))->getValue($test)->allowInvoke = true;
        $guard = (new ReflectionProperty($test, 'guard'))->getValue($test);
        $context = method($test, 'context');
        $call = method($test, 'makeToolCall', 'notes.create', ['name' => 'audit', 'operation_key' => 'audit-op'], 'audit-op');
        $guard->approvedProofDigest = $guard->bindingDigest(method($test, 'descriptor', 'notes.create'), $context, $call);
        app()->instance(MutatingHandler::class, new class implements OperationHandler {
            public function handle(RequestContext $context, array $input, array $parameters): array
            {
                DB::table('tool_effects')->insert(['account_id' => 'A', 'operation_key' => 'audit-op', 'payload' => '{}', 'created_at' => '2026-10-08 00:00:00']);
                return ['unexpected' => 'field'];
            }
        });
        $invoker = app(ToolInvoker::class);
        $first = $invoker->invoke($context, $call);
        $second = $invoker->invoke($context, $call);
        result(['case' => $case, 'first_outcome' => $first->outcome->value, 'first_error' => $first->errorCode,
            'retry_outcome' => $second->outcome->value, 'committed_effect_rows' => DB::table('tool_effects')->count(),
            'idempotency_rows' => DB::table('tool_idempotency')->count()]);
    } finally {
        method($test, 'tearDown');
    }
    exit;
}

$harness = new AuditHarness('audit');
$harness->start();
try {
    if ($case === 'webhook-unmapped') {
        $connection = DB::connection(); // isolated SQLite :memory:
        $connection->getSchemaBuilder()->create('sys_inbox_messages', static function (Blueprint $table): void {
            $table->string('consumer'); $table->string('message_id'); $table->string('name'); $table->timestampTz('processed_at');
            $table->primary(['consumer', 'message_id']);
        });
        $contracts = new ArrayMessageContractRegistry([
            new MessageContract('internal.changed', 1, MessageClass::IntegrationEvent, 'example/app', PiiClass::None, 'internal'),
        ]);
        $clock = app(ClockInterface::class);
        $fanout = new WebhookFanout($connection, new SqlInbox($connection, $clock),
            new WebhookTopicCatalogue($contracts, [], []), [], $clock, new JsonCloudEventCodec($contracts));
        $forward = new class implements DurableMessagePublisher {
            public int $calls = 0;
            public function publish(MessageEnvelope $message): void { $this->calls++; }
        };
        $publisher = new WebhookMessagePublisher($forward, $fanout);
        $error = null;
        try {
            $publisher->publish(new MessageEnvelope('11111111-1111-4111-8111-111111111111', 'example/app', 'internal.changed', 1,
                $clock->now(), ['account' => 'boxed'], MessagePayload::empty(), PiiClass::None));
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
        result(['case' => $case, 'forward_calls' => $forward->calls, 'error' => $error,
            'inbox_rows' => $connection->table('sys_inbox_messages')->count()]);
    } elseif ($case === 'search-fields') {
        $seen = [];
        $schema = new CoreX\Search\Data\SearchSnapshot('employee', str_repeat('a', 64), 'employees', 'id', ['name'], resultColumns: ['id', 'name', 'salary']);
        $record = new CoreX\Data\Data\RecordRef('employee', 'worker-1');
        $scope = CoreX\Data\Data\DataScope::fromTenantContext(app(TenantContextResolver::class)->current(),
            'boxed:00000000-0000-7000-8000-000000000000', 'user-1', str_repeat('b', 64));
        $authorizer = new CoreX\Data\Internal\CallableDataScopeAuthorizer(
            static function ($scope, $entity, $id, $fields, $action) use (&$seen): bool {
                $seen[] = $fields;
                return ! in_array('salary', $fields, true);
            },
        );
        $hydrator = new CoreX\Search\Internal\Query\PolicySearchHydrator(
            static fn () => new CoreX\Search\Data\CurrentSearchRecord($record, $schema->schemaHash, str_repeat('c', 64),
                ['id' => 'worker-1', 'name' => 'Example', 'salary' => 100000]), $authorizer,
        );
        $page = $hydrator->authorize($scope, new CoreX\Search\Data\CandidatePage([new CoreX\Search\Data\Candidate($record)], null, false), $schema);
        result(['case' => $case, 'fields_seen_by_authorizer' => $seen, 'returned_records' => $page->records]);
    } elseif ($case === 'digest-starvation') {
        $connection = DB::connection();
        $connection->getSchemaBuilder()->create('ntf_digest_runs', static function (Blueprint $table): void {
            $table->string('id')->primary(); $table->string('recipient_kind'); $table->string('recipient_id');
            $table->string('period'); $table->timestamp('period_start'); $table->timestamp('period_end'); $table->string('state');
        });
        foreach ([['old', '2026-10-07 01:00:00', 'skipped'], ['new', '2026-10-07 02:00:00', 'frozen']] as [$id, $end, $state]) {
            $connection->table('ntf_digest_runs')->insert(['id' => $id, 'recipient_kind' => 'user', 'recipient_id' => 'u1',
                'period' => 'hourly', 'period_start' => '2026-10-07 00:00:00', 'period_end' => $end, 'state' => $state]);
        }
        $builder = new class implements CoreX\Notifications\Digest\Contracts\DigestBuilder {
            public array $windows = [];
            public function build(CoreX\Notifications\Inbox\Data\NotificationContext $context,
                CoreX\Notifications\Digest\Data\DigestWindow $window): ?CoreX\Notifications\Digest\Data\DigestRef
            {
                $this->windows[] = $window->end->format('H:i');
                return new CoreX\Notifications\Digest\Data\DigestRef('old',
                    new CoreX\Notifications\Delivery\Data\RecipientReference('user', 'u1'), $window, 0);
            }
        };
        $handler = new CoreX\Notifications\Digest\Internal\DigestWakeupHandler($connection,
            Mockery::mock(CoreX\Notifications\Preferences\Contracts\PreferenceRepository::class), app(ClockInterface::class));
        $source = new CoreX\Notifications\Digest\Internal\NotificationDigestDueSource($connection, $builder, $handler);
        $context = app(TenantContextResolver::class)->current();
        for ($i = 0; $i < 3; $i++) {
            $source->sweep($context, new DateTimeImmutable('2026-10-08T00:00:00Z'), 1);
        }
        result(['case' => $case, 'selected_periods' => $builder->windows,
            'new_run_state' => $connection->table('ntf_digest_runs')->where('id', 'new')->value('state'),
            'next_due' => $source->nextDue($context)?->format(DATE_ATOM)]);
    } elseif ($case === 'recurrence-resume') {
        $set = CoreX\Recurrence\Data\RecurrenceSet::create('audit-series', '1',
            new CoreX\Recurrence\Data\DateStart('UTC', '2026-10-01T00:00:00Z', null), 'FREQ=DAILY;COUNT=6');
        $window = new CoreX\Recurrence\Data\RecurrenceWindow('UTC', '2026-10-01T00:00:00Z', '2026-10-08T00:00:00Z');
        $limits = new CoreX\Recurrence\Data\ExpansionLimits(emitted: 2, candidateSteps: 2, memoryMiB: 256);
        $expander = new CoreX\Recurrence\Internal\RRuleRecurrenceExpander;
        $cursor = null;
        $pages = [];
        for ($i = 0; $i < 10; $i++) {
            $page = $expander->expand($set, $window, $limits, $cursor);
            $pages[] = ['status' => $page->status, 'occurrences' => count($page->occurrences), 'scanned' => $page->scanned];
            $cursor = $page->nextCursor;
            if ($cursor === null) { break; }
        }
        $control = $expander->expand($set, $window, new CoreX\Recurrence\Data\ExpansionLimits(emitted: 100, candidateSteps: 100, memoryMiB: 256));
        result(['case' => $case, 'expected_occurrences' => 6, 'full_budget_control' => count($control->occurrences),
            'returned_occurrences' => array_sum(array_column($pages, 'occurrences')), 'pages' => $pages]);
    } elseif (in_array($case, ['preinstalled-module', 'module-direct-control'], true)) {
        $database = getenv('PGSQL_DATABASE');
        if ($database !== 'corex_kernel_audit_test') {
            throw new RuntimeException('This reproduction only accepts PGSQL_DATABASE=corex_kernel_audit_test.');
        }
        config(['database.default' => 'pgsql', 'corex.modules.connection' => 'pgsql']);
        $connection = DB::connection('pgsql');
        if ($connection->getDatabaseName() !== $database) {
            throw new RuntimeException('Unexpected resolved database.');
        }
        $connection->statement('DROP SCHEMA IF EXISTS public CASCADE');
        $connection->statement('CREATE SCHEMA public');
        app(SchemaMigrator::class)->migrateSets(['axiomasoft/corex:tenant'], 'pgsql');
        $modulePath = dirname(__DIR__, 2).'/tests/Stubs/Lifecycle/migrations/base';
        $set = new SchemaSet('corex/stub-base', 'tenant', SchemaScope::Tenant, $modulePath, 1, lifecycle: SchemaLifecycle::Module);
        $provider = new class($set) implements CoreX\Database\Contracts\SchemaSetProvider {
            public function __construct(private SchemaSet $set) {}
            public function sets(): array { return [$this->set]; }
        };
        $migrator = new LaravelSchemaMigrator(new TaggedSchemaSets([$provider]), app('migrator'), app('db'));
        if ($case === 'preinstalled-module') { $migrator->migrate(SchemaScope::Tenant, 'pgsql', true); }
        $discovery = new ModuleDiscovery(fallbackModules: [CoreX\Testing\Stubs\Lifecycle\StubBaseModule::class]);
        $registry = Mockery::mock(ModuleRegistry::class);
        foreach ($discovery->discover() as $manifest) {
            $registry->shouldReceive('get')->with($manifest->composerName)->andReturn(CompiledModule::fromManifest($manifest));
        }
        $lifecycle = new DatabaseModuleLifecycle($registry, $discovery,
            new DatabaseRecordsRegistrar(app('db'), app(ClockInterface::class), 'pgsql'), app(), app(TenantContextResolver::class),
            app('db'), app('events'), app(ClockInterface::class), migrationPaths: ['corex/stub-base' => $modulePath], connection: 'pgsql');
        $lifecycle->enable('corex/stub-base');
        $claims = $connection->table('mod_records')->where('module_name', 'corex/stub-base')->where('record_type', 'table')->count();
        $lifecycle->disable('corex/stub-base', DisableReason::Manual);
        $lifecycle->uninstall('corex/stub-base', false);
        result(['case' => $case, 'table_claims_after_enable' => $claims,
            'module_state_after_purge' => $lifecycle->status('corex/stub-base')->state->value,
            'table_survived_purge' => $connection->getSchemaBuilder()->hasTable('sba_widgets')]);
    } else {
        throw new RuntimeException('Unknown reproduction: '.$case);
    }
} finally {
    $harness->stop();
}
