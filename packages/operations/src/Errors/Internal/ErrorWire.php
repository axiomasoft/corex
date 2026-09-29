<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\InvocationMetadata;

/** @internal Stable error members shared by both HTTP representations. */
final class ErrorWire
{
    /** @return array<string, string> */
    public static function meta(InvocationMetadata $metadata): array
    {
        return [
            'request_id' => $metadata->requestId,
            'correlation_id' => $metadata->correlationId,
            'api_version' => $metadata->version,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function headers(InvocationMetadata $metadata, ?ApiError $error = null): array
    {
        $headers = [
            'X-Request-ID' => $metadata->requestId,
            'Cache-Control' => 'no-store',
            'Vary' => 'Accept',
        ];
        $seconds = RetryAfterPolicy::seconds($error?->retryAfterPolicy);

        if ($seconds !== null) {
            $headers['Retry-After'] = (string) $seconds;
        }

        return $headers;
    }

    public static function type(string $problemTypeBase, string $code): string
    {
        return rtrim($problemTypeBase, '/').'/'.rawurlencode($code);
    }

    public static function instance(string $problemTypeBase, string $requestId): string
    {
        return rtrim($problemTypeBase, '/').'/instances/'.rawurlencode($requestId);
    }
}
