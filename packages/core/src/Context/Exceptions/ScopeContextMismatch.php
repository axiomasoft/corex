<?php

declare(strict_types=1);

namespace CoreX\Context\Exceptions;

use RuntimeException;

final class ScopeContextMismatch extends RuntimeException
{
    public static function account(string $expected, string $actual): self
    {
        return new self("Data scope account [{$expected}] does not match the active context [{$actual}].");
    }

    public static function workspace(?string $expected, ?string $actual): self
    {
        return new self(sprintf(
            'Data scope workspace [%s] does not match the active context [%s].',
            $expected ?? 'none',
            $actual ?? 'none',
        ));
    }

    public static function connection(string $expected, string $actual): self
    {
        return new self("Data scope connection [{$expected}] does not match the active connection [{$actual}].");
    }

    public static function profile(bool $expectedBoxed): self
    {
        return new self($expectedBoxed
            ? 'Data scope was produced by a boxed profile but the active context is not boxed.'
            : 'Data scope was produced by a tenant profile but the active context is boxed.');
    }
}
