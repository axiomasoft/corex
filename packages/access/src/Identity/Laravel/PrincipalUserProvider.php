<?php

declare(strict_types=1);

namespace CoreX\Access\Identity\Laravel;

use CoreX\Access\Access\Laravel\PrincipalIdentity;
use CoreX\Access\Identity\Internal\LowercaseColumn;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Context\Contracts\IdentityPasswordRehasher;
use CoreX\Context\Enums\PrincipalType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use SensitiveParameter;
use stdClass;

/**
 * The boxed-and-cloud login lane's own {@see UserProvider} (D125, OQ-18
 * variant A): reads a users-shaped table through query-builder, entirely by
 * {@see AuthOptions} identity fields — connection/table/column names — so
 * `axiomasoft/corex-access` never imports a model from `axiomasoft/corex-tenancy` (AC-28). Every
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
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class PrincipalUserProvider implements UserProvider
{
    public function __construct(
        private AuthOptions $options,
    ) {}

    #[Override]
    public function retrieveById($identifier): ?Authenticatable
    {
        $row = $this->query()->where($this->idColumn(), $identifier)->first();

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

    /** @param  array<array-key, mixed>  $credentials */
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
            ->where(new LowercaseColumn($this->emailColumn()), mb_strtolower($email))
            ->first();

        return $this->toPrincipal($row);
    }

    /** @param  array<array-key, mixed>  $credentials */
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

    /** @param  array<array-key, mixed>  $credentials */
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

        resolve(IdentityPasswordRehasher::class)->rehash(
            identityId: $user->getAuthIdentifier(),
            expectedHash: $hash,
            replacementHash: Hash::make($password),
            table: $this->options->identityTable,
            columns: $this->options->identityColumns,
            connection: $this->options->identityConnection,
        );
    }

    /** "Live user" filter shared by every lookup — see class docblock. */
    private function query(): Builder
    {
        return DB::connection($this->options->identityConnection)
            ->table($this->options->identityTable)
            ->where($this->activeColumn(), true)
            ->whereNull($this->deletedAtColumn());
    }

    private function toPrincipal(?stdClass $row): ?PrincipalIdentity
    {
        if ($row === null) {
            return null;
        }

        $attributes = get_object_vars($row);
        $id = $attributes[$this->idColumn()] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        $hash = $attributes[$this->passwordColumn()] ?? null;

        return new PrincipalIdentity(
            type: PrincipalType::User,
            id: $id,
            passwordHash: is_string($hash) ? $hash : null,
        );
    }

    private function idColumn(): string
    {
        return $this->options->identityColumns['id'];
    }

    private function emailColumn(): string
    {
        return $this->options->identityColumns['email'];
    }

    private function passwordColumn(): string
    {
        return $this->options->identityColumns['password'];
    }

    private function activeColumn(): string
    {
        return $this->options->identityColumns['active'];
    }

    private function deletedAtColumn(): string
    {
        return $this->options->identityColumns['deleted_at'];
    }
}
