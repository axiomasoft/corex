<?php

declare(strict_types=1);

namespace CoreX\Operations\Validation\Data;

use CoreX\Attributes\Api;

/** Digest-bound admitted schema closure for offline validation. */
#[Api(since: '1.0')]
final readonly class SchemaHandle
{
    /**
     * @param  array<string, string>  $localResources
     */
    public function __construct(
        public string $digest,
        public string $profile,
        public array $localResources,
    ) {}
}
