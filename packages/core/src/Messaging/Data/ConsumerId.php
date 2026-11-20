<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Messaging\Exceptions\InvalidMessage;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ConsumerId
{
    public function __construct(public string $name, public int $version)
    {
        if ($version < 1 || strlen($name) > 120 || preg_match('/\A[a-z][a-z0-9._-]*\z/D', $name) !== 1) {
            throw InvalidMessage::identity('logical consumer');
        }
    }
}
