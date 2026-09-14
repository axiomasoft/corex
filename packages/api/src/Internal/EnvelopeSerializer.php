<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\ApiError;
use CoreX\Api\Contracts\ResponseSerializer;
use CoreX\Api\RequestMetadata;
use Illuminate\Http\JsonResponse;
use Override;

/** @internal */
final class EnvelopeSerializer implements ResponseSerializer
{
    #[Override]
    public function success(array $data, int $status, RequestMetadata $metadata): JsonResponse
    {
        return $this->respond(['data' => $data, 'error' => null, 'meta' => $this->meta($metadata)], $status, $metadata);
    }

    #[Override]
    public function error(ApiError $error, RequestMetadata $metadata): JsonResponse
    {
        return $this->respond([
            'data' => null,
            'error' => ['code' => $error->errorCode, 'message' => $error->getMessage(), 'details' => (object) $error->details],
            'meta' => $this->meta($metadata),
        ], $error->status, $metadata);
    }

    /** @return array<string, string> */
    private function meta(RequestMetadata $metadata): array
    {
        return ['request_id' => $metadata->requestId, 'api_version' => $metadata->version];
    }

    /** @param  array<string, mixed>  $body */
    private function respond(array $body, int $status, RequestMetadata $metadata): JsonResponse
    {
        return new JsonResponse($body, $status, ['X-Request-ID' => $metadata->requestId, 'Cache-Control' => 'no-store']);
    }
}
