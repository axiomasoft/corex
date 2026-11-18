<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Internal;

use CoreX\Context\Data\TenantContext;
use CoreX\Tasks\Planning\Data\PlanningOperation;
use CoreX\Tasks\Project\Data\ProjectDraft;
use CoreX\Tasks\Sprint\Data\SprintDraft;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Time\Data\TimeLogDraft;
use Packman\Attributes\Internal;

/** @internal Canonical payload digests for planning operations. */
#[Internal]
final class PlanningHasher
{
    public static function project(ProjectDraft $draft, TenantContext $context): string
    {
        return self::digest(
            operation: PlanningOperation::CreateProject,
            parts: [
                $draft->workspaceId,
                self::trimmedName($draft->name),
                self::actor($draft->actor),
                self::context($context),
            ],
        );
    }

    public static function sprint(SprintDraft $draft, TenantContext $context, string $startDate, string $endDate): string
    {
        return self::digest(
            operation: PlanningOperation::CreateSprint,
            parts: [
                $draft->projectId,
                self::trimmedName($draft->name),
                $startDate,
                $endDate,
                self::actor($draft->actor),
                self::context($context),
            ],
        );
    }

    public static function timeLog(TimeLogDraft $draft, TenantContext $context): string
    {
        return self::digest(
            operation: PlanningOperation::RecordTime,
            parts: [
                $draft->taskId,
                $draft->userId,
                (string) $draft->minutes,
                $draft->spentOn,
                $draft->comment ?? '',
                $draft->source->value,
                self::actor($draft->actor),
                self::context($context),
            ],
        );
    }

    public static function restoreEpoch(TenantContext $context): string
    {
        return self::context($context);
    }

    /** @param list<string> $parts */
    private static function digest(PlanningOperation $operation, array $parts): string
    {
        return hash('sha256', 'task-planning/1'."\0".$operation->value."\0".implode("\0", $parts));
    }

    private static function context(TenantContext $context): string
    {
        $workspaceId = $context->workspace === null ? '' : $context->workspace->id;

        return hash('sha256', $context->account->id."\0".$workspaceId);
    }

    private static function actor(ActorData $actor): string
    {
        return $actor->type->value."\0".($actor->id ?? '');
    }

    private static function trimmedName(string $name): string
    {
        return trim($name);
    }
}
