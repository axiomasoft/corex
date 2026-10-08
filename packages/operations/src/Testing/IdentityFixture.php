<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Enums\PrincipalType;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use Illuminate\Http\Request;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IdentityFixture implements Principal, PrincipalResolver
{
    public bool $authenticated = true;

    public function principalType(): PrincipalType
    {
        return PrincipalType::User;
    }

    public function principalId(): string
    {
        return 'trusted-user';
    }

    public function resolve(Request $request): ?Principal
    {
        return $this->authenticated ? $this : null;
    }
}
