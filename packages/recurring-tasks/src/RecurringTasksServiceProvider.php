<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\RuntimeConfigCheck;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\RecurringTasks\Errors\Internal\PackageProblemDefinitions;
use CoreX\RecurringTasks\Internal\DangerousModesCheck;
use CoreX\RecurringTasks\Internal\MaterializationBindings;
use CoreX\RecurringTasks\Internal\RecurringTasksOptions;
use CoreX\RecurringTasks\Internal\RecurringTasksSchemaSets;
use CoreX\RecurringTasks\Laravel\CompletionBindings;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class RecurringTasksServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([RecurringTasksSchemaSets::class], SchemaSetProvider::class);

        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-recurring-tasks.php', 'corex-recurring-tasks');

        $this->app->singleton(RecurringTasksOptions::class, static fn (Application $app): RecurringTasksOptions => RecurringTasksOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-recurring-tasks', []),
        ));
        // Register-time read: the wakeup tag must be decided before the registry is built.
        $options = RecurringTasksOptions::fromArray((array) $this->app->make(Repository::class)->get('corex-recurring-tasks', []));
        $this->app->tag([DangerousModesCheck::class], RuntimeConfigCheck::class);

        MaterializationBindings::register(app: $this->app, wakeupEnabled: $options->wakeupEnabled);
    }

    public function boot(): void
    {
        $this->app->make(RecurringTasksOptions::class);

        CompletionBindings::register($this->app);
    }
}
