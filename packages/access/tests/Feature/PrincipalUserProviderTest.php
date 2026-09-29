<?php

declare(strict_types=1);

use CoreX\Access\Access\PrincipalIdentity;
use CoreX\Access\Enums\PrincipalType;
use CoreX\Access\Identity\PrincipalUserProvider;
use CoreX\Access\Support\AuthOptions;
use CoreX\Context\Contracts\IdentityPasswordRehasher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// P2.22 — PrincipalUserProvider (D125, OQ-18 variant A): the boxed-and-cloud
// login lane's own UserProvider, entirely config-driven (table/columns), no
// dependency on axiomasoft/corex-tenancy's `users` model. `tests/database/migrations`
// (loaded by CoreAuthTestCase) does not carry a `users` fixture — every test
// below assembles the table it needs itself, mirroring PermissionSurfaceTest's
// inline Schema::create() precedent.
beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('email');
        $table->string('password_hash')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamp('deleted_at')->nullable();
    });
    app()->instance(IdentityPasswordRehasher::class, new class implements IdentityPasswordRehasher
    {
        public function rehash(string $identityId, string $expectedHash, string $replacementHash, string $table, array $columns, ?string $connection): void
        {
            DB::connection($connection)->table($table)
                ->where($columns['id'], $identityId)
                ->where($columns['password'], $expectedHash)
                ->update([$columns['password'] => $replacementHash]);
        }
    });
});

function makeDefaultProviderPUP(): PrincipalUserProvider
{
    return new PrincipalUserProvider(
        options: AuthOptions::fromArray([]),
    );
}

/** @return array{id: string} */
function insertUserPUP(array $overrides = []): array
{
    $id = (string) Str::uuid();

    DB::table('users')->insert(array_merge([
        'id' => $id,
        'email' => 'user-'.$id.'@example.test',
        'password_hash' => Hash::make('correct-password'),
        'is_active' => true,
        'deleted_at' => null,
    ], $overrides));

    return ['id' => $id];
}

// (а) неизвестный e-mail → retrieveByCredentials() = null.
it('returns null from retrieveByCredentials() for an unknown email', function (): void {
    insertUserPUP();

    $result = makeDefaultProviderPUP()->retrieveByCredentials(['email' => 'ghost@example.test', 'password' => 'whatever']);

    expect($result)->toBeNull();
});

// (б) is_active=false → null в retrieveByCredentials() И в retrieveById() — a
// deactivated user must not be resolvable at all, not just unable to log in
// fresh: without the filter in retrieveById() too, a live session survives
// the very deactivation meant to end it.
it('returns null from both retrieveByCredentials() and retrieveById() for a deactivated user', function (): void {
    ['id' => $id] = insertUserPUP(['is_active' => false, 'email' => 'inactive@example.test']);

    $provider = makeDefaultProviderPUP();

    expect($provider->retrieveByCredentials(['email' => 'inactive@example.test', 'password' => 'correct-password']))->toBeNull()
        ->and($provider->retrieveById($id))->toBeNull();
});

// (в) deleted_at заполнен → null в обоих — same reasoning as (б), soft-delete side.
it('returns null from both retrieveByCredentials() and retrieveById() for a soft-deleted user', function (): void {
    ['id' => $id] = insertUserPUP(['deleted_at' => now(), 'email' => 'gone@example.test']);

    $provider = makeDefaultProviderPUP();

    expect($provider->retrieveByCredentials(['email' => 'gone@example.test', 'password' => 'correct-password']))->toBeNull()
        ->and($provider->retrieveById($id))->toBeNull();
});

// (г) неверный пароль → validateCredentials() = false.
it('returns false from validateCredentials() for a wrong password', function (): void {
    ['id' => $id] = insertUserPUP();

    $provider = makeDefaultProviderPUP();
    $user = $provider->retrieveById($id);

    expect($provider->validateCredentials($user, ['password' => 'wrong-password']))->toBeFalse();
});

// (д) верный пароль → Auth::attempt() кладёт PrincipalIdentity(User, users.id).
it('logs a PrincipalIdentity(User) in via Auth::attempt() on the right password', function (): void {
    config(['auth.providers.users.driver' => 'corex-principal']);
    ['id' => $id] = insertUserPUP(['email' => 'valid@example.test']);

    $attempted = Auth::attempt(['email' => 'valid@example.test', 'password' => 'correct-password']);

    expect($attempted)->toBeTrue();

    $user = Auth::user();
    expect($user)->toBeInstanceOf(PrincipalIdentity::class)
        ->and($user->principalType())->toBe(PrincipalType::User)
        ->and($user->principalId())->toBe($id);
});

// (е) parity, половина 1 (premortem C4) — Auth::attempt() и Auth::loginUsingId()
// must produce objects of the SAME class, equal by principalType()/
// principalId()/getAuthIdentifier() — this is the seam the OIDC lane
// (axiomasoft/corex-tenancy) relies on to end in Auth::loginUsingId() without ever
// constructing a PrincipalIdentity itself.
it('gives the same class and identity from Auth::attempt() and Auth::loginUsingId() (parity half 1)', function (): void {
    config(['auth.providers.users.driver' => 'corex-principal']);
    ['id' => $id] = insertUserPUP(['email' => 'parity@example.test']);

    Auth::attempt(['email' => 'parity@example.test', 'password' => 'correct-password']);
    $viaAttempt = Auth::user();
    Auth::logout();

    Auth::loginUsingId($id);
    $viaLoginUsingId = Auth::user();

    expect($viaAttempt)->not->toBeNull()
        ->and($viaLoginUsingId)->not->toBeNull()
        ->and($viaAttempt::class)->toBe($viaLoginUsingId::class)
        ->and($viaAttempt->principalType())->toBe($viaLoginUsingId->principalType())
        ->and($viaAttempt->principalId())->toBe($viaLoginUsingId->principalId())
        ->and($viaAttempt->getAuthIdentifier())->toBe($viaLoginUsingId->getAuthIdentifier());
});

// (ж) remember-me недоступен по устройству: retrieveByToken() всегда null,
// updateRememberToken() — no-op, доказано счётчиком запросов, а не
// комментарием (анти-самообман).
it('makes retrieveByToken() a permanent no-op and updateRememberToken() run zero queries', function (): void {
    ['id' => $id] = insertUserPUP();

    $provider = makeDefaultProviderPUP();
    $user = $provider->retrieveById($id);

    expect($provider->retrieveByToken($id, 'some-token'))->toBeNull();

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $provider->updateRememberToken($user, 'new-token');

    expect(DB::connection()->getQueryLog())->toBeEmpty();
});

// (з) таблица с ДРУГИМИ именами (app_users: uid/mail/pwd/enabled) работает
// через конфиг, без правки кода — axiomasoft/corex-access never imports a users model.
it('works against a table with entirely different column names, driven purely by config', function (): void {
    Schema::create('app_users', function (Blueprint $table): void {
        $table->uuid('uid')->primary();
        $table->string('mail');
        $table->string('pwd')->nullable();
        $table->boolean('enabled')->default(true);
        $table->timestamp('deleted_at')->nullable();
    });

    $id = (string) Str::uuid();
    DB::table('app_users')->insert([
        'uid' => $id,
        'mail' => 'custom@example.test',
        'pwd' => Hash::make('correct-password'),
        'enabled' => true,
        'deleted_at' => null,
    ]);

    config([
        'auth.providers.users.driver' => 'corex-principal',
        'corex-auth.identity.table' => 'app_users',
        'corex-auth.identity.columns' => [
            'id' => 'uid',
            'email' => 'mail',
            'password' => 'pwd',
            'active' => 'enabled',
            'deleted_at' => 'deleted_at',
        ],
    ]);
    app()->instance(
        AuthOptions::class,
        AuthOptions::fromArray((array) config('corex-auth')),
    );

    $attempted = Auth::attempt(['email' => 'custom@example.test', 'password' => 'correct-password']);

    expect($attempted)->toBeTrue()
        ->and(Auth::user()->principalId())->toBe($id);
});

// (и) rehashPasswordIfRequired(): обновляет колонку пароля при
// Hash::needsRehash()===true и НЕ делает НИ ОДНОГО запроса при false — оба
// плеча доказаны напрямую (обновлённый хэш / счётчик запросов), не
// утверждением в комментарии.
it('rehashes the password column only when Hash::needsRehash() is true, and issues zero queries otherwise', function (): void {
    // A hash made at a lower bcrypt cost than the app's configured default —
    // Hash::make() (not raw password_hash()) because Laravel's Hash manager
    // caches its bcrypt driver's rounds for the life of the process, so
    // reconfiguring hashing.bcrypt.rounds after first use would not move it.
    $staleHash = password_hash('correct-password', PASSWORD_BCRYPT, ['cost' => 4]);
    ['id' => $id] = insertUserPUP(['password_hash' => $staleHash]);

    expect(Hash::needsRehash($staleHash))->toBeTrue(); // sanity check on the fixture

    $provider = makeDefaultProviderPUP();
    $staleUser = $provider->retrieveById($id);

    $provider->rehashPasswordIfRequired($staleUser, ['password' => 'correct-password']);

    $updatedHash = DB::table('users')->where('id', $id)->value('password_hash');
    expect($updatedHash)->not->toBe($staleHash)
        ->and(Hash::needsRehash($updatedHash))->toBeFalse();

    $freshUser = $provider->retrieveById($id);
    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $provider->rehashPasswordIfRequired($freshUser, ['password' => 'correct-password']);

    expect(DB::connection()->getQueryLog())->toBeEmpty();
});
