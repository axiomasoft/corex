<?php

declare(strict_types=1);

namespace Flex\Crm\Activity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Packman\Attributes\Internal;

/** @property string $entity_type */
#[Internal]
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
