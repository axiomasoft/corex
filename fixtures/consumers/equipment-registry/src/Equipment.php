<?php

declare(strict_types=1);

namespace Equipment\Registry;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Equipment extends Model
{
    use SoftDeletes;

    public const string Handle = 'eq.equipment';

    protected $table = 'eq_equipment';

    protected $primaryKey = 'asset_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;
}
