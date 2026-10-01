<?php

declare(strict_types=1);

namespace CoreX\Access\Laravel;

use CoreX\Access\Contracts\Principal;
use CoreX\Access\Data\RecordContext;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @corex-model-extension Eloquent row-visibility extension on owner_id/workspace_id; retains caller scopes and returns the same Builder.
 * Row-visibility WHERE for a listing query (B-11 §3.3/§5.5) — the Builder
 * counterpart of {@see AccessChecker::canOnRecord()}: where that checks ONE
 * record, `apply()` narrows a whole query to the rows the principal's
 * effective scope permits. Implemented by `Access\DefaultScopeResolver`,
 * mirroring `canOnRecord()`'s phase order exactly (direct-grant bypass,
 * capability gate, then the compiled scope predicate).
 *
 * The snippet (B-11 §3.3) declares four parameters; `$ctx` is a fifth,
 * OPTIONAL parameter beyond it (D116) — `scopeFor()` already accepts a
 * `RecordContext` and `apply()` needed the same contextual-narrowing
 * capability (AC-24) to be reachable on the builder path. Backward
 * compatible with the snippet's own call shape.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ScopeResolver
{
    /**
     * @param  Builder<Model>  $q
     * @return Builder<Model>
     */
    public function apply(Builder $q, Principal $p, string $entityKey, string $action, ?RecordContext $ctx = null): Builder;
}
