<?php

declare(strict_types=1);

namespace CoreX\Operations\Pagination\Contracts;

/**
 * Signing and verifying keys for `api-cursor/1`.
 *
 * The current key signs new cursors. Every key still in the ring can verify.
 * A retired verifying key must stay available for at least the maximum cursor TTL.
 */
interface CursorKeyring
{
    public function currentKeyId(): string;

    public function ttlSeconds(): int;

    public function verifyingSecret(string $keyId): ?string;
}
