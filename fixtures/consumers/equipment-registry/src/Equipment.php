<?php

declare(strict_types=1);

namespace Equipment\Registry;

use CoreX\Database\Laravel\BaseModel;

final class Equipment extends BaseModel
{
    public const string Handle = 'eq.equipment';

    protected $table = 'eq_equipment';

    public $timestamps = false;
}
