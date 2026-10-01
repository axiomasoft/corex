<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use Illuminate\Http\JsonResponse;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ResponseSerializer
{
    /** @param  array<array-key, mixed>  $data */
    public function success(array $data, int $status, InvocationMetadata $metadata): JsonResponse;

    public function error(ApiError $error, InvocationMetadata $metadata): JsonResponse;
}
