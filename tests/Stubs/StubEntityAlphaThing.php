<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal Eloquent model used by RegistryCompilerTest to exercise
 * Extend\Entity's `$casts` introspection fallback.
 */
final class StubEntityAlphaThing extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['note' => 'string'];
    }
}
