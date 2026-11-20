<?php

declare(strict_types=1);

namespace ThirdParty\Attributes;

use CoreX\Database\Laravel\BaseModel;

final class Inspection extends BaseModel
{
    protected $table = 'cas_inspections';

    public $timestamps = false;
}
