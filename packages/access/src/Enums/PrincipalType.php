<?php

declare(strict_types=1);

namespace CoreX\Access\Enums;

/**
 * @internal spec: B-11 §3.3
 */
enum PrincipalType: string
{
    case User = 'user';
    case Agent = 'agent';
    case ApiKey = 'api_key';
}
