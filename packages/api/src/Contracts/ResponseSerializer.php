<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\ApiError;
use CoreX\Api\RequestMetadata;
use Illuminate\Http\JsonResponse;

interface ResponseSerializer
{
    /** @param  array<string, mixed>|list<mixed>  $data */
    public function success(array $data, int $status, RequestMetadata $metadata): JsonResponse;

    public function error(ApiError $error, RequestMetadata $metadata): JsonResponse;
}
