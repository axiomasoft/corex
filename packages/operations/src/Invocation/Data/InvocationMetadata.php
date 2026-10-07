<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
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
        $this->correlationId = is_string($correlationId) && self::isOpaqueId($correlationId) ? $correlationId : $requestId;
        $this->accept = is_string($accept) ? $accept : '';
    }

    private static function isOpaqueId(?string $value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9_.-]{1,128}$/D', $value) === 1;
    }
}
