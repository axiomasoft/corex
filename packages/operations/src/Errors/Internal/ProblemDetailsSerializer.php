<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\InvocationMetadata;
use CoreX\Operations\Laravel\Support\ApiOptions;
use Illuminate\Http\JsonResponse;

/** @internal RFC 9457 projection of one mapped ApiError. */
final class ProblemDetailsSerializer
{
    public function __construct(private readonly ApiOptions $options) {}

    public function error(ApiError $error, InvocationMetadata $metadata): JsonResponse
    {
        $descriptor = $error->errorDescriptor();
        $message = $error->getMessage();
        $response = new JsonResponse([
            'type' => ErrorWire::type($this->options->problemTypeBase, $descriptor->code),
            'title' => $message,
            'status' => $error->status,
            'detail' => $message,
            'instance' => ErrorWire::instance($this->options->problemTypeBase, $metadata->requestId),
            'code' => $descriptor->code,
            'category' => $descriptor->category->value,
            'path' => $descriptor->path,
            'retryable' => $descriptor->retryable,
            'details' => (object) $descriptor->details,
            'request_id' => $metadata->requestId,
            'correlation_id' => $metadata->correlationId,
            'api_version' => $metadata->version,
        ], $error->status, ErrorWire::headers($metadata, $error));
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response;
    }
}
