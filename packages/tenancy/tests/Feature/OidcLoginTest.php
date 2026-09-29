<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Models\Account;
use CoreX\Tenancy\Authentication\Internal\Oidc\OidcClient;
use CoreX\Tenancy\Tests\Stubs\StubIdentityProvider;
use CoreX\Tenancy\Tests\TestCase;
use Firebase\JWT\JWT;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Stancl\Tenancy\Tenancy;

// AC-14 — authorization-code + PKCE + nonce (D129), Implementation Rule 5
// order (research/10 §3). Negative-first per the P2.22 pre-mortem (§7): each
// case below fails if its corresponding check in IdTokenVerifier/
// OidcLoginController is removed — asserted by the specific claim/step each
// test forges, not by the mere presence of a `->assertStatus(403)`.
uses()->group('pg');

// File-scoped, not uses()->beforeAll() (directory-wide, would leak into
// every other Feature test) — see TestCase::$oidcEnabled's docblock for why
// this can't be a plain config()->set() in defineEnvironment(). Reset in
// afterAll() so a random suite-execution order never lets this file's flip
// bleed into a sibling file's tests.
beforeAll(fn () => TestCase::$oidcEnabled = true);
afterAll(fn () => TestCase::$oidcEnabled = false);

const OL_ISSUER = 'https://idp.corex.test';
const OL_CLIENT_ID = 'oidc-test-client';
const OL_DISCOVERY_URL = OL_ISSUER.'/.well-known/openid-configuration';
const OL_AUTHORIZATION_ENDPOINT = OL_ISSUER.'/authorize';
const OL_TOKEN_ENDPOINT = OL_ISSUER.'/token';
const OL_JWKS_URI = OL_ISSUER.'/jwks';

const ROOT_MIGRATIONS_PATH_OL = __DIR__.'/../../database/migrations/root';
const TENANT_MIGRATIONS_PATH_OL = __DIR__.'/../../database/migrations/tenant';
const CORE_MIGRATIONS_PATH_OL = __DIR__.'/../../vendor/axiomasoft/corex/database/migrations/pg';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_OL),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    config(['queue.default' => 'sync']);

    // routes/oidc.php wraps only in the 'tenant' group (ResolveTenantFromHost/
    // AccountStatusGate/ScopeSessions/EnforceSessionLifetime under this
    // package's own flags) — no session-starting middleware of its own. A
    // real consuming app is expected to compose these routes under ITS OWN
    // 'web' group (stancl's ScopeSessions middleware, itself already part of
    // 'tenant' when host_identification is enabled, makes the same
    // assumption). Testbench has no such app-level route file, so this test
    // PREPENDS StartSession — and only StartSession, not the rest of 'web'
    // (CSRF included) — into the 'tenant' group itself (not appended per
    // route): EnforceSessionLifetime is already a 'tenant' member and reads
    // the session too, so it must run AFTER, not before, StartSession.
    app('router')->prependMiddlewareToGroup('tenant', StartSession::class);
});

afterEach(function (): void {
    app(Tenancy::class)->end();
    DB::purge('pgsql');
    DB::purge('tenant');

    foreach ($GLOBALS['__ol_test_dbs'] ?? [] as $db) {
        DB::statement('DROP DATABASE IF EXISTS '.$db);
    }
    $GLOBALS['__ol_test_dbs'] = [];
});

function trackDbOL(string $db): void
{
    $GLOBALS['__ol_test_dbs'][] = $db;
}

// Every central-table helper below targets the 'pgsql' connection EXPLICITLY
// (never bare DB::table()) — initAccountWithTenantOL() calls
// Tenancy::initialize(), which flips the app's DEFAULT connection to
// 'tenant' for the rest of the test (DatabaseTenancyBootstrapper); a bare
// DB::table('root_identities') called afterwards would silently insert into
// the TENANT database (which has no such table) instead of the central one.
function makeClusterOL(): string
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
function makeIdentityOL(array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::connection('pgsql')->table('root_identities')->insert(array_merge([
        'id' => $id,
        'email' => $id.'@example.test',
    ], $overrides));

    return $id;
}

/** @param  array<string, mixed>  $overrides */
function makeMembershipOL(string $identityId, string $accountId, array $overrides = []): void
{
    DB::connection('pgsql')->table('root_identity_accounts')->insert(array_merge([
        'id' => (string) Str::uuid(),
        'identity_id' => $identityId,
        'account_id' => $accountId,
        'status' => 'active',
    ], $overrides));
}

/** Creates an Account with a real (empty) tenant DB, initializes tenancy, and migrates the `users` table into it. */
function initAccountWithTenantOL(): Account
{
    $dbName = 'acc_ol_'.Str::lower(Str::random(8));
    trackDbOL($dbName);
    DB::statement('DROP DATABASE IF EXISTS '.$dbName);
    DB::statement('CREATE DATABASE '.$dbName);

    $account = Account::on('pgsql')->create([
        'cluster_id' => makeClusterOL(),
        'db_name' => $dbName,
        'slug' => 'acc-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'owner_identity_id' => makeIdentityOL(),
        'status' => 'active',
    ]);

    app(Tenancy::class)->initialize($account);

    $ref = new AccountRef(id: $account->id, slug: (string) $account->slug, status: $account->status, features: [], limits: []);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor($ref, null, function (): void {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => realpath(CORE_MIGRATIONS_PATH_OL),
            '--realpath' => true,
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => realpath(TENANT_MIGRATIONS_PATH_OL),
            '--realpath' => true,
            '--force' => true,
        ]);
    });

    return $account;
}

/**
 * @return array{kid: string, private: string, public_pem: string, jwk: array<string, string>}
 */
function makeRsaKeyOL(?string $kid = null): array
{
    // PHP's compiled-in default OpenSSL config path is not guaranteed to
    // exist on every host running this suite (openssl_pkey_new() fails
    // silently without a resolvable config) — pointing at a real
    // system-provided openssl.cnf makes key generation environment-agnostic.
    $opensslConfig = collect(['/etc/ssl/openssl.cnf', '/usr/lib/ssl/openssl.cnf'])
        ->first(static fn (string $path): bool => is_file($path));

    $options = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    if ($opensslConfig !== null) {
        $options['config'] = $opensslConfig;
    }

    $resource = openssl_pkey_new($options);

    if ($resource === false) {
        throw new RuntimeException('openssl_pkey_new() failed — no usable openssl.cnf found on this host.');
    }

    // openssl_pkey_export() ALSO needs the same 'config' option (it PEM-
    // serializes via the same OpenSSL config machinery as openssl_pkey_new())
    // — omitting it here reproduces the exact same "no usable config" class
    // of failure one level later, silently leaving $privatePem unset.
    if (! openssl_pkey_export($resource, $privatePem, options: $opensslConfig !== null ? ['config' => $opensslConfig] : null)) {
        throw new RuntimeException('openssl_pkey_export() failed.');
    }

    $details = openssl_pkey_get_details($resource);

    $kid ??= 'kid-'.Str::random(10);

    return [
        'kid' => $kid,
        'private' => $privatePem,
        'public_pem' => $details['key'],
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
function jwksDocOL(array $jwk): array
{
    return ['keys' => [$jwk]];
}

function base64UrlOL(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/** @param  array<string, mixed>  $claims */
function algNoneTokenOL(array $claims): string
{
    $header = base64UrlOL(json_encode(['alg' => 'none', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
    $payload = base64UrlOL(json_encode($claims, JSON_THROW_ON_ERROR));

    return $header.'.'.$payload.'.';
}

/** @param  array<string, mixed>  $claims */
function signIdTokenOL(array $claims, string $privatePem, string $kid, string $alg = 'RS256'): string
{
    return JWT::encode($claims, $privatePem, $alg, $kid);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function baseClaimsOL(string $sub, string $acct, string $nonce, array $overrides = []): array
{
    return array_merge([
        'iss' => OL_ISSUER,
        'aud' => OL_CLIENT_ID,
        'sub' => $sub,
        'acct' => $acct,
        'nonce' => $nonce,
        'iat' => time(),
        'exp' => time() + 300,
    ], $overrides);
}

/**
 * Wires discovery/jwks/token Http::fake()s. `$jwksDoc`/`$idTokenResponse`
 * are captured BY REFERENCE so a test can mutate what's served WITHOUT a
 * second Http::fake() call — Http::fake() resets Http::recorded()
 * (Illuminate\Http\Client\Factory::fake()), which would break the
 * request-counter assertions (test п, D130 anti-self-deception).
 *
 * @param  array<string, mixed>  $jwksDoc
 * @param  array<string, mixed>  $idTokenResponse
 */
function wireOidcHttpOL(array &$jwksDoc, array &$idTokenResponse): void
{
    Http::fake([
        OL_DISCOVERY_URL => Http::response([
            'authorization_endpoint' => OL_AUTHORIZATION_ENDPOINT,
            'token_endpoint' => OL_TOKEN_ENDPOINT,
            'jwks_uri' => OL_JWKS_URI,
        ]),
        OL_JWKS_URI => function () use (&$jwksDoc) {
            return Http::response($jwksDoc);
        },
        OL_TOKEN_ENDPOINT => function () use (&$idTokenResponse) {
            return Http::response($idTokenResponse);
        },
    ]);
}

/**
 * Dispatches `GET /auth/callback`. `$verifier === null` skips priming the
 * session entirely (test к: "another session", nothing to consume) —
 * otherwise state/nonce/code_verifier are seeded directly via
 * `withSession()` (no real round trip through `/auth/login` is needed to
 * exercise the callback's own checks). `$queryState` defaults to `$state`;
 * passing a different value is how test и's "state mismatch" half is built.
 */
function callbackOL(string $state, string $nonce, ?string $verifier = 'code-verifier-value', ?string $queryState = null): TestResponse
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

// (а) alg: none → 403, not logged in.
it('а: id_token с alg=none → 403, вход не выполнен', function (): void {
    $state = Str::random(40);
    $nonce = Str::random(40);

    $jwksDoc = jwksDocOL(makeRsaKeyOL()['jwk']);
    $idTokenResponse = ['id_token' => algNoneTokenOL(baseClaimsOL((string) Str::uuid(), (string) Str::uuid(), $nonce))];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
    expect(auth('web')->check())->toBeFalse();
});

// (б) id_token signed by a DIFFERENT private key than the one published under the same kid.
it('б: id_token подписан чужим ключом → 403', function (): void {
    $state = Str::random(40);
    $nonce = Str::random(40);

    $realKey = makeRsaKeyOL();
    $foreignKey = makeRsaKeyOL($realKey['kid']);
    $jwksDoc = jwksDocOL($realKey['jwk']);

    $idTokenResponse = ['id_token' => signIdTokenOL(baseClaimsOL((string) Str::uuid(), (string) Str::uuid(), $nonce), $foreignKey['private'], $realKey['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
});

// (в) HS256 with the RSA public key (PEM bytes) used AS the HMAC secret — the classic alg-confusion forgery.
it('в: HS256 с секретом = публичный ключ из JWKS → 403', function (): void {
    $state = Str::random(40);
    $nonce = Str::random(40);

    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);

    $idTokenResponse = ['id_token' => JWT::encode(baseClaimsOL((string) Str::uuid(), (string) Str::uuid(), $nonce), $key['public_pem'], 'HS256', $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
});

// (г) kid absent from the header entirely.
it('г: id_token без kid → 403', function (): void {
    $state = Str::random(40);
    $nonce = Str::random(40);

    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);

    $idTokenResponse = ['id_token' => JWT::encode(baseClaimsOL((string) Str::uuid(), (string) Str::uuid(), $nonce), $key['private'], 'RS256')];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
});

// (г) kid present but unknown to the JWKS — even after JwksKeySet's own one forced refetch, D130.
it('г: неизвестный kid → один принудительный рефетч JWKS, но всё равно 403', function (): void {
    $state = Str::random(40);
    $nonce = Str::random(40);

    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']); // never actually carries the unknown kid, even after the forced refetch

    $idTokenResponse = ['id_token' => signIdTokenOL(baseClaimsOL((string) Str::uuid(), (string) Str::uuid(), $nonce), $key['private'], 'totally-unknown-kid')];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);

    $jwksRequests = Http::recorded(fn ($request): bool => $request->url() === OL_JWKS_URI)->count();
    expect($jwksRequests)->toBe(2); // 1 normal cache-miss fetch + 1 forced refetch on the unknown kid
});

// (д) iss does not match the configured issuer.
it('д: чужой iss → 403', function (): void {
    $state = Str::random(40);
    $nonce = Str::random(40);

    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);

    $claims = baseClaimsOL((string) Str::uuid(), (string) Str::uuid(), $nonce, ['iss' => 'https://evil-as.example.test']);
    $idTokenResponse = ['id_token' => signIdTokenOL($claims, $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
});

// (е) aud does not carry the configured client_id.
it('е: aud ≠ client_id → 403', function (): void {
    $state = Str::random(40);
    $nonce = Str::random(40);

    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);

    $claims = baseClaimsOL((string) Str::uuid(), (string) Str::uuid(), $nonce, ['aud' => 'someone-elses-client']);
    $idTokenResponse = ['id_token' => signIdTokenOL($claims, $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
});

// (ж) exp in the past, well beyond the configured leeway (30s default).
it('ж: exp в прошлом сверх leeway → 403', function (): void {
    $state = Str::random(40);
    $nonce = Str::random(40);

    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);

    $claims = baseClaimsOL((string) Str::uuid(), (string) Str::uuid(), $nonce, ['iat' => time() - 7200, 'exp' => time() - 3600]);
    $idTokenResponse = ['id_token' => signIdTokenOL($claims, $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
});

// (з) nonce claim does not match the session's one-time nonce.
it('з: nonce не совпал → 403', function (): void {
    $state = Str::random(40);
    $nonce = Str::random(40);

    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);

    $claims = baseClaimsOL((string) Str::uuid(), (string) Str::uuid(), 'a-completely-different-nonce');
    $idTokenResponse = ['id_token' => signIdTokenOL($claims, $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
});

// (и) query `state` does not match the session's one-time `state`.
it('и: state в query ≠ state в сессии → 403', function (): void {
    $state = Str::random(40);
    $nonce = Str::random(40);

    $jwksDoc = jwksDocOL(makeRsaKeyOL()['jwk']);
    $idTokenResponse = ['id_token' => algNoneTokenOL(baseClaimsOL((string) Str::uuid(), (string) Str::uuid(), $nonce))];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce, queryState: 'a-different-query-state-value')->assertStatus(403);
});

// (и) a repeated callback with the SAME state — the session key is pull()ed
// (read-and-removed) on the FIRST attempt regardless of that attempt's
// eventual outcome, so nothing is left to consume on the replay. The FIRST
// attempt here carries a genuinely VALID, correctly signed id_token for an
// active membership — it MUST succeed — so the replay's 403 is provably
// caused by the one-time state/nonce/code_verifier being gone from the
// session, not by a forged/rejected token (the previous version of this
// test used `algNoneTokenOL()` on BOTH calls, so it stayed green whether or
// not `consumeState()`'s one-time consumption worked at all — self-check:
// swapping its `pull()`s for `get()` must turn this test red).
it('и: повторный callback с тем же state → 403 (одноразовый), первый вход — успешен', function (): void {
    $account = initAccountWithTenantOL();
    $sub = makeIdentityOL();
    makeMembershipOL($sub, $account->id, ['status' => 'active']);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);
    $idTokenResponse = ['id_token' => signIdTokenOL(baseClaimsOL($sub, $account->id, $nonce), $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    // First attempt — valid token, active membership: must actually log in
    // (proves the session keys really were there to consume, not merely
    // absent for an unrelated reason).
    callbackOL($state, $nonce)->assertRedirect();
    expect(auth('web')->check())->toBeTrue()
        ->and(auth('web')->id())->toBe($sub);

    // Replay — same query `state`, but the session's state/nonce/
    // code_verifier were already pull()ed (consumed) by the first attempt.
    // `verifier: null` skips re-priming the session entirely, so nothing
    // stands in for them here.
    callbackOL($state, $nonce, verifier: null)->assertStatus(403);
});

// (к) callback dispatched against a session that never went through /auth/login (no code_verifier at all).
it('к: callback в другой сессии (нет code_verifier) → 403', function (): void {
    $state = Str::random(40);
    $nonce = Str::random(40);

    callbackOL($state, $nonce, verifier: null)->assertStatus(403);
});

// (л) acct claim does not match the current tenant.
it('л: acct ≠ tenancy()->tenant->id → 403', function (): void {
    $account = initAccountWithTenantOL();
    $sub = makeIdentityOL();
    makeMembershipOL($sub, $account->id, ['status' => 'active']);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);

    $claims = baseClaimsOL($sub, (string) Str::uuid(), $nonce); // a foreign acct, not $account->id
    $idTokenResponse = ['id_token' => signIdTokenOL($claims, $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
    expect(auth('web')->check())->toBeFalse();
});

// (м) membership status must be RIGID `active` — `invited`/`blocked`/absent all deny.
it('м: членство invited → 403', function (): void {
    $account = initAccountWithTenantOL();
    $sub = makeIdentityOL();
    makeMembershipOL($sub, $account->id, ['status' => 'invited']);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);
    $idTokenResponse = ['id_token' => signIdTokenOL(baseClaimsOL($sub, $account->id, $nonce), $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
});

it('м: членство blocked → 403', function (): void {
    $account = initAccountWithTenantOL();
    $sub = makeIdentityOL();
    makeMembershipOL($sub, $account->id, ['status' => 'blocked']);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);
    $idTokenResponse = ['id_token' => signIdTokenOL(baseClaimsOL($sub, $account->id, $nonce), $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
});

it('м: членства нет вовсе → 403', function (): void {
    $account = initAccountWithTenantOL();
    $sub = makeIdentityOL(); // no makeMembershipOL() call at all

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);
    $idTokenResponse = ['id_token' => signIdTokenOL(baseClaimsOL($sub, $account->id, $nonce), $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
});

// (н) `imp` claim present while `tenancy.oidc.impersonation.enabled` stays at its false default (P2.11 fail-closed gate).
it('н: imp в токене при impersonation.enabled=false → 403', function (): void {
    expect(config('tenancy.oidc.impersonation.enabled'))->toBeFalse();

    $account = initAccountWithTenantOL();
    $sub = makeIdentityOL();
    makeMembershipOL($sub, $account->id, ['status' => 'active']);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);
    $claims = baseClaimsOL($sub, $account->id, $nonce, ['imp' => (string) Str::uuid()]);
    $idTokenResponse = ['id_token' => signIdTokenOL($claims, $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertStatus(403);
});

// (о) happy path — IdentitySyncer projects a `users` row, Auth::id() === sub, session regenerated.
it('о: happy-path — users создан IdentitySyncer, Auth::id()===sub, сессия перевыпущена', function (): void {
    $account = initAccountWithTenantOL();
    $sub = makeIdentityOL(['email' => 'happy@example.test', 'name' => 'Ada']);
    makeMembershipOL($sub, $account->id, ['status' => 'active']);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);
    $idTokenResponse = ['id_token' => signIdTokenOL(baseClaimsOL($sub, $account->id, $nonce), $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    $this->withSession([
        OidcClient::SESSION_STATE_KEY => $state,
        OidcClient::SESSION_NONCE_KEY => $nonce,
        OidcClient::SESSION_CODE_VERIFIER_KEY => 'verifier-value',
    ]);
    $sessionIdBefore = $this->app['session']->getId();

    $response = $this->get('/auth/callback?'.http_build_query(['state' => $state, 'code' => 'auth-code-value']));

    $response->assertRedirect();
    expect(auth('web')->id())->toBe($sub)
        ->and($this->app['session']->getId())->not->toBe($sessionIdBefore);

    $ref = new AccountRef(id: $account->id, slug: (string) $account->slug, status: $account->status, features: [], limits: []);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $row = $manager->runFor($ref, null, fn (): ?object => DB::table('users')->where('id', $sub)->first());

    expect($row)->not->toBeNull()
        ->and($row->email)->toBe('happy@example.test')
        ->and($row->is_active)->toBeTrue();
});

// (п) JWKS caching (D130): second login makes 0 requests to jwks_uri; after
// an AS key rotation (new kid, same JWKS doc mutated in place, NO
// Cache::flush()), the next login succeeds with exactly ONE additional
// jwks_uri request — proven via Http::recorded() counters, not Cache::has().
it('п: второй вход не ходит в jwks_uri; после ротации ключа — +1 запрос, без ожидания TTL', function (): void {
    $account = initAccountWithTenantOL();

    $key1 = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key1['jwk']);
    $idTokenResponse = ['id_token' => ''];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    $login = function (string $sub, string $privateKey, string $kid) use ($account, &$idTokenResponse): void {
        makeMembershipOL($sub, $account->id, ['status' => 'active']);
        $state = Str::random(40);
        $nonce = Str::random(40);
        $idTokenResponse['id_token'] = signIdTokenOL(baseClaimsOL($sub, $account->id, $nonce), $privateKey, $kid);

        callbackOL($state, $nonce)->assertRedirect();
        expect(auth('web')->id())->toBe($sub); // every login must actually succeed, not just avoid a 500
        Auth::guard('web')->logout(); // isolate each login's assertions
    };

    $login(makeIdentityOL(), $key1['private'], $key1['kid']);
    $afterFirst = Http::recorded(fn ($request): bool => $request->url() === OL_JWKS_URI)->count();
    expect($afterFirst)->toBe(1);

    $login(makeIdentityOL(), $key1['private'], $key1['kid']); // same kid — cache hit
    $afterSecond = Http::recorded(fn ($request): bool => $request->url() === OL_JWKS_URI)->count();
    expect($afterSecond)->toBe($afterFirst);

    // Key rotation — mutate the served document in place (never Http::fake()
    // again, never Cache::flush()).
    $key2 = makeRsaKeyOL();
    $jwksDoc['keys'] = [$key2['jwk']];

    $login(makeIdentityOL(), $key2['private'], $key2['kid']);

    $afterThird = Http::recorded(fn ($request): bool => $request->url() === OL_JWKS_URI)->count();
    expect($afterThird)->toBe($afterSecond + 1);
});

// (с) parity, half 2 (premortem C4) — login went through the CONFIGURED
// auth provider (StubIdentityProvider standing in for it), never a Principal
// the controller constructed itself.
it('с: parity половина 2 — вход выполнен через сконфигурированный auth.provider', function (): void {
    $account = initAccountWithTenantOL();
    $sub = makeIdentityOL();
    makeMembershipOL($sub, $account->id, ['status' => 'active']);

    $state = Str::random(40);
    $nonce = Str::random(40);
    $key = makeRsaKeyOL();
    $jwksDoc = jwksDocOL($key['jwk']);
    $idTokenResponse = ['id_token' => signIdTokenOL(baseClaimsOL($sub, $account->id, $nonce), $key['private'], $key['kid'])];
    wireOidcHttpOL($jwksDoc, $idTokenResponse);

    callbackOL($state, $nonce)->assertRedirect();

    $stub = app(StubIdentityProvider::class);
    expect($stub->retrievedByIdCalls)->toContain($sub)
        ->and(auth('web')->id())->toBe($sub);
});
