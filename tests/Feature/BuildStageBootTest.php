<?php

declare(strict_types=1);

namespace CoreX\Tests\Feature;

use CoreX\Config\Data\CoreOptions;
use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Config\Laravel\RuntimeConfigGate;
use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Scheduler\Internal\SchedulerOptions;
use CoreX\Tests\Golden\GoldenTestCase;
use CoreX\Workflow\Internal\WorkflowOptions;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Artisan;
use Override;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

// P05.1 (D69 p.2, p.7, p.10; examples.md §3) — shape is valid on an empty environment,
// runtime requirements and dangerous modes run only before a process accepts work.

final class BuildStageBootTest extends GoldenTestCase
{
    private const string WORKFLOW_MODE = 'corex-workflow.enforce_declarations';

    #[Override]
    protected function tearDown(): void
    {
        // Teardown runs migrate commands that prompt for confirmation in production.
        $this->app['env'] = 'testing';

        parent::tearDown();
    }

    public function test_every_provider_boots_on_an_empty_environment(): void
    {
        self::assertInstanceOf(CoreOptions::class, $this->app->make(CoreOptions::class));
        self::assertInstanceOf(SchedulerOptions::class, $this->app->make(SchedulerOptions::class));
        self::assertSame([], RuntimeConfigGate::violations($this->app, false));
    }

    public function test_a_build_command_never_runs_runtime_checks(): void
    {
        $this->production(dangerousWorkflow: true);

        foreach (RuntimeConfigGate::BUILD_COMMANDS as $command) {
            $this->startCommand($command);
        }

        $this->addToAssertionCount(1);
    }

    public function test_a_work_command_and_http_boot_fail_with_the_dangerous_path(): void
    {
        $this->production(dangerousWorkflow: true);

        foreach ([fn () => $this->startCommand('queue:work'), fn () => RuntimeConfigGate::assert($this->app)] as $attempt) {
            try {
                $attempt();
                self::fail('A dangerous mode was accepted in production.');
            } catch (InvalidConfiguration $exception) {
                self::assertContains(self::WORKFLOW_MODE.': dangerous mode is not acknowledged for production', $exception->violations);
            }
        }
    }

    public function test_an_exact_acknowledgement_passes_and_is_reported_by_the_validate_command(): void
    {
        $this->declareTenancyHostConfiguration();
        $this->production(dangerousWorkflow: true, acknowledged: [self::WORKFLOW_MODE]);

        $this->startCommand('queue:work');
        RuntimeConfigGate::assert($this->app);

        self::assertSame(0, Artisan::call('corex:config:validate'));
        self::assertStringContainsString('WARNING [dangerous-mode-acknowledged] '.self::WORKFLOW_MODE, Artisan::output());
    }

    public function test_a_pattern_in_the_acknowledgement_list_is_a_shape_violation(): void
    {
        config(['corex.acknowledged_dangerous_modes' => ['corex-scheduler.*']]);
        $this->app->forgetInstance(CoreOptions::class);

        try {
            $this->app->make(CoreOptions::class);
            self::fail('A wildcard acknowledgement was accepted.');
        } catch (InvalidConfiguration $exception) {
            self::assertContains('acknowledged_dangerous_modes.0: must be an exact config path without wildcards', $exception->violations);
        }
    }

    public function test_a_dangerous_mode_outside_production_is_not_a_violation(): void
    {
        config([self::WORKFLOW_MODE => false, 'corex-notifications.digest.accept_legacy_unstamped' => true]);
        $this->app->forgetInstance(WorkflowOptions::class);
        $this->app->forgetInstance(NotificationsOptions::class);

        $this->startCommand('queue:work');
        self::assertSame([], RuntimeConfigGate::violations($this->app, false));
    }

    public function test_a_missing_secret_of_an_enabled_feature_fails_production_without_its_value(): void
    {
        $this->production(dangerousWorkflow: false);
        config(['app.key' => '']);

        try {
            RuntimeConfigGate::assert($this->app);
            self::fail('Production started without app.key.');
        } catch (InvalidConfiguration $exception) {
            self::assertStringContainsString('app.key: must be configured', $exception->getMessage());
        }
    }

    public function test_the_validate_command_reports_the_physical_tenancy_opt_out_as_a_notice(): void
    {
        $this->declareTenancyHostConfiguration();
        config(['corex-operations.require_physical_tenancy' => false]);

        self::assertSame(0, Artisan::call('corex:config:validate'));
        self::assertStringContainsString('NOTICE [opt-out] corex-operations.require_physical_tenancy=false', Artisan::output());
    }

    /** @param  list<string>  $acknowledged */
    private function production(bool $dangerousWorkflow, array $acknowledged = []): void
    {
        $this->app['env'] = 'production';
        config([
            self::WORKFLOW_MODE => ! $dangerousWorkflow,
            'corex.acknowledged_dangerous_modes' => $acknowledged,
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        ]);
        $this->app->forgetInstance(WorkflowOptions::class);
        $this->app->forgetInstance(CoreOptions::class);
    }

    private function startCommand(string $name): void
    {
        $this->app->make('events')->dispatch(new CommandStarting($name, new ArrayInput([]), new NullOutput));
    }
}
