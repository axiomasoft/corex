<?php

declare(strict_types=1);

namespace App\TaskLifetime;

use App\Scenario\Scenario;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\CompleteTaskData;
use CoreX\Tasks\Task\Data\EntityReference;
use CoreX\Tasks\Task\Data\MoveTaskToStageData;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Data\TaskView;
use CoreX\Tasks\Task\Data\UpdateTaskData;

final readonly class TaskLifetimeAccess implements TaskAccess, TaskMutationAccess
{
    public function __construct(private WorkPrincipalAuthority $principals) {}

    public function authorizeCreate(TaskDraft $draft, TenantContext $context): void
    {
        $this->authorize($draft->actor, $context, $draft->workspaceId);
    }

    public function authorizeMove(MoveTaskToStageData $data, TenantContext $context): void
    {
        $this->authorize($data->actor, $context, Scenario::W1, accountWide: true);
    }

    public function authorizeTarget(EntityReference $target, ActorData $actor, TenantContext $context): void
    {
        throw new ScopeContextMismatch('The fixture task actor has no target grants.');
    }

    public function authorizeUpdate(UpdateTaskData $data, TenantContext $context, TaskView $task): void
    {
        throw new ScopeContextMismatch('The fixture task actor has no generic update grant.');
    }

    public function authorizeComplete(CompleteTaskData $data, TenantContext $context, TaskView $task): void
    {
        $this->authorize($data->actor, $context, $task->workspaceId, accountWide: true);
    }

    private function authorize(ActorData $actor, TenantContext $context, ?string $workspace, bool $accountWide = false): void
    {
        $principal = $this->principals->current($context);

        if ($workspace !== Scenario::W1 || ($context->workspace?->id !== $workspace && ! ($accountWide && $context->workspace === null))
            || $principal->kind !== $actor->type->value || $principal->id !== $actor->id) {
            throw new ScopeContextMismatch('The current fixture task actor does not own this workspace operation.');
        }
    }
}
