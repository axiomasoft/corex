<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\EntityReference;
use CoreX\Tasks\Task\Data\MoveTaskToStageData;
use CoreX\Tasks\Task\Data\TaskDraft;

final readonly class WorkspaceTaskAccess implements TaskAccess
{
    public function __construct(private ScenarioPrincipalAuthority $principals) {}

    public function authorizeCreate(TaskDraft $draft, TenantContext $context): void
    {
        if ($context->workspace?->id !== $draft->workspaceId || $this->principals->current($context)->id !== $draft->actor->id) {
            throw new ScopeContextMismatch('This workspace member cannot create the requested task.');
        }
    }

    public function authorizeMove(MoveTaskToStageData $data, TenantContext $context): void
    {
        throw new ScopeContextMismatch('The scenario host grants only task creation.');
    }

    public function authorizeTarget(EntityReference $target, ActorData $actor, TenantContext $context): void
    {
        throw new ScopeContextMismatch('The scenario host grants only task creation.');
    }
}
