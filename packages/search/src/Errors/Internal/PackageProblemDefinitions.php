<?php

declare(strict_types=1);

namespace CoreX\Search\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Search\Errors\Enums\SearchErrorCode;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: SearchErrorCode::InvalidSearchConfiguration, type: 'urn:corex:problem:search.invalid_search_configuration', defaultStatus: 500, title: 'Invalid search configuration.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
    }
}
