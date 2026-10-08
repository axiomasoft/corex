<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @corex-model-extension Names the Eloquent global scope for workspace-scoped models; adds no columns or relations.
 * Names the Eloquent global scope that isolates workspace-scoped rows.
 * Bound by axiomasoft/corex-tenancy; unbound ⇒ the install has no
 * workspaces, so consumers (axiomasoft/corex-access) apply no workspace scope.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WorkspaceScopeProvider
{
    /** @return class-string<Scope<Model>> */
    public function scopeClass(): string;
}
