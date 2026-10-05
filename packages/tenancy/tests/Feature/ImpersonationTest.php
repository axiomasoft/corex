<?php

declare(strict_types=1);

use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Exceptions\ImpersonationRestrictedException;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Authentication\Exceptions\OidcAuthenticationException;
use CoreX\Tenancy\Authentication\Internal\Oidc\OidcClient;
use CoreX\Tenancy\Identity\Internal\Models\User;
use CoreX\Tenancy\Impersonation\Events\ImpersonationEnded;
use CoreX\Tenancy\Impersonation\Events\ImpersonationStarted;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Tests\TestCase;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// P2.11 — grant validation (D143) + read-only-session effects, through the
// REAL callback() lane (D145 imp branch). Negative-first per Code Guidance.
// Letters per phases/P2.md P2.11 Validation. Reuses OidcLoginTest.php's
// approach but with its own "IM"-suffixed helpers (Pest loads every test
// file into the same process — a shared function name would fatal-collide).
uses()->group('pg');

// `impersonation.enabled` must read true from the FIRST createApplication()
// of this file, same reasoning as TestCase::$oidcEnabled's own docblock
// (TenancyServiceProvider::boot() reads it directly, before any
// config()->set() in a test could take effect) — set via the SAME env-var
// mechanism `resolveApplicationCore()` already uses for `oidc.enabled`,
// without touching TestCase.php.
beforeAll(function (): void {
    TestCase::$oidcEnabled = true;
    putenv('COREX_TENANCY_OIDC_IMPERSONATION_ENABLED=true');
});
afterAll(function (): void {
    TestCase::$oidcEnabled = false;
    putenv('COREX_TENANCY_OIDC_IMPERSONATION_ENABLED=false');
});

const IM_ISSUER = 'https://idp.corex.test';

it('isolates impersonation state across requests in one worker container', function (bool $firstImpersonated): void {
    foreach ([$firstImpersonated, ! $firstImpersonated] as $impersonated) {
        app()->forgetScopedInstances();
        session()->flush();

        if ($impersonated) {
            session([
                'corex.impersonation.grant_id' => 'grant-test',
                'corex.impersonation.staff_identity_id' => 'staff-test',
                'corex.impersonation.target_user_id' => 'target-test',
                'corex.impersonation.expires_at' => now()->addHour()->toIso8601String(),
                'corex.impersonation.reason' => 'support ticket #1',
            ]);
        }
        expect(app(ImpersonationService::class)->active())->toBe($impersonated ? 'grant-test' : null);
        $saving = fn () => event('eloquent.saving: '.User::class, [new User]);

        if ($impersonated) {
            expect($saving)->toThrow(ImpersonationRestrictedException::class);
        } else {
            expect($saving)->not->toThrow(ImpersonationRestrictedException::class);
        }
    }
})->with([false, true]);
const IM_CLIENT_ID = 'oidc-test-client';
const IM_DISCOVERY_URL = IM_ISSUER.'/.well-known/openid-configuration';
const IM_AUTHORIZATION_ENDPOINT = IM_ISSUER.'/authorize';
const IM_TOKEN_ENDPOINT = IM_ISSUER.'/token';
const IM_JWKS_URI = IM_ISSUER.'/jwks';

const ROOT_MIGRATIONS_PATH_IM = __DIR__.'/../../database/migrations/root';
const TENANT_MIGRATIONS_PATH_IM = __DIR__.'/../../database/migrations/tenant';
const CORE_MIGRATIONS_PATH_IM = __DIR__.'/../../vendor/axiomasoft/corex/database/migrations/pg';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    test()->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_IM),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    config(['queue.default' => 'sync']);

    app('router')->prependMiddlewareToGroup('tenant', StartSession::class);
});

afterEach(function (): void {
    endTenantContext();
    DB::purge('pgsql');
    DB::purge('tenant');

    foreach ($GLOBALS['__im_test_dbs'] ?? [] as $db) {
        DB::statement('DROP DATABASE IF EXISTS '.$db);
    }
    $GLOBALS['__im_test_dbs'] = [];
    Carbon::setTestNow();
});

function trackDbIM(string $db): void
{
    $GLOBALS['__im_test_dbs'][] = $db;
}

/**
 * Overrides `auth.providers.users` ('oidc-test-stub') for ONE test: unlike
 * StubIdentityProvider (which always resolves, by design — P2.22 parity
 * proof, premortem C4), this always returns null — models a target-user id
 * that Auth::loginUsingId() cannot resolve, so the caller sees `false`
 * without throwing (case к).
 */
final class MissingUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return null;
    }

    public function retrieveByToken($identifier, #[SensitiveParameter] mixed $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[SensitiveParameter] mixed $token): void {}

    /** @param  array<string, mixed>  $credentials */
    public function retrieveByCredentials(#[SensitiveParameter] array $credentials): ?Authenticatable
    {
        return null;
    }

    /** @param  array<string, mixed>  $credentials */
    public function validateCredentials(Authenticatable $user, #[SensitiveParameter] array $credentials): bool
    {
        return false;
    }

    /** @param  array<string, mixed>  $credentials */
    public function rehashPasswordIfRequired(Authenticatable $user, #[SensitiveParameter] array $credentials, bool $force = false): void {}
}

function makeClusterIM(): string
{
    $id = (string) Str::uuid();

    DB::connection('pgsql')->table('root_clusters')->insert([
        'id' => $id,
        'name' => 'cell-'.$id,
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer-a.local',
        'app_db_user' => 'corex',
    ]);

    return $id;
}

/** @param  array<string, mixed>  $overrides */
function makeIdentityIM(array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::connection('pgsql')->table('root_identities')->insert(array_merge([
        'id' => $id,
        'email' => $id.'@example.test',
    ], $overrides));

    return $id;
}

/** A staff (is_staff=true) identity — the presenter side of a grant. */
function makeStaffIdentityIM(array $overrides = []): string
{
    return makeIdentityIM(array_merge(['is_staff' => true], $overrides));
}

/** @param  array<string, mixed>  $overrides */
function makeGrantIM(string $staffIdentityId, string $accountId, array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::connection('pgsql')->table('root_impersonation_grants')->insert(array_merge([
        'id' => $id,
        'staff_identity_id' => $staffIdentityId,
        'account_id' => $accountId,
        'target_user_id' => null,
        'reason' => 'support ticket #1',
        'approved_by' => null,
        'expires_at' => now()->addHour(),
        'used_at' => null,
        'revoked_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

function grantRowIM(string $grantId): ?object
{
    return DB::connection('pgsql')->table('root_impersonation_grants')->where('id', $grantId)->first();
}

/** A root_accounts row with no tenant DB provisioned — FK-satisfying "some OTHER account" for cross-account cases. */
function makeBareAccountIM(): string
{
    $account = Account::on('pgsql')->create([
        'cluster_id' => makeClusterIM(),
        'db_name' => 'acc_im_bare_'.Str::lower(Str::random(8)),
        'slug' => 'acc-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'owner_identity_id' => makeIdentityIM(),
        'status' => 'active',
    ]);

    return $account->id;
}

/** Creates an Account with a real (empty) tenant DB, initializes tenancy, migrates `users`. */
function initAccountWithTenantIM(): Account
{
    $dbName = 'acc_im_'.Str::lower(Str::random(8));
    trackDbIM($dbName);
    DB::statement('DROP DATABASE IF EXISTS '.$dbName);
    DB::statement('CREATE DATABASE '.$dbName);

    $account = Account::on('pgsql')->create([
        'cluster_id' => makeClusterIM(),
        'db_name' => $dbName,
        'slug' => 'acc-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'owner_identity_id' => makeIdentityIM(),
        'status' => 'active',
    ]);

    initTenantContext($account);

    $ref = new AccountRef(id: $account->id, slug: (string) $account->slug, status: $account->status, features: [], limits: []);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor($ref, null, function (): void {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => realpath(CORE_MIGRATIONS_PATH_IM),
            '--realpath' => true,
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => realpath(TENANT_MIGRATIONS_PATH_IM),
            '--realpath' => true,
            '--force' => true,
        ]);
    });

    return $account;
}

/** Seeds a tenant `users` row directly — impersonation never calls IdentitySyncer (D145), so a target/owner must pre-exist as it would from an earlier ordinary login. */
function seedTenantUserIM(Account $account, string $id, array $overrides = []): void
{
    $ref = new AccountRef(id: $account->id, slug: (string) $account->slug, status: $account->status, features: [], limits: []);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor($ref, null, function () use ($id, $overrides): void {
        DB::table('users')->insert(array_merge([
            'id' => $id,
            'email' => $id.'@example.test',
            'is_active' => true,
        ], $overrides));
    });
}

function makeRsaKeyIM(): array
{
    $opensslConfig = collect(['/etc/ssl/openssl.cnf', '/usr/lib/ssl/openssl.cnf'])
        ->first(static fn (string $path): bool => is_file($path));

    $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

    if ($opensslConfig !== null) {
        $options['config'] = $opensslConfig;
    }

    $resource = openssl_pkey_new($options);

    if ($resource === false) {
        throw new RuntimeException('openssl_pkey_new() failed — no usable openssl.cnf found on this host.');
    }

    if (! openssl_pkey_export($resource, $privatePem, options: $opensslConfig !== null ? ['config' => $opensslConfig] : null)) {
        throw new RuntimeException('openssl_pkey_export() failed.');
    }

    $details = openssl_pkey_get_details($resource);
    $kid = 'kid-'.Str::random(10);

    return [
        'kid' => $kid,
        'private' => $privatePem,
        'jwk' => [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ],
    ];
}

/** @param  array<string, mixed>  $jwk */
function jwksDocIM(array $jwk): array
{
    return ['keys' => [$jwk]];
}

/** @param  array<string, mixed>  $claims */
function signIdTokenIM(array $claims, string $privatePem, string $kid): string
{
    return JWT::encode($claims, $privatePem, 'RS256', $kid);
}

/** @param  array<string, mixed>  $overrides */
function baseClaimsIM(string $sub, string $acct, string $nonce, array $overrides = []): array
{
    return array_merge([
        'iss' => IM_ISSUER,
        'aud' => IM_CLIENT_ID,
        'sub' => $sub,
        'acct' => $acct,
        'nonce' => $nonce,
        'iat' => time(),
        'exp' => time() + 300,
    ], $overrides);
}

/** @param  array<string, mixed>  $jwksDoc @param  array<string, mixed>  $idTokenResponse */
function wireOidcHttpIM(array &$jwksDoc, array &$idTokenResponse): void
{
    Http::fake([
        IM_DISCOVERY_URL => Http::response([
            'authorization_endpoint' => IM_AUTHORIZATION_ENDPOINT,
            'token_endpoint' => IM_TOKEN_ENDPOINT,
            'jwks_uri' => IM_JWKS_URI,
        ]),
        IM_JWKS_URI => function () use (&$jwksDoc) {
            return Http::response($jwksDoc);
        },
        IM_TOKEN_ENDPOINT => function () use (&$idTokenResponse) {
            return Http::response($idTokenResponse);
        },
    ]);
}

function callbackIM(string $state, string $nonce, ?string $verifier = 'code-verifier-value', ?string $queryState = null): TestResponse
{
    if ($verifier !== null) {
        test()->withSession([
            OidcClient::SESSION_STATE_KEY => $state,
            OidcClient::SESSION_NONCE_KEY => $nonce,
            OidcClient::SESSION_CODE_VERIFIER_KEY => $verifier,
        ]);
    }

    $query = http_build_query(['state' => $queryState ?? $state, 'code' => 'auth-code-value']);

    return test()->get('/auth/callback?'.$query);
}

/**
 * Full harness for one impersonation attempt: fresh account+tenant,
 * grant (overridable), staff identity, and a validly-signed `imp` token.
 * Returns [$account, $grantId, $staffId, $response].
 *
 * @param  array<string, mixed>  $grantOverrides
 */
function attemptImpersonationIM(array $grantOverrides = [], ?string $staffId = null): array
{
    $account = initAccountWithTenantIM();
    $staffId ??= makeStaffIdentityIM();
    $grantId = makeGrantIM($staffId, $account->id, $grantOverrides);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($staffId, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    $response = callbackIM($state, $nonce);

    return [$account, $grantId, $staffId, $response];
}

// ── б–з, и — grant validation, negative-first ───────────────────────────

it('б: грант с used_at уже выставленным → 403', function (): void {
    [, , , $response] = attemptImpersonationIM(['used_at' => now()->subMinute()]);

    $response->assertStatus(403);
    expect(auth('web')->check())->toBeFalse();
});

it('в: expires_at в прошлом → 403', function (): void {
    [, , , $response] = attemptImpersonationIM(['expires_at' => now()->subMinute()]);

    $response->assertStatus(403);
});

it('г: revoked_at выставлен → 403', function (): void {
    [, , , $response] = attemptImpersonationIM(['revoked_at' => now()->subMinute()]);

    $response->assertStatus(403);
});

it('д: reason пустой → 403', function (): void {
    [, , , $response] = attemptImpersonationIM(['reason' => '']);

    $response->assertStatus(403);
});

it('е: staff_identity_id гранта ≠ sub токена → 403', function (): void {
    $account = initAccountWithTenantIM();
    $grantStaff = makeStaffIdentityIM();
    $tokenSub = makeStaffIdentityIM(); // a DIFFERENT staff identity presents the token
    $grantId = makeGrantIM($grantStaff, $account->id);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($tokenSub, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    callbackIM($state, $nonce)->assertStatus(403);
});

it('ж: account_id гранта ≠ текущий тенант → 403', function (): void {
    $account = initAccountWithTenantIM();
    $otherAccountId = makeBareAccountIM(); // a real, but DIFFERENT, root_accounts row (FK)
    $staffId = makeStaffIdentityIM();
    $grantId = makeGrantIM($staffId, $otherAccountId); // grant issued for a DIFFERENT account

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($staffId, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    callbackIM($state, $nonce)->assertStatus(403);
});

it('з: is_staff=false у предъявителя → 403', function (): void {
    $account = initAccountWithTenantIM();
    $nonStaffId = makeIdentityIM(); // is_staff defaults false
    $grantId = makeGrantIM($nonStaffId, $account->id);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($nonStaffId, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    callbackIM($state, $nonce)->assertStatus(403);
});

it('и: regulated profile requires a separate staff approver', function (): void {
    config(['corex-tenancy.impersonation.profile' => 'regulated']);
    app()->forgetInstance(TenancyOptions::class);

    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    seedTenantUserIM($account, $account->owner_identity_id);

    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $idTokenResponse = ['id_token' => ''];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    // approved_by = NULL → denied.
    $deniedGrant = makeGrantIM($staffId, $account->id, ['approved_by' => null, 'expires_at' => now()->addMinutes(20)]);
    $state1 = Str::random(40);
    $nonce1 = Str::random(40);
    $claims1 = baseClaimsIM($staffId, $account->id, $nonce1, ['imp' => $deniedGrant]);
    $idTokenResponse['id_token'] = signIdTokenIM($claims1, $key['private'], $key['kid']);
    callbackIM($state1, $nonce1)->assertStatus(403);

    // approved_by set → passes.
    $approverId = makeStaffIdentityIM();
    $approvedGrant = makeGrantIM($staffId, $account->id, ['approved_by' => $approverId, 'expires_at' => now()->addMinutes(20)]);
    $state2 = Str::random(40);
    $nonce2 = Str::random(40);
    $claims2 = baseClaimsIM($staffId, $account->id, $nonce2, ['imp' => $approvedGrant]);
    $idTokenResponse['id_token'] = signIdTokenIM($claims2, $key['private'], $key['kid']);
    callbackIM($state2, $nonce2)->assertRedirect();
    expect(auth('web')->id())->toBe($account->owner_identity_id);
});

it('й: два start одного гранта → ровно один успех (одноразовость, D143)', function (): void {
    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    $targetId = $account->owner_identity_id;
    seedTenantUserIM($account, $targetId);
    $grantId = makeGrantIM($staffId, $account->id);

    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $idTokenResponse = ['id_token' => ''];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    $attempt = function () use ($grantId, $staffId, $account, $key, &$idTokenResponse): TestResponse {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $claims = baseClaimsIM($staffId, $account->id, $nonce, ['imp' => $grantId]);
        $idTokenResponse['id_token'] = signIdTokenIM($claims, $key['private'], $key['kid']);

        return callbackIM($state, $nonce);
    };

    $first = $attempt();
    $first->assertRedirect();
    expect(auth('web')->id())->toBe($targetId);

    Auth::guard('web')->logout();
    $second = $attempt();
    $second->assertStatus(403);
});

it('к/н: target-пользователя не существует → 403, но used_at выставлен ДО этого отказа', function (): void {
    // StubIdentityProvider always resolves an id (P2.22 parity proof) — a
    // one-off provider models retrieveById() returning null instead, the
    // real precondition for `loginUsingId()` to answer `false`.
    Auth::provider('oidc-test-stub', static fn (): MissingUserProvider => new MissingUserProvider);

    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    $missingTargetId = (string) Str::uuid(); // never seeded into tenant `users`
    $grantId = makeGrantIM($staffId, $account->id, ['target_user_id' => $missingTargetId]);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($staffId, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    callbackIM($state, $nonce)->assertStatus(403);
    expect(auth('web')->check())->toBeFalse();

    $grant = grantRowIM($grantId);
    expect($grant->used_at)->not->toBeNull()
        ->and(auth('web')->check())->toBeFalse()
        ->and(session('corex.impersonation.grant_id'))->toBeNull()
        ->and(DB::connection('pgsql')->table('root_account_events')->where('event', 'impersonation.failed')->count())->toBe(1);
});

// ── л–у — happy path & its observable effects ────────────────────────────

it('л/м/о/п/с/т/у: валидный грант — target≠sub, root_account_events, событие, no-membership, no-sync, ACTOR', function (): void {
    Event::fake([ImpersonationStarted::class]);

    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    $targetId = makeIdentityIM();
    seedTenantUserIM($account, $targetId, ['email' => 'target@example.test']);
    $grantId = makeGrantIM($staffId, $account->id, ['target_user_id' => $targetId]);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($staffId, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    callbackIM($state, $nonce)->assertRedirect();

    // (л) target, not sub, is the authenticated principal.
    expect(auth('web')->id())->toBe($targetId)
        ->and(auth('web')->id())->not->toBe($staffId);

    // (о) root_account_events carries the started mirror, actor form per §5.9.
    $event = DB::connection('pgsql')->table('root_account_events')->where('event', 'impersonation.started')->sole();
    $actor = json_decode((string) $event->actor, true, 512, JSON_THROW_ON_ERROR);
    // toEqual, not toBe: jsonb does not preserve key insertion order on round-trip.
    expect($actor)->toEqual(['type' => 'support', 'id' => $staffId, 'grant' => $grantId]);

    // (п) ImpersonationStarted fired.
    Event::assertDispatched(ImpersonationStarted::class, static fn (ImpersonationStarted $e): bool => $e->grantId === $grantId && $e->staffIdentityId === $staffId && $e->targetUserId === $targetId && $e->reason === 'support ticket #1');

    // (с) staff is NOT a member — no root_identity_accounts row for it.
    expect(DB::connection('pgsql')->table('root_identity_accounts')->where('identity_id', $staffId)->exists())->toBeFalse();

    // (т) IdentitySyncer was never invoked — no tenant `users` row for staff.
    $ref = new AccountRef(id: $account->id, slug: (string) $account->slug, status: $account->status, features: [], limits: []);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $staffRow = $manager->runFor($ref, null, fn (): ?object => DB::table('users')->where('id', $staffId)->first());
    expect($staffRow)->toBeNull();
});

it('м: target_user_id = NULL → сессия открывается от имени owner_identity_id', function (): void {
    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    seedTenantUserIM($account, $account->owner_identity_id);
    $grantId = makeGrantIM($staffId, $account->id, ['target_user_id' => null]);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($staffId, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    callbackIM($state, $nonce)->assertRedirect();

    expect(auth('web')->id())->toBe($account->owner_identity_id);
});

it('р: заголовок X-CoreX-Impersonation присутствует на следующем запросе', function (): void {
    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    seedTenantUserIM($account, $account->owner_identity_id);
    $grantId = makeGrantIM($staffId, $account->id);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($staffId, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    callbackIM($state, $nonce)->assertRedirect();

    $next = test()->get('/auth/login');
    expect($next->headers->get('X-CoreX-Impersonation'))->toBe($grantId);
});

// ── э, ю, я — stop / TTL / revocation ─────────────────────────────────

it('э: stop() гасит сессию, пишет impersonation.ended и излучает ImpersonationEnded', function (): void {
    Event::fake([ImpersonationEnded::class]);

    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    seedTenantUserIM($account, $account->owner_identity_id);
    $grantId = makeGrantIM($staffId, $account->id);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($staffId, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    callbackIM($state, $nonce)->assertRedirect();
    expect(auth('web')->check())->toBeTrue();

    app(ImpersonationService::class)->stop();

    expect(auth('web')->check())->toBeFalse();

    $ended = DB::connection('pgsql')->table('root_account_events')->where('event', 'impersonation.ended')->sole();
    $actor = json_decode((string) $ended->actor, true, 512, JSON_THROW_ON_ERROR);
    expect($actor['grant'])->toBe($grantId);

    Event::assertDispatched(ImpersonationEnded::class, static fn (ImpersonationEnded $e): bool => $e->grantId === $grantId);
});

it('ю: TTL истёк → следующий запрос 401, сессия завершена', function (): void {
    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    seedTenantUserIM($account, $account->owner_identity_id);
    $grantId = makeGrantIM($staffId, $account->id, ['expires_at' => now()->addMinute()]);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($staffId, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    callbackIM($state, $nonce)->assertRedirect();

    Carbon::setTestNow(now()->addMinutes(2));

    test()->get('/auth/login')->assertStatus(401);
    expect(auth('web')->check())->toBeFalse();
});

it('я: revoked_at выставлен извне mid-session → следующий запрос 401 немедленно (не ждёт TTL)', function (): void {
    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    seedTenantUserIM($account, $account->owner_identity_id);
    $grantId = makeGrantIM($staffId, $account->id);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($staffId, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    callbackIM($state, $nonce)->assertRedirect();

    // Control-plane revokes the grant mid-session (external UPDATE — not via this package's own API).
    DB::connection('pgsql')->table('root_impersonation_grants')->where('id', $grantId)->update(['revoked_at' => now()]);

    test()->get('/auth/login')->assertStatus(401);
});

it('а: imp в токене при impersonation.enabled=false → 403 (регресс P2.22)', function (): void {
    config(['corex-tenancy.oidc.impersonation.enabled' => false]);
    app()->forgetInstance(TenancyOptions::class);

    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    $grantId = makeGrantIM($staffId, $account->id);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyIM();
    $jwksDoc = jwksDocIM($key['jwk']);
    $claims = baseClaimsIM($staffId, $account->id, $nonce, ['imp' => $grantId]);
    $idTokenResponse = ['id_token' => signIdTokenIM($claims, $key['private'], $key['kid'])];
    wireOidcHttpIM($jwksDoc, $idTokenResponse);

    callbackIM($state, $nonce)->assertStatus(403);
    expect(auth('web')->check())->toBeFalse();
});

it('rejects a nested start before the second grant is consumed', function (): void {
    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    $targetId = makeIdentityIM();
    seedTenantUserIM($account, $targetId, ['email' => 'nested@example.test']);
    $first = makeGrantIM($staffId, $account->id, ['target_user_id' => $targetId]);
    $second = makeGrantIM($staffId, $account->id, ['target_user_id' => $targetId]);
    $service = app(ImpersonationService::class);

    $service->start($first, $staffId);

    expect(fn () => $service->start($second, $staffId))->toThrow(OidcAuthenticationException::class)
        ->and(grantRowIM($second)->used_at)->toBeNull()
        ->and($service->state()?->grantId)->toBe($first)
        ->and($service->state()?->reason)->toBe('support ticket #1');
});

it('backfills a legacy session from the claimed grant reason', function (): void {
    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    $targetId = makeIdentityIM();
    $grantId = makeGrantIM($staffId, $account->id, ['target_user_id' => $targetId, 'used_at' => now()]);
    app()->forgetInstance(ImpersonationService::class);

    session([
        'corex.impersonation.grant_id' => $grantId,
        'corex.impersonation.staff_identity_id' => $staffId,
        'corex.impersonation.target_user_id' => $targetId,
        'corex.impersonation.expires_at' => now()->addHour()->toIso8601String(),
    ]);

    $state = app(ImpersonationService::class)->state();

    expect($state?->reason)->toBe('support ticket #1')
        ->and(session('corex.impersonation.reason'))->toBe('support ticket #1');
});

it('closes a legacy session whose claimed grant has no reason', function (): void {
    $account = initAccountWithTenantIM();
    $staffId = makeStaffIdentityIM();
    $targetId = makeIdentityIM();
    $grantId = makeGrantIM($staffId, $account->id, ['target_user_id' => $targetId, 'used_at' => now(), 'reason' => '']);
    app()->forgetInstance(ImpersonationService::class);

    session([
        'corex.impersonation.grant_id' => $grantId,
        'corex.impersonation.staff_identity_id' => $staffId,
        'corex.impersonation.target_user_id' => $targetId,
        'corex.impersonation.expires_at' => now()->addHour()->toIso8601String(),
    ]);

    expect(app(ImpersonationService::class)->state())->toBeNull()
        ->and(session('corex.impersonation.grant_id'))->toBeNull();
});
