<?php

declare(strict_types=1);

namespace Flex\Crm\Context\Models\Concerns;

use Flex\Crm\Activity\Models\Activity;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasActivities
{
    /** @return BelongsToMany<Activity, $this> */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(related: Activity::class, table: 'crm_activity_targets', foreignPivotKey: 'entity_id', relatedPivotKey: 'activity_id')
            ->wherePivot('entity_type', $this->getMorphClass());
    }
}
