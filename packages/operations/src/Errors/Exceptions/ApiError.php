<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Contracts\ErrorCode;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

/** Explicit public error. Never pass database or infrastructure exception messages here. */
#[Api(since: '1.0')]
final class ApiError extends CodedException implements DescribesError
{
    public readonly string $errorCode;

    private readonly ErrorCode $errorCodeIdentity;

    public function __construct(
        ErrorCode $errorCode,
        public readonly int $status,
        string $message,
        public readonly JsonDocument $details = new JsonDocument('[]'),
        public readonly ?ErrorCategory $category = null,
        public readonly bool $retryable = false,
        public readonly ?string $path = null,
        public readonly ?string $retryAfterPolicy = null,
    ) {
        $this->errorCodeIdentity = $errorCode;
        $this->errorCode = $errorCode->value();
        parent::__construct($message);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(
            code: $this->errorCodeIdentity,
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

        foreach (JsonDocuments::map($this->details) as $key => $value) {
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
