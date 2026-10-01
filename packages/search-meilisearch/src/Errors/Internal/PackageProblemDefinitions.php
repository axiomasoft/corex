<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\SearchMeilisearch\Errors\Enums\SearchMeilisearchErrorCode;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(SearchMeilisearchErrorCode::InvalidMeilisearchConfiguration, 'urn:corex:problem:search-meilisearch.invalid_meilisearch_configuration', 500, 'Invalid meilisearch configuration.', false, ErrorCategory::Internal, []);
    }
}
