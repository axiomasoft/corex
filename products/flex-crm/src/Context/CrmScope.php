<?php

declare(strict_types=1);

namespace Flex\Crm\Context;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Packman\Attributes\Internal;

/**
 * @internal
 *
 * @implements Scope<Model>
 */
#[Internal]
final class CrmScope implements Scope
{
    /** @param Builder<covariant Model> $builder */
    public function apply(Builder $builder, Model $model): void
    {
        $ctx = resolve(ContextGuard::class)->current();

        if ($model->getConnection()->getName() !== $ctx->connectionName) {
            throw new AuthorizationException('CRM query connection differs from its execution context.');
        }
        $column = $model->qualifyColumn('workspace_id');
        $builder->where(function (Builder $query) use ($ctx, $column): void {
            if ($ctx->tenant->workspace !== null) {
                $query->where($column, $ctx->tenant->workspace->id);

                if ($ctx->allowAccountLevel) {
                    $query->getQuery()->orWhereNull($column);
                }
            } else {
                $query->getQuery()->whereNull($column);
            }
        });
    }
}
