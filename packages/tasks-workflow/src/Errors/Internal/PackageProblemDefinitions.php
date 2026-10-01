<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\TasksWorkflow\Errors\Enums\TasksWorkflowErrorCode;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(TasksWorkflowErrorCode::MaterializationConflict, 'urn:corex:problem:tasks-workflow.materialization_conflict', 409, 'Materialization conflict.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(TasksWorkflowErrorCode::StaleMaterialization, 'urn:corex:problem:tasks-workflow.stale_materialization', 409, 'Stale materialization.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(TasksWorkflowErrorCode::UnsupportedMaterialization, 'urn:corex:problem:tasks-workflow.unsupported_materialization', 422, 'Unsupported materialization.', false, ErrorCategory::Unsupported, []);
    }
}
