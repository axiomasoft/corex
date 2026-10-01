<?php

declare(strict_types=1);

namespace CoreX\Access\Enums;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum PrincipalType: string
{
    case User = 'user';
    case Agent = 'agent';
    case ApiKey = 'api_key';
}
