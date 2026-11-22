<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Errors\Laravel\ProblemProjection;
use Illuminate\Http\JsonResponse;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ProblemRegistry;

/** @internal RFC 9457 projection of one mapped ApiError. */
#[Internal]
final readonly class ProblemDetailsSerializer
{
    public function __construct(private ProblemRegistry $problems) {}

    public function error(ApiError $error, InvocationMetadata $metadata): JsonResponse
    {
        $body = [
            ...(new ProblemProjection($this->problems))->document($error),
            'request_id' => $metadata->requestId,
            'correlation_id' => $metadata->correlationId,
            'api_version' => $metadata->version,
        ];

        $response = new JsonResponse(data: $body, status: $error->status, headers: ErrorWire::headers(metadata: $metadata, error: $error));
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response;
    }
}
