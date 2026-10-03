<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use Illuminate\Http\JsonResponse;
use Override;

/** @internal HTTP projection. The invoker stays transport-neutral. */
final class HttpResponseSerializer implements ResponseSerializer
{
    public function __construct(
        private readonly EnvelopeSerializer $envelope,
        private readonly ProblemDetailsSerializer $problems,
    ) {}

    #[Override]
    public function success(array $data, int $status, InvocationMetadata $metadata): JsonResponse
    {
        return $this->envelope->success($data, $status, $metadata);
    }

    #[Override]
    public function error(ApiError $error, InvocationMetadata $metadata): JsonResponse
    {
        return $this->problems->error(error: $error, metadata: $metadata);
    }
}
