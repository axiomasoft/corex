<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\ApiError;
use CoreX\Api\Contracts\ResponseSerializer;
use CoreX\Api\InvocationMetadata;
use Illuminate\Http\JsonResponse;
use Override;

/** @internal */
final class EnvelopeSerializer implements ResponseSerializer
{
    #[Override]
    public function success(array $data, int $status, InvocationMetadata $metadata): JsonResponse
    {
        return $this->respond(['data' => $data, 'error' => null, 'meta' => ErrorWire::meta($metadata)], $status, ErrorWire::headers($metadata));
    }

    #[Override]
    public function error(ApiError $error, InvocationMetadata $metadata): JsonResponse
    {
        $descriptor = $error->errorDescriptor();

        return $this->respond([
            'data' => null,
            'error' => [
                'code' => $descriptor->code,
                'message' => $error->getMessage(),
                'category' => $descriptor->category->value,
                'path' => $descriptor->path,
                'retryable' => $descriptor->retryable,
                'details' => (object) $descriptor->details,
            ],
            'meta' => ErrorWire::meta($metadata),
        ], $error->status, ErrorWire::headers($metadata, $error));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    private function respond(array $body, int $status, array $headers): JsonResponse
    {
        return new JsonResponse($body, $status, $headers);
    }
}
