<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

/** Digest-bound admitted schema closure for offline validation. */
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
