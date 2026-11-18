<?php

declare(strict_types=1);

namespace CoreX\Search\Errors\Internal;

use CoreX\Search\Errors\Enums\SearchErrorCode;
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
        yield new ProblemDefinition(code: SearchErrorCode::InvalidSearchConfiguration, type: 'urn:packman:problem:search.invalid_search_configuration', defaultStatus: 500, title: 'Invalid search configuration.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
    }
}
