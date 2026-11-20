<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use CoreX\Data\Data\ScopeIdentity;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ScopePrincipal
{
    public function __construct(public string $kind, public string $id)
    {
        ScopeIdentity::principal($kind, $id);
    }
}
