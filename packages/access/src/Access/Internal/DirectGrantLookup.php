<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use CoreX\Context\Contracts\Principal;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Psr\Clock\ClockInterface;

/**
 * Whether a principal holds an active native direct grant for one compiled
 * permission key. Extracted out of `DefaultAccessChecker` (D110): both the
 * record path (`canOnRecord()`) and the new builder path
 * (`DefaultScopeResolver::apply()`) treat an active direct grant as a full
 * bypass of the scope predicate, so the query lives once here rather than
 * risking the two copies drifting. Adding a 4th method to the public
 * `AccessChecker` contract for this was rejected (Code Guidance if-then #5)
 * — this lookup is not part of that contract's spec'd shape.
 */
final readonly class DirectGrantLookup
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private ClockInterface $clock,
    ) {}

    public function hasActive(Principal $p, string $key): bool
    {
        $now = $this->clock->now();

        return $this->connections->connection()->table('aut_direct_grants')
            ->where('principal_type', $p->principalType()->value)
            ->where('principal_id', $p->principalId())
            ->where('permission_key', $key)
            ->whereNull('revoked_at')
            ->where(static function (Builder $q) use ($now): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->exists();
    }
}
