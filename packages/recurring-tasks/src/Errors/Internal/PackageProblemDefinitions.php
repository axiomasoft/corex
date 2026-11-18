<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Errors\Internal;

use CoreX\RecurringTasks\Errors\Enums\RecurringTasksErrorCode;
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
        yield new ProblemDefinition(code: RecurringTasksErrorCode::MaterializationConflict, type: 'urn:packman:problem:recurring-tasks.materialization_conflict', defaultStatus: 409, title: 'Materialization conflict.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: RecurringTasksErrorCode::StaleMaterialization, type: 'urn:packman:problem:recurring-tasks.stale_materialization', defaultStatus: 409, title: 'Stale materialization.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: RecurringTasksErrorCode::UnsupportedMaterialization, type: 'urn:packman:problem:recurring-tasks.unsupported_materialization', defaultStatus: 422, title: 'Unsupported materialization.', retryable: false, category: ErrorCategory::Unsupported, detailKeys: []);
    }
}
