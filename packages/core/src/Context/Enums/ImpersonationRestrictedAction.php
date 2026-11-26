<?php

declare(strict_types=1);

namespace CoreX\Context\Enums;

use Packman\Attributes\Api;

/**
 * The five operations forbidden during an impersonated session
 * (Semantic layer, for privileged paths that reach neither L1's HTTP
 * method check nor L2's Eloquent event). Closed action set —
 * not a capability-key list (no such keys exist in the tree).
 */
#[Api(since: '1.0')]
enum ImpersonationRestrictedAction
{
    case ChangePassword;
    case ChangeEmail;
    case Payment;
    case DeleteAccount;
    case GrantPrivilegedRole;
}
