<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Data\RecordContext;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Context\Contracts\Principal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * `apply()`'s phase order is a byte-exact mirror of
 * `DefaultAccessChecker::canOnRecord()` (D110): (1) an active direct grant
 * bypasses the scope predicate entirely; (2) no capability at all ⇒ the
 * whole query denies (`WHERE 1 = 0`); (3) otherwise the compiled scope,
 * normalized against the entity's declared capabilities
 * ({@see ScopeAvailability}, D114), is applied via {@see ScopePredicate} —
 * the single owner of scope semantics shared with the record path.
 *
 * @internal spec: B-11 §3.3/§5.5, P2.9 D110/D114/D116
 */
final readonly class DefaultScopeResolver implements ScopeResolver
{
    public function __construct(
        private AccessChecker $accessChecker,
        private ScopePredicate $predicate,
        private ScopeAvailability $availability,
        private DirectGrantLookup $directGrants,
        private AuthOptions $options,
        private EntityActivity $activity,
    ) {}

    /**
     * @param  Builder<Model>  $q
     * @return Builder<Model>
     */
    #[Override]
    public function apply(Builder $q, Principal $p, string $entityKey, string $action, ?RecordContext $ctx = null): Builder
    {
        if (! $this->activity->isActive($entityKey)) {
            return $this->deny($q);
        }

        $key = "{$this->panel()}.{$entityKey}.{$action}";

        if ($this->directGrants->hasActive(p: $p, key: $key)) {
            return $q;
        }

        if (! $this->accessChecker->allowed(p: $p, entityKey: $entityKey, action: $action)) {
            return $this->deny($q);
        }

        $scope = $this->availability->normalize(entityKey: $entityKey, scope: $this->accessChecker->scopeFor(p: $p, entityKey: $entityKey, action: $action, ctx: $ctx));

        return $this->predicate->applyTo(q: $q, scope: $scope, p: $p);
    }

    private function panel(): string
    {
        return $this->options->panel;
    }

    /** @param Builder<Model> $q
     * @return Builder<Model>
     */
    private function deny(Builder $q): Builder
    {
        $q->getQuery()->whereRaw('1 = 0');

        return $q;
    }
}
