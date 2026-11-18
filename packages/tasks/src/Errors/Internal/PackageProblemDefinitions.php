<?php

declare(strict_types=1);

namespace CoreX\Tasks\Errors\Internal;

use CoreX\Tasks\Errors\Enums\TasksErrorCode;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ProblemDefinitionProvider;
use Packman\Errors\Data\ProblemDefinition;
use Packman\Errors\Enums\ErrorCategory;

/** @internal The owning package contributes its safe error catalogue at boot. */
#[Internal]
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: TasksErrorCode::InvalidPlanningDraft, type: 'urn:packman:problem:tasks.invalid_planning_draft', defaultStatus: 422, title: 'Invalid planning draft.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: TasksErrorCode::PlanningConflict, type: 'urn:packman:problem:tasks.planning_conflict', defaultStatus: 409, title: 'Planning conflict.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: TasksErrorCode::TasksModuleDisabled, type: 'urn:packman:problem:tasks.tasks_module_disabled', defaultStatus: 503, title: 'Tasks module disabled.', retryable: false, category: ErrorCategory::Unavailable, detailKeys: []);
    }
}
