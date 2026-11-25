<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use CoreX\Tenancy\Tests\Stubs\StubIdentity;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// AC-15 — global logout revokes root_idp_sessions/root_oauth_refresh_tokens
// (a real revoke, never a DELETE) and the local tenant session dies with it;
// EnforceSessionLifetime independently caps a session's lifetime at
// corex-tenancy.oidc.max_session_lifetime_minutes (720 = 12h) regardless of what
// the consuming app's own session.lifetime says.
uses()->group('pg');

// File-scoped, not uses()->beforeAll() — see TestCase::$oidcEnabled's
// docblock (OidcLoginTest.php, this file's sibling) for why this can't be a
// plain config()->set() in defineEnvironment(), and why this must be reset
// in afterAll() rather than left flipped for the rest of the suite.
beforeAll(fn () => TestCase::$oidcEnabled = true);
afterAll(fn () => TestCase::$oidcEnabled = false);

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');

    // See OidcLoginTest.php's beforeEach for why this is necessary:
    // routes/oidc.php's 'tenant' group carries no session-starting
    // middleware of its own (a real consuming app is expected to compose it
    // under its own 'web' group) — EnforceSessionLifetime is already a
    // 'tenant' member and reads the session too, so StartSession must be
    // PREPENDED, not appended, to run first.
    resolve('router')->prependMiddlewareToGroup('tenant', StartSession::class);
});

afterEach(function (): void {
    endTenantContext();
    DB::purge('pgsql');
});

/** @param  array<string, mixed>  $overrides */
function makeIdentityGL(array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::connection('pgsql')->table('root_identities')->insert(array_merge([
        'id' => $id,
        'email' => $id.'@example.test',
    ], $overrides));

    return $id;
}

/** @param  array<string, mixed>  $overrides */
function makeIdpSessionGL(string $identityId, array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::connection('pgsql')->table('root_idp_sessions')->insert(array_merge([
        'id' => $id,
        'identity_id' => $identityId,
        'token_hash' => hash('sha256', Str::random(32)),
        'expires_at' => now()->addDay(),
        'revoked_at' => null,
    ], $overrides));

    return $id;
}

/** @param  array<string, mixed>  $overrides */
function makeRefreshTokenGL(string $sessionId, array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::connection('pgsql')->table('root_oauth_refresh_tokens')->insert(array_merge([
        'id' => $id,
        'session_id' => $sessionId,
        'client_id' => 'oidc-test-client',
        'token_hash' => hash('sha256', Str::random(32)),
        'expires_at' => now()->addDay(),
        'revoked_at' => null,
    ], $overrides));

    return $id;
}

/** Authenticates as `$identityId` on the 'web' guard and dispatches POST /auth/logout. */
function logoutGL(string $identityId): TestResponse
{
    test()->actingAs(new StubIdentity($identityId), 'web');
    test()->withSession([]); // starts the session so $request->session() is set

    return test()->post('/auth/logout');
}

// (а) logout revokes EVERY unrevoked root_idp_sessions row of the identity, cascading by session_id into root_oauth_refresh_tokens.
it('а: логаут ревокает ВСЕ неревокнутые root_idp_sessions идентичности и каскадом их refresh-токены', function (): void {
    $identityId = makeIdentityGL();
    $session1 = makeIdpSessionGL($identityId);
    $session2 = makeIdpSessionGL($identityId);
    $token1 = makeRefreshTokenGL($session1);
    $token2 = makeRefreshTokenGL($session2);

    logoutGL($identityId)->assertRedirect();

    $sessions = DB::connection('pgsql')->table('root_idp_sessions')->where('identity_id', $identityId)->get();
    expect($sessions)->toHaveCount(2)
        ->and($sessions->pluck('revoked_at')->filter())->toHaveCount(2);

    $tokens = DB::connection('pgsql')->table('root_oauth_refresh_tokens')->whereIn('id', [$token1, $token2])->get();
    expect($tokens->pluck('revoked_at')->filter())->toHaveCount(2);
});

// (б) an already-revoked row is not touched again (its original revoked_at survives).
it('б: уже ревокнутые строки не перезаписываются', function (): void {
    $identityId = makeIdentityGL();
    // startOfSecond(): the `insert()` below round-trips this through a plain
    // datetime column with no microsecond precision on the way in — comparing
    // against the un-truncated in-memory instant would spuriously fail on the
    // fractional seconds Postgres never received.
    $earlierRevokedAt = CarbonImmutable::now()->subWeek()->startOfSecond();
    $alreadyRevokedSession = makeIdpSessionGL($identityId, ['revoked_at' => $earlierRevokedAt]);
    $alreadyRevokedToken = makeRefreshTokenGL($alreadyRevokedSession, ['revoked_at' => $earlierRevokedAt]);

    // A second, still-live session/token for the same identity — proves the
    // logout actually ran, not merely that nothing errored.
    $liveSession = makeIdpSessionGL($identityId);
    makeRefreshTokenGL($liveSession);

    logoutGL($identityId)->assertRedirect();

    $sessionRow = DB::connection('pgsql')->table('root_idp_sessions')->where('id', $alreadyRevokedSession)->first();
    $tokenRow = DB::connection('pgsql')->table('root_oauth_refresh_tokens')->where('id', $alreadyRevokedToken)->first();

    expect(CarbonImmutable::parse($sessionRow->revoked_at)->equalTo($earlierRevokedAt))->toBeTrue()
        ->and(CarbonImmutable::parse($tokenRow->revoked_at)->equalTo($earlierRevokedAt))->toBeTrue();

    $liveRow = DB::connection('pgsql')->table('root_idp_sessions')->where('id', $liveSession)->first();
    expect($liveRow->revoked_at)->not->toBeNull();
});

// (в) sessions/tokens of a DIFFERENT identity are not touched.
it('в: сессии/токены ЧУЖОЙ идентичности не затронуты', function (): void {
    $identityId = makeIdentityGL();
    makeIdpSessionGL($identityId);

    $otherIdentityId = makeIdentityGL();
    $otherSession = makeIdpSessionGL($otherIdentityId);
    $otherToken = makeRefreshTokenGL($otherSession);

    logoutGL($identityId)->assertRedirect();

    $otherSessionRow = DB::connection('pgsql')->table('root_idp_sessions')->where('id', $otherSession)->first();
    $otherTokenRow = DB::connection('pgsql')->table('root_oauth_refresh_tokens')->where('id', $otherToken)->first();

    expect($otherSessionRow->revoked_at)->toBeNull()
        ->and($otherTokenRow->revoked_at)->toBeNull();
});

// (г) local session killed: Auth::check() === false, session()->getId() changed.
it('г: локальная сессия убита — Auth::check()===false, session()->getId() изменился', function (): void {
    $identityId = makeIdentityGL();
    makeIdpSessionGL($identityId);

    $this->actingAs(new StubIdentity($identityId), 'web');
    $this->withSession([]);
    $sessionIdBefore = $this->app['session']->getId();

    logoutGL($identityId)->assertRedirect();

    expect(auth('web')->check())->toBeFalse()
        ->and($this->app['session']->getId())->not->toBe($sessionIdBefore);
});

// (д) EnforceSessionLifetime: 11h in → still authenticated; 12h01m in → session killed (AC-15).
it('д: EnforceSessionLifetime — сессия жива через 11ч', function (): void {
    $identityId = makeIdentityGL();

    Route::middleware('tenant')->get('/oidc-lifetime-probe', fn () => response(auth()->check() ? 'authed' : 'guest'));

    $this->actingAs(new StubIdentity($identityId), 'web');
    $this->withSession(['_corex_auth_at' => CarbonImmutable::now()->toIso8601String()]);

    $this->travel(11 * 60)->minutes();

    $this->get('/oidc-lifetime-probe')->assertOk()->assertSee('authed');
});

it('д: EnforceSessionLifetime — сессия убита через 12ч01м (AC-15)', function (): void {
    $identityId = makeIdentityGL();

    Route::middleware('tenant')->get('/oidc-lifetime-probe', fn () => response(auth()->check() ? 'authed' : 'guest'));

    $this->actingAs(new StubIdentity($identityId), 'web');
    $this->withSession(['_corex_auth_at' => CarbonImmutable::now()->toIso8601String()]);

    $this->travel(12 * 60 + 1)->minutes();

    $this->get('/oidc-lifetime-probe')->assertStatus(401);
    expect(auth('web')->check())->toBeFalse();
});

it('revokes refresh tokens through the session index instead of scanning every identity token', function (): void {
    $identityId = makeIdentityGL();
    $token = makeRefreshTokenGL(makeIdpSessionGL($identityId));
    $other = makeIdpSessionGL(makeIdentityGL(), ['revoked_at' => now()]);
    DB::connection('pgsql')->statement(<<<'SQL'
        insert into root_oauth_refresh_tokens (id, session_id, client_id, token_hash, expires_at, revoked_at)
        select gen_random_uuid(), ?, 'oidc-test-client', md5(g::text) || md5((g + 1)::text), now(), now() from generate_series(1, 5000) g
        SQL, [$other]);
    DB::connection('pgsql')->statement('analyze root_oauth_refresh_tokens');
    $revokes = [];
    DB::connection('pgsql')->listen(static function ($query) use (&$revokes): void {
        if (str_starts_with($query->sql, 'update "root_oauth_refresh_tokens"')) {
            $revokes[] = [$query->sql, $query->bindings];
        }
    });

    logoutGL($identityId)->assertRedirect();

    expect(DB::connection('pgsql')->table('root_oauth_refresh_tokens')->where('id', $token)->value('revoked_at'))->not->toBeNull()
        ->and($revokes)->toHaveCount(1)
        ->and((string) DB::connection('pgsql')->scalar('explain (format json) '.$revokes[0][0], $revokes[0][1]))
        ->not->toContain('"Node Type": "Seq Scan"');
});
