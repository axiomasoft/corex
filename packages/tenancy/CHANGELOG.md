# Changelog

## Unreleased — installation, retention lookups and test coverage

- Remove unused direct runtime requirements for `ext-ctype`, `ext-session` and `ext-sodium`. Redis remains optional at runtime. CI supplies Redis for the accounts-menu conformance tests.
- Install refresh-token session and control-plane foreign-key lookup indexes through forward migrations so existing databases receive them.
- Run the tenancy configuration-report tests in the package suite. Add regression coverage for host and wire-value validation.

## Unreleased — CoreX 2.0 binding authority

- Host composition implements `TenantBindingAuthority` to verify the physical binding in the active Tenant runtime. Resolve accounts and workspaces through the trusted directory to restore persisted scope. CoreX uses declared tenant and central handlers with the shared v3 queue envelope.

## Unreleased — Packman governance

- Eloquent relation adapters expose covariant Model contracts; middleware and Artisan handlers are explicitly Internal.
- Adopt Packman API/SPI attributes and shared configuration/error contracts directly, with explicit Internal classification and no compatibility aliases. Declare package ownership in `extra.packman` and the monorepo manifest.
- Delegate provider composition to focused registrars and keep service providers within 80 lines.

## Unreleased — external Tenant ownership

- Remove inactive native cache/template options and bootstrapper/factory seams; manual context notifications remain distinct from protected Tenant runtime observations.

- Replace the embedded connection/cache/session runtime with TenantRuntimeManager over axiomasoft/tenant and a required trusted AccountIsolationMapper.
- Delegate physical provisioning, exports and deletion to the required AccountResources host port; remove native route resolvers, template cloning and physical template verification.
- Keep CoreX account/workspace, signup and export metadata orchestration; report missing host bindings in configuration diagnostics.
- Reject obsolete bootstrappers configuration; this is a breaking change to the unused package.

## Unreleased — Packman runtime

- Breaking: module declarations and schema registrations use Packman contracts directly; module lifecycle and schema execution are owned by Packman.

## Unreleased — workspace scope through the kernel contract (October 8, 2026 audit)

- `WorkspaceRegistrar` binds `CoreX\Context\Laravel\WorkspaceScopeProvider` to `WorkspaceScope`, so access no longer needs the scope's class name in config.

## Unreleased — wakeup through kernel contracts (audit 2026-10-08)

- The root_registry integration depends on `CoreX\Scheduling\Contracts` only; no `CoreX\Scheduler` import remains. With `wakeup.driver=root_registry` the integration is always registered and resolves the sweeper lazily; a missing backend is reported by the config report and fails on first use instead of being skipped silently.

## Unreleased — Laravel 13 platform

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Refresh the PHP API metadata for equivalent readonly/type/promotion declarations.
- Remove verified unused Internal helpers: `CentralValue::nonEmpty`, `TemplateClonePostgreSQLManager::deleteDatabase`.
- Remove unregistered Internal queue jobs: `RefreshAccountMembership`.

## Historical baseline v1 and signup/cleanup columns

- Squash the PostgreSQL `root` and `tenant` schema sets to baseline v1, one table per file, with explicit object names and bigint counters. Both sets used generation 1 and the PostgreSQL driver at that point.
- Replace `root_accounts.data` with nullable `signup_key`, `signup_request_digest`, `signup_cluster_id`, `signup_template_id` and a `pool_cleanup` boolean. Named CHECK/partial unique constraints enforce complete and unique live signup bindings. The fields stay outside model mass assignment.
- Preserve claim locks, immutable replay comparisons, cleanup marker-before-DROP ordering and retry recovery. This unpublished baseline replaces historical migration names; no installed-schema upgrade path is provided.

## Unreleased — deterministic schema

- Generate model identifiers with Laravel HasUuids (UUIDv7); package migrations use UUID columns and literal table names.

## Unreleased — host cache invalidation

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.
- `MigrationWaveOrchestrator` now requires `SchemaMigrator` and migrates all platform tenant sets on the account connection.

- Fence host mappings after committed account/domain saves and deletes, including cascade deletion.
  Late cache publication cannot restore a revoked mapping; transactional resolutions bypass shared cache
  to prevent publishing rolled-back verification. A shared generation invalidates all warmed host mappings
  on account/domain changes; unchanged cache hits still issue no SQL. Existing public signatures are retained.

## Unreleased — provisioning port

- Added `CoreX\Tenancy\Provisioning\Contracts\TemplateDatabaseCloner`. Account provisioning calls that port; `TemplateClonePostgreSQLManager` stays inside Provisioning and implements it.

## Unreleased — impersonation profiles and audit

- Replaced the approval flag with `corex-tenancy.impersonation.profile` (`dev`, `standard`, `regulated`). Grant rules are enforced in one atomic claim; regulated approval requires a distinct staff identity. Added grant and audit ports, committed central plus tenant audit for standard/regulated, fail-closed access, and a production acknowledgement for dev. The Eloquent write guard is scoped with an instance `allowing()` method. This is an unpublished pre-1.0 PHP/configuration contract change.

## Unreleased — replacement of the Stancl provider (pre-1.0 breaking change)

- Removed Stancl and its prerelease provider dependencies, runtime model projections, identifier binding,
  JobPipeline wiring and the obsolete PHPStan import rule. Account/Domain are plain central Eloquent models;
  Account uses UUIDv7, explicit fillable columns and immutable date/integer casts. Model attributes no longer
  use the legacy data column; signup claim and pool cleanup SQL still used it for idempotency and crash
  recovery until the later dedicated signup and cleanup columns were introduced.
- Configuration moves from `tenancy` to `corex-tenancy`, including wakeup, under one published file. Removed
  tenantModel/idGenerator/tenantDatabaseManager/provisioningPipeline options; `TenancyOptions::fromArray()`
  now accepts one configuration array. Middleware aliases and route/error identifiers retain their existing names.
- `AccountTemplateSnapshot::capture()` now accepts AccountRef and the explicit central connection. Provisioning
  synchronously runs CreateDatabaseFromTemplate with AccountRef/ProvisionParams; template integrity, replay
  verification, WAL_LOG cloning, export-before-drop and cache invalidation are preserved.

## Unreleased — thin providers and registrars

### Embedded tenancy engine (unpublished pre-1.0 breaking changes)

- **Added:** `Runtime\Contracts\TenantBootstrapper` (SPI), `Runtime\Events\TenantContextInitialized` / `TenantContextEnded`,
  `Runtime\Exceptions\TenantContextUnavailable` (`tenancy.context_unavailable`, `TenancyErrorCode::ContextUnavailable`),
  `Http\Middleware\ScopeSessionToTenant`, `Support\HostResolverCacheInvalidator`; internal `DatabaseTenancyManager` with
  connection / cache-prefix / session bootstrappers, per-job `QueueTenantContext`, `HostAccountResolver`.
- **Semantics:** every context switch (`initialize`, `end`, leaving `runFor`) forgets the container's scoped instances (the scope
  boundary is the tenant boundary); a failed revert poisons the runtime; queue payloads carry `corex_tenant` and `JobAttempted` is
  the single completion point; sessions are bound to the account (first binding invalidates earlier attributes; mismatch → 403).
- **Changed (BC):** `AccountStatusGate::__construct()` takes five required arguments (policy, `TenantContextResolver`,
  `AccountRead`, `AuthFactory`, central connection); `ResolveTenantFromHost` is a plain middleware (no stancl base class, new
  constructor) and ends the context after the response; `TenancyOptions` gains `bootstrappers`, `cacheStores`, `hostCache*`
  (replacing `bootstrapperOrder` and `hostIdentificationCache*`); `TenantCouldNotBeIdentifiedByHostException` no longer extends a
  stancl exception.
- **Configuration / security:** config key `tenancy.bootstrapper_order` → `tenancy.bootstrappers`; new `tenancy.cache_stores`
  (null = the default cache store; only redis, memcached, database and file stores are supported, `array`/`null`/`failover` are
  refused); the host cache store must be outside `cache_stores`; tenant HTTP sessions support the `database`, `redis`,
  `memcached` and `file` drivers (redis/memcached need a dedicated `session.store`), other drivers are refused before the first
  switch.
- **Removed:** `Bootstrappers\WorkspaceContextBootstrapper`, `Resolvers\HostTenantResolver`,
  `Listeners\RevertTenancyOnJobException`; the stancl provider is no longer discovered (`extra.laravel.dont-discover`).

- Registrar closures with several statements moved to named methods; behaviour is unchanged.

## Unreleased — typed Options and runtime configuration checks

- `TenancyOptions` is a lazy shared singleton; `IdentitiesReconcileCommand` and `PendingCreateCommand` read the central connection from it (the new `handle()` parameter is optional).

## Unreleased — CI ownership and test isolation repair

- Testbench teardown disconnects every remaining database connection after restoring the public schema, preventing retained application cycles from exhausting PostgreSQL's connection limit.

## Unreleased — kernel package and API migration

- Breaking before 1.0: CoreX\Tenancy is owned exclusively by this package; AccountRead, central API and domain trust ports expose declared account/domain DTOs and events. TenancyOptions and feature-owned Laravel bindings replace implicit access to persistence internals.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking before 1.0: supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — typed error contract

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## Unreleased — package identifier alignment

### Changed

- Implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases).

- Breaking before 1.0: Rename publish tags to `corex-tenancy-*`, commands to `corex:tenancy:*`, and environment variables to `COREX_TENANCY_*`. The engine still reads the `tenancy` config key.

## Unreleased

- Declare ext-redis for package development, keep it optional for runtime consumers, and explicitly skip its conformance tests when the extension is unavailable.

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

- The connection guard decorates the kernel's scoped semantic restorer; the identity owner supplies the `users` password rehash writer.

- Renamed `Workspaces` to `Workspace` and preserved DomainTrust/Impersonation/Workspace model and event FQCNs via PSR-4 maps.

- Moved OIDC into `Authentication/Oidc` as a context-first adapter. Identity user/event/job and OIDC HTTP/exception classes keep FQCNs via PSR-4 maps. Routes and `oidc.enabled` stay the same.

- Moved account lifecycle into `Account/` and physical-database provisioning into `Provisioning/`. `TemplateClonePostgreSQLManager` keeps `CoreX\Tenancy\Database` via classmap; account events, `CreateDatabaseFromTemplate`, and account/migration models keep FQCNs via PSR-4 maps.

- Removed `Membership\Internal` and moved `Central` into `Membership\Transport`. `Expiry` is the path `Expiration`; class names stay. Membership events and `RefreshAccountMembership` keep their FQCNs.

- Moved runtime adapters into `Runtime/` and Laravel seams into `Laravel/` without renaming stable FQCNs. Storage classes are context-first under `CoreX\Tenancy\Runtime\Storage`. `TenantContextRestorer` is now the scoped connection-guarded wrapper: it delegates the semantic check and refuses a mismatched active connection. It does not look up or initialize an account from the payload.

- Added unregistered internal accounts-list transport and Redis display cache
  adapters. Both require explicit consumer construction, retain the strict
  authenticated/no-store channel profile, and never make menu data authority.

- Add an unregistered final credential-write admission boundary that keeps the pair lock, lease samples, callback SQL, and application fence on one connection transaction.

- Added an explicitly constructed, tenant-lane physical pair/inbox mapping for
  membership adapters. It is denied by default, requires canonical snapshot
  digests and UUID inbox workers, and is never registered automatically.

- Export and physical DROP now require the existing consumer integrity verifier
  for every archive, including local files. Size-only writers and legacy ready
  flags fail closed until the same qualified verifier is configured for export
  and purge; constructor and public contract signatures are unchanged. The
  verifier must bind readability, size, digest and tenant ownership to a trusted
  durable manifest.
- Latest-export selection uses the generated UUID as a deterministic tie-break
  when timestamps match, preserving refusal after a newer interrupted export.

- Added optional, explicitly constructed private `ObjectTenantStorage` using
  qualified Flysystem S3 adapter 3.35.3. Object paths, URLs, usage and export
  are explicitly unsupported; ephemeral files remain account-scoped local files.
- Queue workspace restoration clears previous workspace for same-account jobs
  and refuses unavailable queued workspaces. Local storage usage refuses root symlinks.
- Package tests now discover per-package PostgreSQL conformance cases.

- The earlier distribution candidate required the sealed, immutable provider pair
  `axiomasoft/stancl-tenancy` `4.0.0-alpha1` and
  `axiomasoft/stancl-jobpipeline` `2.0.0-alpha1`. They were unpublished
  packages. The distribution proof required an approved root repository
  and disallowed a moving-branch fallback. The external Tenant integration superseded these dependencies.

At this stage, the package followed the pre-1.0 policy allowing breaking changes to public contracts in minor releases. The monorepo's `v0.1.0` tag did not contain `packages/tenancy`. See the [changelog format](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Principal workspace API

- Added `Contracts\PrincipalWorkspaceResolver::resolveFor()` for trusted session, token or service identities on the initialized account connection. `DatabaseWorkspaceResolver` implements it without implicit `auth()` reads; the existing `WorkspaceResolver::resolve()` delegates and remains compatible. Null/empty principals fail closed and membership is queried afresh.

### Changed

- Provisioning, pending-pool claims, queued clone replay, clone DDL,
  activation, and migration-wave admission now fail closed on template
  integrity before their first write. Queue workers reload the central account
  and re-verify after delay; an already-existing account database must match
  the trusted template catalogue before it is accepted as an idempotent no-op.

- Changed the public `CentralApiClient` to the strict `corex-membership/1`
  profile: every read now carries product and correlation identifiers; snapshots
  retain exact state, revision and revoke-generation values; revoke returns a
  durable receipt. The legacy boolean HTTP adapter was removed rather than
  silently weakening this contract. The qualified strict transport remains
  unregistered until a real cloud binding is supplied.

### Added

- Added the opt-in domain-trust SPI: consumer-owned policy, challenge-store,
  proof-verifier and authorizer contracts now drive a CoreX-owned, generation-
  guarded trusted-state transition. The default profile supplies no DNS, HTTP,
  TLS, credential, or proof adapter and keeps custom-domain verification
  disabled.

- Added internal signed template-integrity primitives: the additive
  `schema_hash` migration, canonical catalogue serializer, manifest verifier,
  and qualified advisory-lock seam. Consumer write-path adoption remains a
  separate item.

- Added an unregistered, disposable fenced credential reference seam for local membership fixtures.
- Added an unregistered durable trigger inbox and pull-only refresh reference seam for local fixtures.
- Added an unregistered local membership composition reference for A/B revoke, credential cleanup,
  fenced regrant, restore denial, and trigger-refresh fixtures. It does not register an auth guard,
  runtime projection, production schema, or public API.
- Added an unregistered internal accounts-menu read/cache-loss reference. It preserves the public
  surface, never grants access from menu data, and requires separate central/API adoption.

- Opt-in `CentralApiClient` HTTP transport, UUID-only membership DTOs/events,
  typed deny/unavailable/protocol failures, and an account-local membership
  projection. Revocation refreshes authoritative membership under a per-account
  lock, deactivates only the target tenant user, and deletes only that tenant's
  sessions. Central writers, credentials provisioning, and SSO authority remain
  outside this package.
- `DatabaseIdentitySyncer` now applies membership activity independently from
  profile freshness: a missing or non-active membership deactivates the existing
  UUID projection even when the profile timestamp is unchanged; regrant restores
  that same UUID without a contact-based merge.

- `ProvisioningClaim` and proof-gated `ClaimAccountSignup` reserve one existing
  pending account for an immutable signup key. The root-only Action uses a
  transaction-scoped PostgreSQL advisory lock and the partial
  `root_accounts_signup_key_uq` expression index, returns the original account
  for compatible replay, preserves unrelated JSON data, and fails closed for
  incompatible keys, tombstones, unqualified templates, or an empty pool. It
  never clones, activates, or exposes a runtime entrypoint; those orchestration
  responsibilities remain consumer-owned.
- `ContextTenantStorage` binds the core `TenantStorage` contract to the
  initialized tenancy context. Its `local` and explicitly local `fake-s3`
  modes create private, captured scoped filesystems rather than exposing a
  mutable Stancl named disk. The fake mode has object-prefix test semantics
  only; real S3, credentials, backup, restore and moves remain unimplemented.

## 0.1.0 development history — July 27, 2026

The original version entry describes physical database-per-account tenancy. Its intended monorepo release tag was `v0.2.0`. At this point there was no separate package distribution mirror.

### Added

- **Support impersonation.** `DatabaseImpersonationService` claims a grant with one atomic update that checks remaining use, TTL, revocation, presenting staff identity, reason and, when required, independent approval. It writes the central audit record to `root_account_events` before opening the tenant session. Impersonation records the `support` actor.
- Impersonated sessions default to read-only. `DenyImpersonatedWrites` returns 403 for unsafe HTTP methods unless the route carries `AllowImpersonatedWrites`. `ImpersonatedModelWriteGuard` monitors Eloquent saving, deleting and restoring events with a closed exemption list and an explicit `allowing()` scope. Mass updates, mass deletes and direct `DB` writes do not emit those model events. The core `CoreX\Tenancy\ImpersonationGuard` covers the closed `ImpersonationRestrictedAction` enum.
- `ImpersonationSessionGuard` rereads central `revoked_at` on every request and enforces grant TTL. `X-CoreX-Impersonation` carries banner state for a host UI. In `OidcLoginController`, `assertAccountClaim` guards both login paths. Only ordinary users undergo `assertMembership` and `IdentitySyncer`. The later impersonation profiles superseded the original optional two-person approval policy.
- **Workspace selection.** Routes opt in through `->middleware(['tenant', 'tenancy.workspace'])`. The `tenancy.workspace` alias is always registered. Routes using only `tenant` retain the account-wide administration context.
- `ResolveWorkspace` requires initialized tenancy and an authenticated principal. It reads the slug following the first configured `tenancy.workspace.path_segment` in `$request->segments()` (default `w`). A marker without a following segment fails. It calls `WorkspaceResolver::resolve()`, sets the workspace with `TenancyManager::setWorkspace()` and rereads `context()->workspace->id` before invoking the next handler. This catches a manager that ignores the change. Failures return 403, including unknown slugs and missing defaults. `DatabaseWorkspaceResolver::defaultWorkspaceFor()` uses `Workspaces\NoDefaultWorkspaceException`, a `RuntimeException`.
- The container bound `WorkspaceResolver` to `DatabaseWorkspaceResolver`. `TENANCY_WORKSPACE_PATH_SEGMENT` controlled the path marker. The 18 cases in `tests/Feature/ResolveWorkspaceTest.php` covered uninitialized or guest access, absent/suspended membership, unknown or empty slugs, partner/parent visibility, missing defaults, optional/custom/repeated markers, no-op managers, `sys_departments` scope narrowing, alias registration and cleanup after `tenancy()->end()`.
- **Cloud OIDC and global logout.** The original `tenancy.oidc.enabled` setting defaulted to false. `Oidc\OidcDiscovery` fetches `{issuer}/.well-known/openid-configuration` via Laravel HTTP and caches the result with a configurable store and TTL. `Oidc\JwksKeySet` reads `jwks_uri`, calls `Firebase\JWT\JWK::parseKeySet()` and stores the keys in Laravel Cache. One cooldown-limited forced refresh handles an unknown `kid` during key rotation. This implementation uses Laravel's HTTP test doubles and does not depend on PSR-6 or `Firebase\JWT\CachedKeySet`.
- `Oidc\IdTokenVerifier` checks the allowed `alg` and nonempty `kid` before `JWT::decode()`, verifies the signature, and validates `iss`, `aud`, `nonce` and `sub`. Both nonce and PKCE are required. `Oidc\OidcClient` stores state, nonce and the PKCE S256 challenge once, constructs the authorization URL and exchanges the code at the discovered `token_endpoint`. The client secret and code verifier use `#[SensitiveParameter]`.
- `Http\Controllers\OidcLoginController` consumes the one-time state, compares it with `hash_equals`, exchanges the code, verifies the token and checks the tenant account claim. The ordinary login path requires `root_identity_accounts.status === 'active'`, synchronizes the identity, regenerates the session and calls `Auth::guard($guard)->loginUsingId($sub)`. Impersonation requires its own opt-in. Local and cloud login converge on the same `PrincipalUserProvider` without importing `corex/auth` internals.
- Global logout updates `revoked_at` on active `root_idp_sessions` and their `root_oauth_refresh_tokens` by `session_id`, retaining both audit histories. Tenant-local sessions may live until `oidc.max_session_lifetime_minutes` (default 720 minutes). `Http\Middleware\EnforceSessionLifetime` reads `_corex_auth_at` independently of the host's session lifetime. `OidcAuthenticationException` renders 403 without creating a partial session or exposing secrets.
- When enabled, `routes/oidc.php` registers `GET /auth/login`, `GET /auth/callback` and `POST /auth/logout` with tenant middleware. Configuration covered issuer, client ID, client secret, redirect URI, allowed algorithms (default `['RS256']`), leeway, guard, discovery and JWKS cache stores/TTLs, refresh cooldown, impersonation (default false) and maximum session lifetime. The package added `firebase/php-jwt: ^7.1`.
- **Host resolution and session isolation.** The original `Resolvers\HostTenantResolver` extended Stancl's `CachedTenantResolver`. It resolved `Request::getHost()` through `root_domains` and `root_accounts`, enforcing custom-domain verification and soft-delete checks. `ResolveTenantFromHost` initialized tenancy with the hydrated model. `AccountStatusGate` applied the status rules without reloading the account.
- Account and domain saves/deletes invalidated resolver caches through `InvalidatesResolverCache` and `InvalidatesTenantsResolverCache`. A Laravel tenant `sessions` migration used a UUID `user_id`. `ScopeSessions` extended the existing tenant middleware group. Host identification and status-gate configuration populated Stancl's identification resolver and middleware settings when enabled. The boxed profile left these unregistered. Laravel owned the sessions table.

### Schema changes before the first tag

- The `root_account_products.product` constraint changed from the application-specific `CHECK (product IN ('flexcrm','flexstore'))` to `CHECK (product ~ '^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$')`. Applications select permitted products. SQL still requires a nonempty slug in a non-null `varchar(32)`. The table comment was made product-neutral. Since the migration had no production installations at that time, local databases could be recreated from the corrected migration. The frozen schema contract advanced to version 1.1 with owner approval.
- Comments on `root_domains` and `root_templates` now describe `slug.<base-domain>` and a versioned template database whose name is supplied in `db_name`. They previously used `slug.flexcrm.ru` and `tpl_flex_vNN`. The original `tpl_flex_v1` database fixture became `tpl_tenant_v1` in the test stubs, including the `TEMPLATE_DB` and `PENDING_TEMPLATE_DB` constants.

### Internal prototypes

- An internal management-only identity hydrator and disposable fixtures associated accounts solely by UUID. Missing or inactive authority disabled the user independently of profile age. Hydration could neither activate a user nor modify leases, credential generations, epochs or remember tokens. The prototype supplied no production registration or changes to the public sync contract.
- Typed membership snapshots and revoke receipts exercised scalar GET serialization, certificate-bound service claims, no-store/correlation validation and a trigger-only RS256 parser. An uncertain revoke retained the original request ID and payload. Audit actors supplied no authority. Disposable wire, signature and central-store fixtures exercised the behavior before a cloud deployment or public contract change.
- A disposable PostgreSQL `numeric(20,0)` pair-store prototype checked canonical unsigned counters, operation and application fences, idempotent invalidation and independent-witness restore denial. It introduced no production migration, provider registration or public contract.
- Fixture-only clock arithmetic denied an operation exactly at the 45-second expiry boundary and cleared a paused owner's pair fence before a delayed commit. These checks provided local evidence only and did not introduce a shared clock or worker termination mechanism.
