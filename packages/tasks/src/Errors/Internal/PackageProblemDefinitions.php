<?php

declare(strict_types=1);

namespace CoreX\Tasks\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Tasks\Errors\Enums\TasksErrorCode;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(TasksErrorCode::InvalidPlanningDraft, 'urn:corex:problem:tasks.invalid_planning_draft', 422, 'Invalid planning draft.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(TasksErrorCode::PlanningConflict, 'urn:corex:problem:tasks.planning_conflict', 409, 'Planning conflict.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(TasksErrorCode::TasksModuleDisabled, 'urn:corex:problem:tasks.tasks_module_disabled', 503, 'Tasks module disabled.', false, ErrorCategory::Unavailable, []);
    }
}
