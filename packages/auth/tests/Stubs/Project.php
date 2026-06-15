<?php

declare(strict_types=1);

namespace CoreX\Auth\Tests\Stubs;

use CoreX\Models\TenantModel;

/**
 * Tenant-owned stub used as an authorization context entity.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 */
final class Project extends TenantModel
{
    protected $guarded = [];
}
