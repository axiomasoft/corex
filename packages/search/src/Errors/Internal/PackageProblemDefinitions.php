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
        foreach ([
            [SearchErrorCode::SearchFeatureUnavailable, ErrorCategory::Unsupported, 422, 'Search feature unavailable.'],
            [SearchErrorCode::IndexNotReady, ErrorCategory::Unavailable, 503, 'Search index is not ready.'],
            [SearchErrorCode::GenerationChanged, ErrorCategory::Conflict, 409, 'Search generation changed.'],
            [SearchErrorCode::SearchWitnessChanged, ErrorCategory::Conflict, 409, 'Search witness changed.'],
            [SearchErrorCode::InvalidSearchCursor, ErrorCategory::Validation, 422, 'Invalid search cursor.'],
        ] as [$code, $category, $status, $title]) {
            yield new ProblemDefinition(code: $code, type: 'urn:packman:problem:'.$code->value, defaultStatus: $status,
                title: $title, retryable: false, category: $category, detailKeys: []);
        }
    }
}
