<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use CoreX\Api\Contracts\Principal;
use CoreX\Api\Contracts\PrincipalResolver;
use Illuminate\Http\Request;

final class IdentityFixture implements Principal, PrincipalResolver
{
    public bool $authenticated = true;

    public function id(): string
    {
        return 'trusted-user';
    }

    public function resolve(Request $request): ?Principal
    {
        return $this->authenticated ? $this : null;
    }
}
