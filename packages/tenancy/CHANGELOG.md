# Changelog

Все заметные изменения `corex/tenancy` документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Пакет pre-1.0 — breaking-изменения публичных контрактов легальны в MINOR до 1.0 (D17).
Пакет ещё не тегирован: в `v0.1.0` монорепо его нет (`git ls-tree -r v0.1.0 -- packages/tenancy` пусто).

## [0.1.0] - 2026-07-27

First tagged release — roadmap-фаза P1 / B-11 (physical DB-per-account tenancy). Repo-level
tag `v0.2.0` per D17 default policy (no per-package split mirror configured).

### Added

- **Support impersonation: `DatabaseImpersonationService` + read-only invariant + ACTOR `support`
  (P2.11, B-11 §5.9, AC-31, D137–D151).** One-time grant claim via a single atomic `UPDATE …
  WHERE` (D143, one-time/TTL/revocation/staff-presenter/reason/optional-4-eyes all in one
  statement); dual-log mirror into `root_account_events` synchronously BEFORE the tenant session
  opens (D146, first writer of that table). Impersonated session is **read-only by construction**
  (D141) — three deny-by-default layers: L1 `DenyImpersonatedWrites` (unsafe HTTP method without
  a route marker → 403) + `AllowImpersonatedWrites` marker; L2 `ImpersonatedModelWriteGuard`
  (global `eloquent.saving|deleting|restoring` wildcard listeners, closed exempt list, `allowing()`
  scope — boundary named honestly, D142: mass-update/delete and the `DB` facade are NOT covered);
  L3 `CoreX\Tenancy\ImpersonationGuard` (core) over the closed `ImpersonationRestrictedAction` enum.
  `ImpersonationSessionGuard` re-reads `revoked_at` from root on every request (no cache, D144) and
  enforces TTL; `X-CoreX-Impersonation` response header carries the banner state (D147, level 0
  has no UI). Login lane (`OidcLoginController`) split `assertMembership` into `assertAccountClaim`
  (both lanes) + `assertMembership` (ordinary lane only) — the `imp` branch skips membership and
  `IdentitySyncer` entirely (support is not an account member, D145); the ordinary lane's own
  Validation suite is unchanged. `require_approved_by` config (D148, default `false`) — optional
  4-eyes deployment policy.
- **`ResolveWorkspace`-middleware: `/w/{slug}` → workspace narrowing (P2.23, B-11 §5.4 п.4,
  D131–D136, AC-18).** Registered as the `tenancy.workspace` alias (D131) — always registered,
  never a member of the `'tenant'` group: a route with `->middleware(['tenant',
  'tenancy.workspace'])` narrows to a workspace, a route without the alias keeps the account-wide
  admin context (B-11 §5.4 п.4). Seven-step fail-closed pass (research/11 §2): tenancy
  initialized → authenticated → slug extracted from the FIRST occurrence of
  `tenancy.workspace.path_segment` (default `w`) in `$request->segments()` (marker with no
  following segment → 403) → `WorkspaceResolver::resolve()` → `TenancyManager::setWorkspace()` →
  post-condition re-read of `context()->workspace->id` (catches a no-op `setWorkspace()`, e.g.
  `NullTenancyManager`) → `$next`. Every failure is 403, including an unknown slug (D135 — 404
  would leak slug existence, B-11 §7.3 п.9) and a missing default workspace
  (`Workspaces\NoDefaultWorkspaceException extends RuntimeException`, D132 — a one-line `throw`
  change in `DatabaseWorkspaceResolver::defaultWorkspaceFor()`, existing P2.6 test
  `toThrow(RuntimeException::class)` stays green). New `WorkspaceResolver::class` container
  binding to `DatabaseWorkspaceResolver::class` (D136 — P2.6 had left it unbound). New `workspace`
  config section (`path_segment`, default `w`, env `TENANCY_WORKSPACE_PATH_SEGMENT`).
  `tests/Feature/ResolveWorkspaceTest.php` — 18 cases (negative-first): uninitialized tenancy,
  guest, non-member, suspended member, unknown slug, bare marker, partner-vs-parent visibility
  (AC-18), missing default, happy paths (with/without marker, custom marker, marker depth/repeat),
  no-op-manager post-condition, `WorkspaceScope` narrowing on `sys_departments`, alias-vs-group
  registration (AC-28), and context clearing after `tenancy()->end()`.

- **Cloud OIDC login lane + global logout (P2.22, D123/D125/D127/D129/D130), default OFF
  behind `tenancy.oidc.enabled`.** `Oidc\OidcDiscovery` — `GET
  {issuer}/.well-known/openid-configuration` via `Http` (not raw Guzzle, so `Http::fake()`
  works), cached by configurable store/TTL — endpoints are never hardcoded. `Oidc\JwksKeySet` —
  `Http::get(jwks_uri)` → `Firebase\JWT\JWK::parseKeySet()` → `Cache::store()->put()`, WITHOUT
  `Firebase\JWT\CachedKeySet` (D130 — no PSR-6 in this tree, and PSR-18 isn't `Http::fake()`-able
  either): on an unknown `kid` (AS key rotation) exactly ONE forced refetch runs, gated by a
  cooldown cache key, so key rotation is survived without waiting out the TTL and without an
  unknown-`kid` DoS lever on the JWKS endpoint. `Oidc\IdTokenVerifier` checks the id_token HEADER
  (`alg` ∈ `oidc.allowed_algs`, `kid` non-empty) BEFORE `JWT::decode()` even runs, then signature,
  then `iss`/`aud`/`nonce`/`sub` (`nonce` is mandatory per D129, not merely PKCE — the two protect
  different things). `Oidc\OidcClient` builds the authorize URL (`state` + `nonce` + PKCE S256
  `code_challenge`, written to the session exactly once) and exchanges `code` → tokens on
  `token_endpoint`; `client_secret`/`code_verifier` are `#[SensitiveParameter]`.
  `Http\Controllers\OidcLoginController` runs the full callback order (research/10 §3): one-time
  `state` (`hash_equals`, forgotten from the session the instant it is read) → code exchange →
  id_token verification → `acct === tenancy()->tenant->id` → `root_identity_accounts.status ===
  'active'` (RIGID equality — the enum is three-valued, `invited` must NOT pass) → `imp`
  fail-closed gate (`oidc.impersonation.enabled`, default `false` — P2.11 is not built yet) →
  `Identity\IdentitySyncer::syncToAccount()` (AC-14 sync-on-demand) → `session()->regenerate()` →
  `Auth::guard($guard)->loginUsingId($sub)` — the ONLY seam that constructs the login session; no
  class from `corex/auth` is ever imported here (D125), so both login lanes (local `Auth::attempt`
  in corex/auth, cloud OIDC here) converge on the exact same `PrincipalUserProvider`-built
  principal (premortem C4). `Http\Controllers\LogoutController` (AC-15) — global logout revokes
  every unrevoked `root_idp_sessions` row of the identity and cascades into
  `root_oauth_refresh_tokens` by `session_id`, always via `UPDATE revoked_at`, never `DELETE`
  (both tables are audited); tenant-local sessions still live up to
  `oidc.max_session_lifetime_minutes` (default 720 = 12h) after that, enforced by
  `Http\Middleware\EnforceSessionLifetime` reading the `_corex_auth_at` login marker — independent
  of whatever `session.lifetime` the consuming app sets. `Exceptions\OidcAuthenticationException`
  — every OIDC failure renders 403 (`render()`), no partial login, no secret in any message.
  `routes/oidc.php` (`GET /auth/login`, `GET /auth/callback`, `POST /auth/logout`, all
  `middleware('tenant')`) loads only under `oidc.enabled`, same idiom as
  `host_identification.enabled` — no `if (cloud)` branch anywhere, only config-gated container
  bindings and route/middleware registration (D10). New `oidc` config section (`issuer`,
  `client_id`/`client_secret`/`redirect_uri`, `allowed_algs` default `['RS256']`,
  `leeway_seconds`, `guard`, `discovery`/`jwks` cache stores+TTLs, `jwks.refetch_cooldown_seconds`,
  `impersonation.enabled` default `false`, `max_session_lifetime_minutes` default `720`).
  New dependency `firebase/php-jwt: ^7.1` (advisories all `<7.0.0`, unaffected).

- **Request resolution: host → account → status gate → tenant session (P2.10).**
  `Resolvers\HostTenantResolver` (extends stancl's `CachedTenantResolver`, D119 — no bespoke
  cache class) resolves `Request::getHost()` through `root_domains` → `root_accounts`, with a
  fail-closed double guard on `slug IS NULL`/`verified_at IS NULL`/`deleted_at` (§7.3 п.9);
  `Http\Middleware\ResolveTenantFromHost` initializes tenancy with the already-hydrated model
  (D120 — no per-request root-DB `find()`); `Http\Middleware\AccountStatusGate` enforces B-11
  §5.1 access rules purely from the hydrated tenant, no extra query. `Models\Domain` (new,
  `root_domains`) + `InvalidatesResolverCache`/`InvalidatesTenantsResolverCache` on
  `Account`/`Domain` bust the resolver cache on save/delete — no TTL wait needed after a status
  or domain change. Tenant-DB `sessions` migration (Laravel-canonical form, `user_id` as uuid) +
  `ScopeSessions` complete the middleware group `'tenant'` (merged into stancl's own pre-existing
  empty group of the same name, not overwritten — see `TenancyServiceProvider::boot()`).
  Config: new `host_identification`/`status_gate` top-level keys, projected onto stancl's
  `identification.resolvers.*`/`middleware` keys under the `host_identification.enabled` flag
  (boxed profile leaves this unregistered — AC-28). `sessions` is a Laravel-framework table, not
  a `corex/*`-owned domain table — deliberately absent from `DB_SCHEMA.md` (D122).

### Changed

- **Breaking (DDL, до первого тега):** `root_account_products.product` — продуктовый
  `CHECK (product IN ('flexcrm','flexstore'))` заменён product-agnostic ограничением формы
  `CHECK (product ~ '^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$')`; допустимый НАБОР продуктов задаётся
  прикладным уровнем, схема ядра его не перечисляет. Инвариант не ослаблен: NOT NULL +
  `varchar(32)` + непустота + формат слага; таблица получила нейтральный `COMMENT`. Правка внесена
  В СУЩЕСТВУЮЩУЮ миграцию (прод-инсталляций нет) — локальные БД чинит `migrate:fresh`/пересоздание.
  Замороженный контракт `DB_SCHEMA` синхронно бампнут до `FROZEN v1.1` под владельческим
  approval-gate (D94, P2.17).
- Комментарии таблиц `root_domains` и `root_templates` продукт-нейтральны: `slug.flexcrm.ru` →
  `slug.<base-domain>`, `tpl_flex_vNN` → «версионированная шаблонная БД (имя приходит данными —
  `db_name`)» (P2.17).
- Тест-фикстура шаблонной БД переименована `tpl_flex_v1` → `tpl_tenant_v1` (`tests/Stubs/`,
  константы `TEMPLATE_DB`/`PENDING_TEMPLATE_DB`) — идентификаторы уровня 0 без Flex-привязки (P2.17).
