<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * Hierarchical scope for a setting value: system-wide, per-tenant, or per-user.
 */
enum SettingScope: string
{
    case System = 'system';
    case Tenant = 'tenant';
    case User = 'user';
}
