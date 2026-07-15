<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * Who performed an audited change (B-10 §2 sys_audit_log CHECK constraint).
 */
enum ActorType: string
{
    case User = 'user';
    case Agent = 'agent';
    case ApiKey = 'api_key';
    case System = 'system';
}
