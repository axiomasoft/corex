<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Tests\Feature;

require_once __DIR__.'/../../../scheduler/tests/Support/PackageBridge.php';

use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\RecurringTasks\Contracts\TaskRecurrenceCommand;
use CoreX\RecurringTasks\Data\MaterializationWindow;
use CoreX\RecurringTasks\Data\SeriesReference;
use CoreX\RecurringTasks\Exceptions\MaterializationConflict;
use CoreX\RecurringTasks\Exceptions\UnsupportedMaterialization;
use CoreX\RecurringTasks\Internal\MaterializationHasher;
use CoreX\RecurringTasks\Internal\ScopeConnectionGuard;
use CoreX\RecurringTasks\Internal\SeriesDefinitionCodec;
use CoreX\RecurringTasks\Tests\Support\SeriesSeeder;
use CoreX\RecurringTasks\Tests\TestCase;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\ScheduleResult;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\TaskDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packman\Config\Exceptions\InvalidConfiguration;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Compiler\CompiledRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class MaterializationSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        $app['config']->set('corex-recurring-tasks.wakeup.enabled', true);
        $custom = ($this->providedData()[0] ?? false) === true;

        if ($custom) {
            $app['config']->set('corex-recurring-tasks.wakeup.handler_kind', 'custom.materialize');
            $app['config']->set('corex-recurring-tasks.wakeup.handler_version', 'custom/2');
            $app['config']->set('corex-recurring-tasks.wakeup.accept_legacy_unstamped', true);
        }
        $providers = parent::getPackageProviders($app);

        return ($this->providedData()[1] ?? false)
            ? [wakeup_bridge_provider(), ...$providers]
            : [...$providers, wakeup_bridge_provider()];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateSchemaSets(['axiomasoft/corex-scheduler:tenant']);
        resolve(ModuleRegistry::class)->shouldReceive('compiled')->andReturn(CompiledRegistry::empty());
    }

    private function series(int $count = 6): SeriesReference
    {
        $workspace = (string) Str::uuid7();
        $user = (string) Str::uuid7();
        DB::table('wsp_workspaces')->insert(['id' => $workspace, 'name' => 'Main']);
        DB::table('users')->insert(['id' => $user, 'name' => 'Owner']);

        return SeriesSeeder::seed('scheduled-series', SeriesSeeder::dailySet('scheduled-series', 'rev-1', $count), new TaskDraft($workspace, 'Scheduled', new ActorData(ActorType::User, $user)), str_repeat('a', 64));
    }

    private function scope(): DataScope
    {
        return DataScope::fromTenantContext(resolve(TenantContextResolver::class)->current(), ScopeConnectionGuard::identity(DB::connection()), 'operator', str_repeat('a', 64));
    }

    private function runWindow(SeriesReference $series, MaterializationWindow $window, string $key): void
    {
        $scope = $this->scope();
        resolve(TaskRecurrenceCommand::class)->materialize($scope, $series, $window, new WriteToken($key, MaterializationHasher::payload($scope, $series, $window), $series->immutableRevision));
    }

    public static function definitions(): array
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

    #[DataProvider('definitions')]
    public function test_public_providers_register_stamp_schedule_and_sweep(bool $custom, bool $wakeupFirst): void
    {
        $definitions = iterator_to_array(app()->tagged(WakeupHandlerDefinition::TAG));
        self::assertCount(1, $definitions);
        self::assertSame($custom ? 'custom.materialize' : MaterializationHasher::HANDLER_KIND, $definitions[0]->kind);
        self::assertSame($custom ? 'custom/2' : MaterializationHasher::HANDLER_VERSION, $definitions[0]->version);
        self::assertSame($custom, $definitions[0]->acceptLegacyUnstamped);
        $series = $this->series();
        $window = new MaterializationWindow('2026-09-01', '2026-09-08', pageLimit: 2);
        $this->runWindow($series, $window, 'scheduled-1');
        $first = DB::table('sys_wakeup_requests')->first();
        $payload = json_decode($first->payload, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($definitions[0]->version, $payload['_corex']['extension']['version']);
        $this->runWindow($series, $window, 'scheduled-1');
        self::assertSame(1, DB::table('sys_wakeup_requests')->count());
        for ($i = 0; $i < 4; $i++) {
            wakeup_bridge_sweep(resolve(TenantContextResolver::class)->current(), 10);
        }
        self::assertSame(6, DB::table('tsk_tasks')->count());
        self::assertSame(6, DB::table('corex_task_occurrences')->count());
        self::assertSame(0, DB::table('sys_wakeup_requests')->where('status', 'failed')->count());
    }

    public static function terminalStates(): array
    {
        return [['cancelled'], ['completed']];
    }

    #[DataProvider('terminalStates')]
    public function test_new_windows_are_independent_of_completed_or_cancelled_old_requests(string $state): void
    {
        $series = $this->series();
        $first = new MaterializationWindow('2026-09-01', '2026-09-04', pageLimit: 10);
        $this->runWindow($series, $first, 'first');
        $row = DB::table('sys_wakeup_requests')->first();

        if ($state === 'cancelled') {
            resolve(WakeupScheduler::class)->cancel(resolve(TenantContextResolver::class)->current(), $row->request_key);
        } else {
            wakeup_bridge_sweep(resolve(TenantContextResolver::class)->current(), 10);
        }
        self::assertSame($state, DB::table('sys_wakeup_requests')->where('request_key', $row->request_key)->value('status'));
        $this->runWindow($series, $first, 'first');
        self::assertSame(1, DB::table('sys_wakeup_requests')->count());
        $this->runWindow($series, new MaterializationWindow('2026-09-04', '2026-09-08', pageLimit: 10), 'next');
        self::assertSame(2, DB::table('sys_wakeup_requests')->count());
        self::assertSame(6, DB::table('tsk_tasks')->count());
        $stored = DB::table('corex_task_series')->where('series_key', $series->id)->first();
        $decoded = SeriesDefinitionCodec::decode($stored->definition, $stored->template, $stored->actor);
        $nextRevision = SeriesSeeder::seed($series->id, SeriesSeeder::dailySet($series->id, 'rev-2', 1), $decoded['template'], str_repeat('a', 64));
        DB::table('corex_task_series_state')->where('series_key', $series->id)->update(['active_revision' => 'rev-2']);
        $this->runWindow($nextRevision, $first, 'new-revision');
        self::assertSame(3, DB::table('sys_wakeup_requests')->count());
        self::assertSame(7, DB::table('tsk_tasks')->count());
    }

    public function test_duplicate_handler_kind_is_rejected_at_provider_boot(): void
    {
        $definition = iterator_to_array(app()->tagged(WakeupHandlerDefinition::TAG))[0];
        config(['corex-scheduler.handlers' => [$definition->kind => [
            'handler' => $definition->handler, 'version' => $definition->version,
        ]]]);
        app()->forgetInstance(wakeup_bridge_options());
        $provider = wakeup_bridge_provider();
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('duplicate wakeup handler kind');
        new $provider(app())->boot();
    }

    public function test_missing_scheduler_is_rejected_before_domain_effects(): void
    {
        $series = $this->series();
        app()->offsetUnset(WakeupScheduler::class);

        try {
            $this->runWindow($series, new MaterializationWindow('2026-09-01', '2026-09-08'), 'no-scheduler');
            self::fail('Missing scheduler accepted.');
        } catch (UnsupportedMaterialization) {
            self::assertSame(0, DB::table('tsk_tasks')->count());
            self::assertSame(0, DB::table('corex_task_operations')->count());
        }
    }

    public function test_replay_recovers_scheduling_failure_after_commit_and_exposes_conflict(): void
    {
        $series = $this->series();
        $window = new MaterializationWindow('2026-09-01', '2026-09-08');
        $scheduler = resolve(WakeupScheduler::class);
        $failed = $this->createMock(WakeupScheduler::class);
        $failed->method('schedule')->willThrowException(new RuntimeException('Unavailable scheduler'));
        app()->instance(WakeupScheduler::class, $failed);

        try {
            $this->runWindow($series, $window, 'recover');
            self::fail('Scheduling failure hidden.');
        } catch (RuntimeException) {
            self::assertSame(6, DB::table('tsk_tasks')->count());
            self::assertSame(1, DB::table('corex_task_operations')->count());
        }
        $conflict = $this->createMock(WakeupScheduler::class);
        $conflict->method('schedule')->willReturnCallback(static fn (WakeupRequest $request): ScheduleResult => new ScheduleResult('conflict', $request->requestKey));
        app()->instance(WakeupScheduler::class, $conflict);

        try {
            $this->runWindow($series, $window, 'recover');
            self::fail('Scheduling conflict hidden.');
        } catch (MaterializationConflict) {
            self::assertSame(6, DB::table('tsk_tasks')->count());
        }
        app()->instance(WakeupScheduler::class, $scheduler);
        $this->runWindow($series, $window, 'recover');
        $this->runWindow($series, $window, 'recover');
        self::assertSame(1, DB::table('sys_wakeup_requests')->count());
        self::assertSame(6, DB::table('tsk_tasks')->count());
    }
}
