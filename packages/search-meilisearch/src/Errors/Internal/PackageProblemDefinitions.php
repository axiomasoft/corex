<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Errors\Internal;

use CoreX\SearchMeilisearch\Errors\Enums\SearchMeilisearchErrorCode;
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
        yield new ProblemDefinition(code: SearchMeilisearchErrorCode::InvalidMeilisearchConfiguration, type: 'urn:packman:problem:search-meilisearch.invalid_meilisearch_configuration', defaultStatus: 500, title: 'Invalid meilisearch configuration.', retryable: false, category: ErrorCategory::Internal, detailKeys: []);
    }
}
