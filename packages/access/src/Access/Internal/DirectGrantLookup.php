<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use AzGuard\Models\DirectGrant;
use CoreX\Access\Contracts\Principal;
use CoreX\Access\Internal\Support\AuthOptions;

/**
 * Whether a principal holds an active AzGuard direct grant for one compiled
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
        private AuthOptions $options,
    ) {}

    public function hasActive(Principal $p, string $key): bool
    {
        return DirectGrant::query()
            ->where('grantable_type', $p->principalType()->value)
            ->where('grantable_id', $p->principalId())
            ->where('panel_id', $this->panel())
            ->where('permission_key', $key)
            ->active()
            ->exists();
    }

    private function panel(): string
    {
        return $this->options->panel;
    }
}
