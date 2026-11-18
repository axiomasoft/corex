<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Contracts;

use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Errors\Exceptions\ApiError;
use Illuminate\Http\JsonResponse;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ResponseSerializer
{
    /** @param  array<array-key, mixed>  $data */
    public function success(array $data, int $status, InvocationMetadata $metadata): JsonResponse;

    public function error(ApiError $error, InvocationMetadata $metadata): JsonResponse;
}
