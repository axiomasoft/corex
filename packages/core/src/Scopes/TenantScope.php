<?php

declare(strict_types=1);

namespace CoreX\Scopes;

use CoreX\Contracts\TenantService;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Override;

/**
 * Filters tenant-owned models by the active tenant. No-op when no tenant is
 * active (on-premise, console, seeders) so the same code runs single-tenant.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    #[Override]
    public function apply(Builder $builder, Model $model): void
    {
        $tenantKey = $this->activeTenantKey();

        if ($tenantKey === null) {
            return;
        }

        $builder->where($model->qualifyColumn(Config::tenantColumn()), $tenantKey);
    }

    /**
     * @param  Builder<Model>  $builder
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenant', fn (Builder $query): Builder => $query->withoutGlobalScope(self::class));

        $builder->macro('forTenant', function (Builder $query, string $tenantKey): Builder {
            $column = $query->getModel()->qualifyColumn(Config::tenantColumn());

            return $query->withoutGlobalScope(self::class)->where($column, $tenantKey);
        });
    }

    private function activeTenantKey(): ?string
    {
        if (! app()->bound(TenantService::class)) {
            return null;
        }

        $service = app(TenantService::class);

        return $service->hasTenant() ? $service->currentKey() : null;
    }
}
