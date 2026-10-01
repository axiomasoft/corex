<?php

declare(strict_types=1);

namespace CoreX\Modules\Enums;

use CoreX\Attributes\Api;

/**
 * Why a module was disabled (`mod_modules.disable_reason`).
 */
#[Api(since: '1.0')]
enum DisableReason: string
{
    case Manual = 'manual';
    case Billing = 'billing';
    case Dependency = 'dependency';
}
