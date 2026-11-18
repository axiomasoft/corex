<?php

declare(strict_types=1);

namespace CoreX\Settings\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Packman\Attributes\Internal;

/**
 * @property string $namespace
 * @property string $key
 * @property string $value
 * @property string $scope_type
 * @property string|null $scope_id
 * @property bool $is_sensitive
 */
#[Internal]
final class Setting extends Model
{
    use HasUuids;

    protected $table = 'sys_settings';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'is_sensitive' => 'bool',
    ];
}
