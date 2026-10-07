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
        yield new ProblemDefinition(code: TasksWorkflowErrorCode::MaterializationConflict, type: 'urn:corex:problem:tasks-workflow.materialization_conflict', defaultStatus: 409, title: 'Materialization conflict.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: TasksWorkflowErrorCode::StaleMaterialization, type: 'urn:corex:problem:tasks-workflow.stale_materialization', defaultStatus: 409, title: 'Stale materialization.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: TasksWorkflowErrorCode::UnsupportedMaterialization, type: 'urn:corex:problem:tasks-workflow.unsupported_materialization', defaultStatus: 422, title: 'Unsupported materialization.', retryable: false, category: ErrorCategory::Unsupported, detailKeys: []);
    }
}
