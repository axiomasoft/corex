<?php

declare(strict_types=1);

namespace Flex\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** @property string $entity_type */
final class ActivityTarget extends Model
{
    protected $table = 'crm_activity_targets';

    public $incrementing = false;

    public $timestamps = false;

    /** @return MorphTo<Model, $this> */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
