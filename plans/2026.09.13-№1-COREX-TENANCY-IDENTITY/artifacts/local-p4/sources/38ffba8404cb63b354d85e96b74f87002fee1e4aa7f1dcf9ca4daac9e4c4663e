<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Stubs;

use CoreX\Tenancy\Http\Controllers\OidcLoginController;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Override;
use SensitiveParameter;

/**
 * Stands in for the app's configured `auth.provider` (parity, premortem C4,
 * half 2): {@see OidcLoginController}
 * ends in `Auth::guard($guard)->loginUsingId($sub)`, which resolves
 * through THIS class, never a `PrincipalIdentity` the controller
 * constructs itself — {@see $retrievedByIdCalls} is the proof.
 */
final class StubIdentityProvider implements UserProvider
{
    /** @var list<string> */
    public array $retrievedByIdCalls = [];

    #[Override]
    public function retrieveById($identifier): ?Authenticatable
    {
        $this->retrievedByIdCalls[] = (string) $identifier;

        return new StubIdentity((string) $identifier);
    }

    #[Override]
    public function retrieveByToken($identifier, #[SensitiveParameter] mixed $token): ?Authenticatable
    {
        return null;
    }

    #[Override]
    public function updateRememberToken(Authenticatable $user, #[SensitiveParameter] mixed $token): void
    {
        // Not exercised by these tests.
    }

    /** @param  array<string, mixed>  $credentials */
    #[Override]
    public function retrieveByCredentials(#[SensitiveParameter] array $credentials): ?Authenticatable
    {
        return null;
    }

    /** @param  array<string, mixed>  $credentials */
    #[Override]
    public function validateCredentials(Authenticatable $user, #[SensitiveParameter] array $credentials): bool
    {
        return false;
    }

    /** @param  array<string, mixed>  $credentials */
    #[Override]
    public function rehashPasswordIfRequired(Authenticatable $user, #[SensitiveParameter] array $credentials, bool $force = false): void
    {
        // Not exercised by these tests.
    }
}
