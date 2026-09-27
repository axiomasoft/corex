<?php

declare(strict_types=1);

namespace CoreX\Exceptions;

use RuntimeException;

final class UnknownMessageContract extends RuntimeException
{
    public static function notDeclared(string $name, int $version): self
    {
        return new self(
            "Message contract [{$name}.v{$version}] is not declared: a message is published only by an "
            .'explicit registry declaration (MSG-002).',
        );
    }

    public static function duplicate(string $id): self
    {
        return new self("Message contract [{$id}] is declared more than once.");
    }
}
