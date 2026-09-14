<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\ApiError;
use CoreX\Exceptions\CentralContextException;
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

        if ($error instanceof ValidationException) {
            return new ApiError('validation_failed', 422, 'Input validation failed.', $error->errors());
        }

        if ($error instanceof CentralContextException) {
            return new ApiError('context_required', 403, 'An initialized account context is required.');
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

        return new ApiError($code, $status, $message);
    }
}
