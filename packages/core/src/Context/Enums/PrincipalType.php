<?php

declare(strict_types=1);

namespace CoreX\Context\Enums;

use CoreX\Attributes\Api;

/**
 * Kind of an authenticated principal. A strict subset of the audit
 * ActorType values: `system` and `support` are actors, never principals.
 * Moved from axiomasoft/corex-access; CoreX\Access\Enums\PrincipalType is a
 * deprecated alias of this type.
 */
#[Api(since: '1.0')]
enum PrincipalType: string
{
    case User = 'user';
    case Agent = 'agent';
    case ApiKey = 'api_key';
}
