<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/**
 * Lightweight module loader: a module's top-level service provider registers
 * its child providers here. Top-level providers are still discovered via
 * Laravel package auto-discovery — no external dependency (own loader).
 */
interface ModuleRegistrar
{
    /** @param class-string ...$providers */
    public function register(string ...$providers): void;

    /** @return list<class-string> */
    public function registered(): array;
}
