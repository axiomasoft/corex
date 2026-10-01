<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class InvocationMetadataFactory
{
    public static function fromRequest(Request $request, string $version): InvocationMetadata
    {
        $supplied = $request->headers->get('X-Request-ID');
        $requestId = is_string($supplied) && self::isOpaqueId($supplied) ? $supplied : (string) Str::uuid();
        $correlation = $request->headers->get('X-Correlation-ID');
        $accept = $request->headers->get('Accept');

        return new InvocationMetadata(requestId: $requestId, version: $version, operationId: '', method: $request->method(), correlationId: is_string($correlation) ? $correlation : null, accept: is_string($accept) ? $accept : '');
    }

    private static function isOpaqueId(?string $value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9_.-]{1,128}$/D', $value) === 1;
    }
}
