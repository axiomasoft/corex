<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Laravel;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\RecurringTasks\Internal\CaptureTaskCompletion;
use CoreX\RecurringTasks\Internal\RecurringTasksOptions;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Workflow\Contracts\WorkflowBinding;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Registers the owning package's call ports with their private implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CompletionBindings
{
    public static function register(Application $app): void
    {
        $app->scoped(TaskCompletionCapture::class, static fn (Application $app): CaptureTaskCompletion => new CaptureTaskCompletion(
            enabled: $app->make(RecurringTasksOptions::class)->completionCaptureEnabled,
            definitionRef: $app->make(RecurringTasksOptions::class)->completionCaptureDefinitionRef,
            versionRef: $app->make(RecurringTasksOptions::class)->completionCaptureVersionRef,
            receipts: $app->make(TaskOperationReceipts::class),
            contexts: $app->make(TenantContextResolver::class),
            scopes: $app->make(DataScopeFactory::class),
            workflow: interface_exists(WorkflowBinding::class) && $app->bound(WorkflowBinding::class) ? $app->make(WorkflowBinding::class) : null,
        ));
        $app->tag(TaskCompletionCapture::class, 'corex.tasks.completion-captures');
    }
}
