<?php

declare(strict_types=1);

namespace CoreX\Modules;

/**
 * Manifest dependency declaration (B-10 §3.2): a module or a PHP extension.
 * `requires()` deps mirror composer `require` — the compiler rejects a
 * mismatch (P1.4); `suggests()` deps are soft hints only.
 */
final readonly class Dep
{
    private function __construct(
        public string $type,
        public string $target,
        public string $constraint,
    ) {}

    public static function module(string $composerName, string $constraint = '*'): self
    {
        return new self(type: 'module', target: $composerName, constraint: $constraint);
    }

    public static function phpExtension(string $extension): self
    {
        return new self(type: 'php-extension', target: $extension, constraint: '*');
    }
}
