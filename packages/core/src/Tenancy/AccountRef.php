<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

/**
 * Canonical shape — B-10 §3.1 / B-11 §3.1 (owner: B-11).
 */
final readonly class AccountRef
{
    /**
     * @param  array<int, string>  $features
     * @param  array<string, mixed>  $limits
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $status,
        public array $features,
        public array $limits,
    ) {}
}
