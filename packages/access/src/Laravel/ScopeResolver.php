<?php

declare(strict_types=1);

namespace CoreX\Access\Laravel;

use CoreX\Access\Data\RecordContext;
use CoreX\Context\Contracts\Principal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * @corex-model-extension Narrows the caller's Eloquent query to visible records.
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
