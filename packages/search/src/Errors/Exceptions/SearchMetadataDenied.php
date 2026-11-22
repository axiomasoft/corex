<?php

declare(strict_types=1);

namespace CoreX\Search\Errors\Exceptions;

use CoreX\Search\Errors\Enums\SearchErrorCode;
use Packman\Attributes\Internal;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

#[Internal]
final class SearchMetadataDenied extends CodedException
{
    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: SearchErrorCode::MetadataDenied, category: ErrorCategory::Authorization, retryable: false);
    }
}
