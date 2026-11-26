<?php

declare(strict_types=1);

namespace CoreX\Context\Enums;

use Packman\Attributes\Api;

/**
 * The five operations B-11 §5.9 п.3 forbids under an impersonated session
 * (L3 — semantic layer, for privileged paths that reach neither L1's HTTP
 * method check nor L2's Eloquent event). CLOSED vocabulary from the spec —
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
