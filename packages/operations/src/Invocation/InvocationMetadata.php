<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class InvocationMetadata
{
    public string $correlationId;

    public string $accept;

    public function __construct(
        public string $requestId,
        public string $version,
        public string $operationId,
        public string $method,
        ?string $correlationId = null,
        ?string $accept = null,
    ) {
        $this->correlationId = self::isOpaqueId($correlationId) ? $correlationId : $requestId;
        $this->accept = is_string($accept) ? $accept : '';
    }

    public static function fromRequest(Request $request, string $version): self
    {
        $supplied = $request->headers->get('X-Request-ID');
        $requestId = is_string($supplied) && self::isOpaqueId($supplied)
            ? $supplied
            : (string) Str::uuid();

        $correlation = $request->headers->get('X-Correlation-ID');
        $accept = $request->headers->get('Accept');

        return new self(
            requestId: $requestId,
            version: $version,
            operationId: '',
            method: $request->method(),
            correlationId: is_string($correlation) ? $correlation : null,
            accept: is_string($accept) ? $accept : '',
        );
    }

    private static function isOpaqueId(?string $value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9_.-]{1,128}$/D', $value) === 1;
    }
}
