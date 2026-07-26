<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * Who performed an audited change (sys_audit_log CHECK constraint).
 *
 * @internal spec: B-10 §2
 */
enum ActorType: string
{
    case User = 'user';
    case Agent = 'agent';
    case ApiKey = 'api_key';
    case System = 'system';
}
