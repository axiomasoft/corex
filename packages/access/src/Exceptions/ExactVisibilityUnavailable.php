<?php

declare(strict_types=1);

namespace CoreX\Access\Exceptions;

use CoreX\Access\Errors\Enums\AccessErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;
use Throwable;

/** The requested permission has no exact SQL representation for this model. */
#[Api(since: '1.0')]
final class ExactVisibilityUnavailable extends CodedException
{
    public function __construct(public readonly string $reason, public readonly ?string $component = null, ?Throwable $previous = null)
    {
        parent::__construct('Exact visibility is unavailable: '.$reason.'.', previous: $previous);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(
            code: AccessErrorCode::ExactVisibilityUnavailable,
            category: ErrorCategory::Unsupported,
            retryable: false,
            details: array_filter(['reason' => $this->reason, 'component' => $this->component], static fn (?string $value): bool => $value !== null),
        );
    }
}
