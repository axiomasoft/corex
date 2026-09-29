<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Contracts;

use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\InvocationMetadata;
use Illuminate\Http\JsonResponse;

interface ResponseSerializer
{
    /** @param  array<string, mixed>|list<mixed>  $data */
    public function success(array $data, int $status, InvocationMetadata $metadata): JsonResponse;

    public function error(ApiError $error, InvocationMetadata $metadata): JsonResponse;
}
