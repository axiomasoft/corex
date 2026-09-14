<?php

declare(strict_types=1);

namespace CoreX\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class RequestMetadata
{
    public function __construct(
        public string $requestId,
        public string $version,
        public string $operationId,
        public string $method,
    ) {}

    public static function fromRequest(Request $request, string $version): self
    {
        $supplied = $request->headers->get('X-Request-ID');
        $requestId = is_string($supplied) && preg_match('/^[a-zA-Z0-9_.-]{1,128}$/D', $supplied) === 1
            ? $supplied : (string) Str::uuid();

        return new self($requestId, $version, '', $request->method());
    }
}
