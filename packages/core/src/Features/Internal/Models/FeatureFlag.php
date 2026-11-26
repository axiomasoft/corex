<?php

declare(strict_types=1);

namespace CoreX\Features\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Packman\Attributes\Internal;

/**
 * Per-account feature-flag default (sys_feature_flags).
 *
 * @property string $id
 * @property string $key
 * @property string|null $module
 * @property bool $is_enabled
 * @property array<array-key, mixed>|bool|float|int|string|null $payload
 *
 * @internal
 */
#[Internal]
final class FeatureFlag extends Model
{
    use HasUuids;

    protected $table = 'sys_feature_flags';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'bool',
            'payload' => 'json',
        ];
    }
}
