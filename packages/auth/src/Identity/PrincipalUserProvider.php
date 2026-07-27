<?php

declare(strict_types=1);

namespace CoreX\Auth\Identity;

use CoreX\Auth\Access\PrincipalIdentity;
use CoreX\Auth\Enums\PrincipalType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Override;
use SensitiveParameter;

/**
 * The boxed-and-cloud login lane's own {@see UserProvider} (D125, OQ-18
 * variant A): reads a users-shaped table through query-builder, entirely by
 * {@see config('corex-auth.identity')} — connection/table/column names — so
 * `corex/auth` never imports a model from `corex/tenancy` (AC-28). Every
 * lookup goes through {@see query()}, which always applies the "live user"
 * filter (active = true AND deleted_at IS NULL): without it in
 * retrieveById() too, a session survives the very deactivation meant to end
 * it.
 *
 * `retrieveByToken()`/`updateRememberToken()` are permanent no-ops: the
 * frozen `users` DDL (P2.7, D125) carries no `remember_token` column and one
 * is not being added — "remember me" is simply unsupported by this
 * provider.
 */
final class PrincipalUserProvider implements UserProvider
{
    public function __construct(
        private readonly ?string $connection,
        private readonly string $table,
        private readonly string $idColumn,
        private readonly string $emailColumn,
        private readonly string $passwordColumn,
        private readonly string $activeColumn,
        private readonly string $deletedAtColumn,
    ) {}

    #[Override]
    public function retrieveById($identifier): ?Authenticatable
    {
        $row = $this->query()->where($this->idColumn, $identifier)->first();

        return $this->toPrincipal($row);
    }

    /**
     * No `remember_token` column exists on the configured table (frozen
     * DB_SCHEMA) — remember-me sessions are not supported.
     */
    #[Override]
    public function retrieveByToken($identifier, #[SensitiveParameter] mixed $token): ?Authenticatable
    {
        return null;
    }

    /** No-op — see {@see retrieveByToken()}. */
    #[Override]
    public function updateRememberToken(Authenticatable $user, #[SensitiveParameter] mixed $token): void
    {
        // Intentionally empty: no remember_token column to persist into.
    }

    /** @param  array<string, mixed>  $credentials */
    #[Override]
    public function retrieveByCredentials(#[SensitiveParameter] array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return null;
        }

        // The `password` key is never used to build the WHERE (Laravel canon,
        // validated separately by validateCredentials()); the unique index on
        // this schema is `lower(email)`, so the match is case-insensitive.
        $row = $this->query()
            ->whereRaw('lower('.$this->emailColumn.') = ?', [mb_strtolower($email)])
            ->first();

        return $this->toPrincipal($row);
    }

    /** @param  array<string, mixed>  $credentials */
    #[Override]
    public function validateCredentials(Authenticatable $user, #[SensitiveParameter] array $credentials): bool
    {
        $password = $credentials['password'] ?? null;
        $hash = $user instanceof PrincipalIdentity ? $user->getAuthPassword() : null;

        if (! is_string($password) || $password === '' || ! is_string($hash) || $hash === '') {
            return false;
        }

        return Hash::check($password, $hash);
    }

    /** @param  array<string, mixed>  $credentials */
    #[Override]
    public function rehashPasswordIfRequired(Authenticatable $user, #[SensitiveParameter] array $credentials, bool $force = false): void
    {
        $password = $credentials['password'] ?? null;
        $hash = $user instanceof PrincipalIdentity ? $user->getAuthPassword() : null;

        if (! is_string($password) || $password === '' || ! is_string($hash) || $hash === '') {
            return;
        }

        if (! Hash::needsRehash($hash) && ! $force) {
            return;
        }

        $this->query()
            ->where($this->idColumn, $user->getAuthIdentifier())
            ->update([$this->passwordColumn => Hash::make($password)]);
    }

    /** "Live user" filter shared by every lookup — see class docblock. */
    private function query(): Builder
    {
        return DB::connection($this->connection)
            ->table($this->table)
            ->where($this->activeColumn, true)
            ->whereNull($this->deletedAtColumn);
    }

    private function toPrincipal(?object $row): ?PrincipalIdentity
    {
        if ($row === null) {
            return null;
        }

        $hash = $row->{$this->passwordColumn} ?? null;

        return new PrincipalIdentity(
            type: PrincipalType::User,
            id: (string) $row->{$this->idColumn},
            passwordHash: is_string($hash) ? $hash : null,
        );
    }
}
