<?php

declare(strict_types=1);

namespace CoreX\Messaging\Exceptions;

use InvalidArgumentException;

final class InvalidMessage extends InvalidArgumentException
{
    public static function identity(string $name): self
    {
        return new self("Invalid message {$name}.");
    }

    public static function contractName(string $value): self
    {
        return new self("Message contract name [{$value}] must be a dotted lowercase name, not a PHP class name.");
    }

    public static function payloadShape(string $reason): self
    {
        return new self("Message payload {$reason}.");
    }
}
