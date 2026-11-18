<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Internal;

use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Errors\Exceptions\ApiError;
use Packman\Attributes\Internal;

/** @internal Response metadata and headers. */
#[Internal]
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
        ];
        $seconds = RetryAfterPolicy::seconds($error?->retryAfterPolicy);

        if ($seconds !== null) {
            $headers['Retry-After'] = (string) $seconds;
        }

        return $headers;
    }
}
