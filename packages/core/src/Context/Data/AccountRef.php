<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use Packman\Attributes\Api;

/**
 * Immutable reference to an account: identity, slug, lifecycle status, and
 * its enabled features/limits.
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
