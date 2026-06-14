<?php

declare(strict_types=1);

namespace CoreX\Models;

use CoreX\Concerns\HasUlidKey;
use CoreX\Support\Config;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property string $key
 * @property string|null $value
 * @property string $scope
 * @property string|null $scope_id
 */
final class Setting extends Model
{
    use HasUlidKey;

    protected $guarded = [];

    #[Override]
    public function getTable(): string
    {
        return Config::settingsTable();
    }
}
