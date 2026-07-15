<?php

declare(strict_types=1);

namespace Flex\Modules\Enums;

/** Why a module was disabled (B-10 §5.1/§5.4, `mod_modules.disable_reason`). */
enum DisableReason: string
{
    case Manual = 'manual';
    case Billing = 'billing';
    case Dependency = 'dependency';
}
