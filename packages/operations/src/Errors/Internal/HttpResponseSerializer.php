<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Errors\Exceptions\ApiError;
use Illuminate\Http\JsonResponse;
use Override;

/** @internal HTTP projection. The invoker stays transport-neutral. */
final readonly class HttpResponseSerializer implements ResponseSerializer
{
    public function __construct(
        private EnvelopeSerializer $envelope,
        private ProblemDetailsSerializer $problems,
    ) {}

    #[Override]
    public function success(array $data, int $status, InvocationMetadata $metadata): JsonResponse
    {
        return $this->envelope->success(data: $data, status: $status, metadata: $metadata);
    }

    #[Override]
    public function error(ApiError $error, InvocationMetadata $metadata): JsonResponse
    {
        return $this->problems->error(error: $error, metadata: $metadata);
    }
}
