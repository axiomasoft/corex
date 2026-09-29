<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors;

use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use RuntimeException;

/** Explicit public error. Never pass database or infrastructure exception messages here. */
final class ApiError extends RuntimeException implements DescribesError
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message,
        public readonly array $details = [],
        public readonly ?ErrorCategory $category = null,
        public readonly bool $retryable = false,
        public readonly ?string $path = null,
        public readonly ?string $retryAfterPolicy = null,
    ) {
        parent::__construct($message);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(
            code: $this->errorCode,
            category: $this->category ?? $this->categoryFromStatus(),
            retryable: $this->retryable,
            path: $this->path,
            details: $this->descriptorDetails(),
        );
    }

    private function categoryFromStatus(): ErrorCategory
    {
        return match ($this->status) {
            401 => ErrorCategory::Authentication,
            403 => ErrorCategory::Authorization,
            404 => ErrorCategory::NotFound,
            409 => ErrorCategory::Conflict,
            422 => ErrorCategory::Validation,
            429 => ErrorCategory::RateLimit,
            503 => ErrorCategory::Unavailable,
            default => $this->status >= 400 && $this->status < 500
                ? ErrorCategory::Validation
                : ErrorCategory::Internal,
        };
    }

    /**
     * @return array<string, scalar|list<scalar|null>|null>
     */
    private function descriptorDetails(): array
    {
        $details = [];

        foreach ($this->details as $key => $value) {
            if ($value === null || is_scalar($value)) {
                $details[$key] = $value;

                continue;
            }

            if (! is_array($value) || ! array_is_list($value)) {
                continue;
            }

            $items = [];

            foreach ($value as $item) {
                if ($item !== null && ! is_scalar($item)) {
                    continue 2;
                }

                $items[] = $item;
            }

            $details[$key] = $items;
        }

        return $details;
    }
}
