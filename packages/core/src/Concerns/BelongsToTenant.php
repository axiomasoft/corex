<?php

declare(strict_types=1);

namespace CoreX\Concerns;

use CoreX\Contracts\TenantService;
use CoreX\Scopes\TenantScope;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * Opt-in tenant ownership: registers the TenantScope and auto-stamps the
 * tenant column on create from the active TenantService. Products add their
 * own typed `tenant()` relation (they own the tenant model).
 *
 * @mixin Model
 *
 * @deprecated Row-level tenancy is superseded by the DB-per-account model
 *     designed for phase P2. Will be replaced, not extended, in 0.2.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(static function (Model $model): void {
            $column = Config::tenantColumn();

            if ($model->getAttribute($column) !== null) {
                return;
            }

            if (! app()->bound(TenantService::class)) {
                return;
            }

            $key = app(TenantService::class)->currentKey();

            if ($key !== null) {
                $model->setAttribute($column, $key);
            }
        });
    }

    public function tenantKey(): ?string
    {
        $value = $this->getAttribute(Config::tenantColumn());

        return $value !== null ? (string) $value : null;
    }
}
