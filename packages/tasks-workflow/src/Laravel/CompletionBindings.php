<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\TasksWorkflow\Internal\CaptureTaskCompletion;
use CoreX\TasksWorkflow\Internal\TasksWorkflowOptions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;

/** Registers the owning package's call ports with their private implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CompletionBindings
{
    public static function register(Application $app): void
    {
        $app->scoped(TaskCompletionCapture::class, static fn (Application $app): CaptureTaskCompletion => new CaptureTaskCompletion(
            connection: $app->make(Connection::class),
            enabled: $app->make(TasksWorkflowOptions::class)->completionCaptureEnabled,
            definitionRef: $app->make(TasksWorkflowOptions::class)->completionCaptureDefinitionRef,
            versionRef: $app->make(TasksWorkflowOptions::class)->completionCaptureVersionRef,
            receipts: $app->make(TaskOperationReceipts::class),
        ));
        $app->tag(TaskCompletionCapture::class, 'corex.tasks.completion-captures');
    }
}
