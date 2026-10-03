<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Enums;

use CoreX\Attributes\Api;

/** Input shape; rejecting undeclared fields remains an invariant of dispatch. */
#[Api(since: '1.0')]
enum InputEnvelope: string
{
    case Query = 'query';
    case JsonObject = 'json-object';

    public static function forMethod(string $method): self
    {
        return strtoupper($method) === 'GET' ? self::Query : self::JsonObject;
    }
}
