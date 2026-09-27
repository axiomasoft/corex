<?php

declare(strict_types=1);

namespace CoreX\Contracts;

/** Owner port for replacing a verified identity password hash. */
interface IdentityPasswordRehasher
{
    /**
     * Compare-and-swap the hash on an active identity. The implementation
     * must reject unsupported table or column mappings before any write.
     *
     * @param  array<string, string>  $columns
     */
    public function rehash(
        string $identityId,
        string $expectedHash,
        string $replacementHash,
        string $table,
        array $columns,
        ?string $connection,
    ): void;
}
