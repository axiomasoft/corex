<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Operations\Errors\Contracts\ErrorMapping;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** @internal Shares safe normalization between dispatch and the surrounding middleware pipeline. */
final readonly class ErrorMapper implements ErrorMapping
{
    public function __construct(
        private ProblemRegistry $problems,
        private ExceptionHandler $exceptions,
    ) {}

    public function map(Throwable $error, bool $reportUnexpected = true): ApiError
    {
        if ($error instanceof ApiError) {
            try {
                $this->problems->assertOccurrence(descriptor: $error->errorDescriptor(), status: $error->status);
            } catch (Throwable) {
                return $this->unexpected(error: $error, reportUnexpected: $reportUnexpected);
            }

            return $error;
        }

        if ($error instanceof DescribesError) {
            try {
                $descriptor = $error->errorDescriptor();
                $this->problems->assertOccurrence($descriptor);
                $definition = $this->problems->get($descriptor->errorCode);
            } catch (Throwable) {
                return $this->unexpected(error: $error, reportUnexpected: $reportUnexpected);
            }

            return new ApiError(
                errorCode: $definition->code,
                status: $definition->defaultStatus,
                message: $this->messageFor($descriptor->category),
                details: JsonDocuments::capture($descriptor->details),
                category: $descriptor->category,
                retryable: $descriptor->retryable,
                path: $descriptor->path,
            );
        }

        if ($error instanceof ValidationException) {
            return new ApiError(
                errorCode: OperationsErrorCode::ValidationFailed,
                status: 422,
                message: 'Input validation failed.',
                details: JsonDocuments::capture($error->errors()),
                category: ErrorCategory::Validation,
            );
        }

        if ($error instanceof CentralContextException) {
            return new ApiError(
                errorCode: OperationsErrorCode::ContextRequired,
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
            401 => [OperationsErrorCode::AuthenticationRequired, 'Authentication is required.'],
            403 => [OperationsErrorCode::Forbidden, 'Operation is not permitted.'],
            404 => [OperationsErrorCode::ResourceNotFound, 'Resource not found.'],
            409 => [OperationsErrorCode::Conflict, 'Operation conflicts with current state.'],
            422 => [OperationsErrorCode::ValidationFailed, 'Input validation failed.'],
            429 => [OperationsErrorCode::RateLimited, 'Too many requests.'],
            default => $status >= 400 && $status < 500
                ? [OperationsErrorCode::RequestRejected, 'Request could not be completed.']
                : [CoreErrorCode::InternalError, 'Operation could not be completed.'],
        };

        if ($code === CoreErrorCode::InternalError) {
            if ($reportUnexpected) {
                $this->exceptions->report($error);
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

    private function unexpected(Throwable $error, bool $reportUnexpected): ApiError
    {
        if ($reportUnexpected) {
            $this->exceptions->report($error);
        }

        return new ApiError(errorCode: CoreErrorCode::InternalError, status: 500, message: 'Operation could not be completed.', category: ErrorCategory::Internal);
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
