<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\TenantContextResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SearchScenarioRecord extends Model
{
    public const string ENTITY = 'scenario.search-record';

    protected $table = 'scenario_search_records';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected static function booted(): void
    {
        self::addGlobalScope('scenarioOwnerWorkspace', static function (Builder $query): void {
            $workspace = app(TenantContextResolver::class)->current()->workspace?->id;
            $query->where('scenario_search_records.workspace_id', $workspace);
        });
    }
}
