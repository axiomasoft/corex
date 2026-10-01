<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\TasksWorkflow\Internal\CaptureTaskCompletion;
use Illuminate\Contracts\Foundation\Application;

/** Registers the owning package's call ports with their private implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CompletionBindings
{
    public static function register(Application $app): void
    {
        $app->scoped(TaskCompletionCapture::class, CaptureTaskCompletion::class);
        $app->tag(TaskCompletionCapture::class, 'corex.tasks.completion-captures');
    }
}
