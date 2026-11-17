<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\RecurringTasks\Errors\Enums\RecurringTasksErrorCode;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: RecurringTasksErrorCode::MaterializationConflict, type: 'urn:corex:problem:recurring-tasks.materialization_conflict', defaultStatus: 409, title: 'Materialization conflict.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: RecurringTasksErrorCode::StaleMaterialization, type: 'urn:corex:problem:recurring-tasks.stale_materialization', defaultStatus: 409, title: 'Stale materialization.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: RecurringTasksErrorCode::UnsupportedMaterialization, type: 'urn:corex:problem:recurring-tasks.unsupported_materialization', defaultStatus: 422, title: 'Unsupported materialization.', retryable: false, category: ErrorCategory::Unsupported, detailKeys: []);
    }
}
