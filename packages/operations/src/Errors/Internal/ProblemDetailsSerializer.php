<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Errors\Exceptions\ApiError;
use Illuminate\Http\JsonResponse;

/** @internal RFC 9457 projection of one mapped ApiError. */
final readonly class ProblemDetailsSerializer
{
    public function __construct(private ProblemRegistry $problems) {}

    public function error(ApiError $error, InvocationMetadata $metadata): JsonResponse
    {
        $descriptor = $error->errorDescriptor();
        $definition = $this->problems->fromWire($descriptor->code);
        $body = [
            'type' => $definition->type,
            'title' => $definition->title,
            'status' => $error->status,
            'detail' => $error->getMessage(),
            'code' => $descriptor->code,
            'category' => $descriptor->category->value,
            'retryable' => $descriptor->retryable,
            'details' => (object) $descriptor->details,
            'request_id' => $metadata->requestId,
            'correlation_id' => $metadata->correlationId,
            'api_version' => $metadata->version,
        ];

        if ($descriptor->path !== null) {
            $body['path'] = $descriptor->path;
        }

        $response = new JsonResponse(data: $body, status: $error->status, headers: ErrorWire::headers(metadata: $metadata, error: $error));
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response;
    }
}
