<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Exceptions;

use RuntimeException;

final class UnsupportedMaterialization extends RuntimeException
{
    public static function connection(string $message): self
    {
        return new self($message);
    }

    public static function expansion(string $status): self
    {
        return new self("Recurrence expansion returned unsupported status [{$status}].");
    }
}
