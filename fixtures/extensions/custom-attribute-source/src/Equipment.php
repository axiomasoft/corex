<?php

declare(strict_types=1);

namespace ThirdParty\Attributes;

use CoreX\Database\Laravel\BaseModel;

final class Equipment extends BaseModel
{
    public const string Handle = 'cas.equipment';

    protected $table = 'cas_equipment';

    public $timestamps = false;
}
