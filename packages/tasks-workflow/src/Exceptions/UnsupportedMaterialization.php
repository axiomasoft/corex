<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
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
