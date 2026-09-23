<?php

declare(strict_types=1);

namespace CoreX\Support;

use LogicException;
use SensitiveParameter;
use Stringable;

final class SecretRef implements Stringable
{
    public function __construct(
        #[SensitiveParameter]
        private readonly string $value,
    ) {}

    public function reveal(): string
    {
        return $this->value;
    }

    public function isPresent(): bool
    {
        return $this->value !== '';
    }

    public function __toString(): string
    {
        return '[redacted]';
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }

    /** @return array<string, string> */
    public function __serialize(): array
    {
        throw new LogicException('SecretRef must not be serialized.');
    }
}
