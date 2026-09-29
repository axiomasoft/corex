<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Operations\Errors\ApiError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** @internal Shares safe normalization between dispatch and the surrounding middleware pipeline. */
final class ErrorMapper
{
    public function map(Throwable $error, bool $reportUnexpected = true): ApiError
    {
        if ($error instanceof ApiError) {
            return $error;
        }

        if ($error instanceof DescribesError) {
            $descriptor = $error->errorDescriptor();

            return new ApiError(
                errorCode: $descriptor->code,
                status: $this->statusFor($descriptor->category),
                message: $this->messageFor($descriptor->category),
                details: $descriptor->details,
                category: $descriptor->category,
                retryable: $descriptor->retryable,
                path: $descriptor->path,
            );
        }

        if ($error instanceof ValidationException) {
            return new ApiError(
                errorCode: 'validation_failed',
                status: 422,
                message: 'Input validation failed.',
                details: $error->errors(),
                category: ErrorCategory::Validation,
            );
        }

        if ($error instanceof CentralContextException) {
            return new ApiError(
                errorCode: 'context_required',
                status: 403,
                message: 'An initialized account context is required.',
                category: ErrorCategory::Authorization,
            );
        }

        $status = match (true) {
            $error instanceof AuthenticationException => 401,
            $error instanceof AuthorizationException => $error->status() === 404 ? 404 : 403,
            $error instanceof ModelNotFoundException => 404,
            $error instanceof HttpExceptionInterface => $error->getStatusCode(),
            default => 500,
        };
        [$code, $message] = match ($status) {
            401 => ['authentication_required', 'Authentication is required.'],
            403 => ['forbidden', 'Operation is not permitted.'],
            404 => ['resource_not_found', 'Resource not found.'],
            409 => ['conflict', 'Operation conflicts with current state.'],
            422 => ['validation_failed', 'Input validation failed.'],
            429 => ['rate_limited', 'Too many requests.'],
            default => $status >= 400 && $status < 500
                ? ['request_rejected', 'Request could not be completed.']
                : ['internal_error', 'Operation could not be completed.'],
        };

        if ($code === 'internal_error') {
            if ($reportUnexpected) {
                report($error);
            }
            $status = 500;
        }

        return new ApiError(
            errorCode: $code,
            status: $status,
            message: $message,
            category: $this->categoryForStatus($status),
        );
    }

    private function statusFor(ErrorCategory $category): int
    {
        return match ($category) {
            ErrorCategory::Authentication => 401,
            ErrorCategory::Authorization => 403,
            ErrorCategory::Validation => 422,
            ErrorCategory::Conflict => 409,
            ErrorCategory::NotFound => 404,
            ErrorCategory::RateLimit => 429,
            ErrorCategory::Unsupported => 422,
            ErrorCategory::Unavailable => 503,
            ErrorCategory::Internal => 500,
        };
    }

    private function messageFor(ErrorCategory $category): string
    {
        return match ($category) {
            ErrorCategory::Authentication => 'Authentication is required.',
            ErrorCategory::Authorization => 'Operation is not permitted.',
            ErrorCategory::Validation, ErrorCategory::Unsupported => 'Input validation failed.',
            ErrorCategory::Conflict => 'Operation conflicts with current state.',
            ErrorCategory::NotFound => 'Resource not found.',
            ErrorCategory::RateLimit => 'Too many requests.',
            ErrorCategory::Unavailable, ErrorCategory::Internal => 'Operation could not be completed.',
        };
    }

    private function categoryForStatus(int $status): ErrorCategory
    {
        return match ($status) {
            401 => ErrorCategory::Authentication,
            403 => ErrorCategory::Authorization,
            404 => ErrorCategory::NotFound,
            409 => ErrorCategory::Conflict,
            422 => ErrorCategory::Validation,
            429 => ErrorCategory::RateLimit,
            default => $status >= 400 && $status < 500
                ? ErrorCategory::Validation
                : ErrorCategory::Internal,
        };
    }
}
