<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\Principal;
use CoreX\Context\Enums\PrincipalType;
use Illuminate\Auth\GenericUser;

final class ScenarioUser extends GenericUser implements Principal
{
    public function principalType(): PrincipalType
    {
        return PrincipalType::User;
    }

    public function principalId(): string
    {
        return (string) $this->getAuthIdentifier();
    }
}
