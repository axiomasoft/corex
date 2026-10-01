<?php

declare(strict_types=1);

namespace CoreX\Errors\Data;

use CoreX\Attributes\Api;
use CoreX\Errors\Contracts\ErrorCode;
use CoreX\Errors\Enums\ErrorCategory;

/** A safe, package-owned declaration. Occurrences retain their path and bounded details. */
#[Api(since: '1.0')]
final readonly class ProblemDefinition
{
    /** @param list<string> $detailKeys */
    public function __construct(
        public ErrorCode $code,
        public string $type,
        public int $defaultStatus,
        public string $title,
        public bool $retryable,
        public ErrorCategory $category,
        public array $detailKeys = [],
    ) {}
}
