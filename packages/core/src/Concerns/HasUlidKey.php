<?php

declare(strict_types=1);

namespace CoreX\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * ULID primary keys (ADR-003). A single named seam so a future switch to
 * UUIDv7 (open question #7) touches one trait, not every model.
 */
trait HasUlidKey
{
    use HasUlids;
}
