<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use CoreX\Attributes\Api;

/**
 * Immutable reference to an account: identity, slug, lifecycle status, and
 * its enabled features/limits.
 *
 * Contract: B-10 §3.1, B-11 §3.1 (owner: B-11).
 */
#[Api(since: '1.0')]
final readonly class AccountRef
{
    /**
     * @param  array<int, string>  $features
     * @param  array<string, int|string|bool>  $limits
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $status,
        public array $features,
        public array $limits,
    ) {}
}
