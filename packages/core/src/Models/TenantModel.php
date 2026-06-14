<?php

declare(strict_types=1);

namespace CoreX\Models;

use CoreX\Concerns\BelongsToTenant;

/**
 * Base for tenant-owned domain models: everything in BaseModel plus the
 * TenantScope and tenant auto-stamping (ADR-004). Use BaseModel instead for
 * global/system entities that have no tenant column.
 */
abstract class TenantModel extends BaseModel
{
    use BelongsToTenant;
}
