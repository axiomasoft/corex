<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

use CoreX\Operations\Invocation\Data\InvocationMetadata;
use Illuminate\Http\JsonResponse;

/** @internal */
final class EnvelopeSerializer
{
    /** @param array<array-key, mixed> $data */
    public function success(array $data, int $status, InvocationMetadata $metadata): JsonResponse
    {
        return $this->respond(body: ['data' => $data, 'meta' => ErrorWire::meta($metadata)], status: $status, headers: ErrorWire::headers($metadata));
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
