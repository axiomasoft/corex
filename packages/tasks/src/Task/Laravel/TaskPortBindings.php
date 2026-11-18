<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Laravel;

use CoreX\Tasks\Access\Contracts\TaskModuleContext;
use CoreX\Tasks\Access\Internal\TaskModuleGuard;
use CoreX\Tasks\Project\Contracts\ProjectRead;
use CoreX\Tasks\Project\Internal\SqlProjectRead;
use CoreX\Tasks\Sprint\Contracts\SprintRead;
use CoreX\Tasks\Sprint\Internal\SqlSprintRead;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Registers the owning package's call ports with their private implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TaskPortBindings
{
    public static function register(Application $app): void
    {
        $app->bind(TaskModuleContext::class, static fn (Application $app): TaskModuleContext => $app->make(TaskModuleGuard::class));
        $app->bind(ProjectRead::class, SqlProjectRead::class);
        $app->bind(SprintRead::class, SqlSprintRead::class);
    }
}
