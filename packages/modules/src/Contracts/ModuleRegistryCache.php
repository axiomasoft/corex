<?php

declare(strict_types=1);

namespace Flex\Modules\Contracts;

use Closure;

/**
 * Cache seam for {@see ModuleRegistry}'s per-account enabled-set (D30/D39).
 *
 * The implementation MUST NOT call `Repository::tags()`: file/database stores
 * are not taggable, so tagging throws `BadMethodCallException` on the default
 * Laravel cache and in the boxed profile (B-10 §6.1) — the Blocker A1/A2 this
 * contract exists to kill. The account id already namespaces the key, so tags
 * bought nothing anyway.
 *
 * Invalidation is by EPOCH (D39), not by `forget()`: the caller stamps a
 * monotonic per-account `$version` into the key. A bumped version yields a
 * fresh key, so a stale entry is never read and nothing has to be cleared —
 * which is exactly why the fix survives a multi-node file cache and a
 * long-running `queue:work` (pre-mortem C2), where a caller-local `forget()`
 * would not.
 */
interface ModuleRegistryCache
{
    /**
     * @param  Closure(): array<string, array{state: string, edition: string}>  $resolver
     * @return array<string, array{state: string, edition: string}>
     */
    public function rememberEnabled(string $accountId, int $version, Closure $resolver): array;
}
