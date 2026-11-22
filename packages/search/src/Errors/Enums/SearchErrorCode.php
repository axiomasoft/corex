<?php

declare(strict_types=1);

namespace CoreX\Search\Errors\Enums;

use Packman\Attributes\Api;
use Packman\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum SearchErrorCode: string implements ErrorCode
{
    case InvalidSearchConfiguration = 'search.invalid_search_configuration';

    case SearchFeatureUnavailable = 'search.feature_unavailable';
    case MetadataDenied = 'search.metadata_denied';

    case IndexNotReady = 'search.index_not_ready';
    case GenerationChanged = 'search.generation_changed';
    case SearchWitnessChanged = 'search.witness_changed';
    case InvalidSearchCursor = 'search.invalid_cursor';

    public function value(): string
    {
        return $this->value;
    }
}
