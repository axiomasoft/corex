# Changelog

## Unreleased

- Check the current database schema when applying department access rules and resolve department-tree joins on prefixed connections.

## CoreX 2.0 — Metadata authorization

- Resolve metadata field permissions against one verified PostgreSQL snapshot for local Access policies.

## Unreleased — Laravel identity

- Moved the public adapter to `CoreX\Access\Laravel\PrincipalIdentity`. Laravel callers and the native user provider use this path.

## Unreleased — field use authorization

- Added separate read, write, search, filter, sort, aggregate and export checks for stable field IDs. FieldUsePolicy returns an unconditional grant, a witnessed set of allowed records, or a denial. DataScopeAuthorizer enforces record and field grants on the verified physical connection.

## Unreleased — CoreX authorization

- Install `axiomasoft/corex-access` to check actions, authorize individual records and filter Eloquent queries. CoreX registers its AzGuard policy for the role matrix and direct grants during Laravel discovery.
- Manage role scopes and assignments through `RoleAdministration` and direct grants through `DirectGrants`. An application can delegate an individual permission to a declared policy panel through `corex-access.policy_panels`.

## Unreleased — Packman governance

- Adopt Packman API/SPI attributes and shared configuration/error contracts directly, with explicit Internal classification and no compatibility aliases. Declare package ownership in `extra.packman` and the monorepo manifest.


## Unreleased — package boundaries

- Breaking before release: Rename the public Laravel provider to `CoreX\Access\AccessServiceProvider`.

## Unreleased — Packman runtime

- Breaking: module declarations and schema registrations use Packman contracts directly; module lifecycle and schema execution are owned by Packman.

## Unreleased — principal concept in the kernel (audit 2026-10-08 P-02)

- Breaking before release: remove `CoreX\Access\Contracts\Principal` and `CoreX\Access\Enums\PrincipalType` aliases. Consumers use kernel `CoreX\Context\Contracts\Principal` and `CoreX\Context\Enums\PrincipalType` directly; `PrincipalIdentity` implements the kernel contract.
- `PrincipalIdentity` is therefore also the operation caller (`RequestContext::principal()`), with no adapter.

## Unreleased — fail-closed workspace scope (audit 2026-10-08 P-04)

- `corex-access.workspace_scope_class` now defaults to `null`: the workspace scope comes from the kernel `WorkspaceScopeProvider` that tenancy binds; without it no workspace scope is applied (boxed, AC-28).
- A class set in `workspace_scope_class` overrides the provider and must exist; a missing class now throws `InvalidArgumentException` at boot instead of silently leaving workspace-scoped models unscoped. Hosts that published the old default without installing tenancy must remove the key (or set it to `null`). `AuthOptions` is unchanged.

## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Refresh the PHP API metadata for equivalent readonly/type/promotion declarations (D130).

## Unreleased — strict Access types

- `PrincipalIdentity::getAuthPassword()` now returns `string`, matching Laravel's authentication contract. Identities without a password hash return an empty string and still deny password authentication and rehashing.
- Credentials PHPDoc accepts integer and string keys as required by Laravel's `UserProvider`; named credential validation is unchanged. Database authority identifiers, scope values, cache versions and increment results are checked before use; malformed state is rejected.
- Access analysis has no PHPStan baseline entries and a zero debt ceiling; query predicates, valid authentication and schema are preserved.

## Unreleased — baseline v1

- The `tenant` and `tenant-cloud` schema sets started at baseline v1, which replaced the unreleased upgrade sequence. Cloud foreign keys require tenancy. Boxed installations use only `tenant`.

## Unreleased — deterministic schema (P08.2)

- Generate model identifiers with Laravel HasUuids (UUIDv7); schema keys use literal UUID columns.

## Unreleased — native authorization (P07.4)

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.

- Access introduced product-owned role matrix and direct grant evaluation.
- Added `aut_direct_grants` and the `RoleMatrixCompiled` event. Compilation dispatches synchronously in the tenant transaction and invalidates scope versions after commit.
- Removed `aut_roles.azguard_role_id`, `RoleView::azguardRoleId`, `RoleMatrixData::bindAzGuardRole`, and AzGuard options. Access checks deny inactive entities before reading cached scopes or grants.

## Unreleased — thin providers and registrars (P05.2)

- `AccessServiceProvider` delegates to `AccessBindingsRegistrar` and `AccessIntegrationRegistrar`; bindings, order and lifetimes are unchanged.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- `AuthOptions` is a lazy shared singleton resolved in `boot()`; it is no longer bound with `instance()` while the provider registers.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): Authorization now exposes Principal, RecordContext and AccessChecker ports plus typed AuthOptions and matrix registration; AzGuard identities and permission storage remain implementation details.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — typed error contract (P04.3)

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## Unreleased — package identifier alignment

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Breaking before 1.0: Rename config and publish identifiers to `corex-access` and environment variables to `COREX_ACCESS_*`.

All notable changes to `corex/auth` are recorded here following [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Before 1.0, minor releases may introduce breaking changes to public contracts (D17).

## [Unreleased]

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

### Changed

- Password rehash delegates to the identity table owner's `IdentityPasswordRehasher` port with compare-and-swap inputs; auth no longer updates `users` directly.
- `DefaultAccessChecker`, the matrix and access helpers, and the matrix compiler now receive `AuthOptions` in place of direct `config('corex-auth.*')` calls (P7.7).
- `AuthOptions::fromArray` validates the `corex-auth` snapshot. The provider and `PrincipalUserProvider` read identity settings and configuration pins from those options (P7.6).
- Released metadata no longer declares development `path` repositories or an
  unconfirmed AzGuard remote. Private AzGuard resolution is a consumer-owned,
  canonical immutable channel; credentials are never stored in this package.

## [0.1.0] - 2026-07-27

First tagged release, covering the AzGuard bridge, entity-scoped RBAC and OIDC in roadmap phase P1 and blueprint B-11.
Repo-level tag `v0.2.0` per D17 default policy (no per-package split mirror configured).

### Added

- **Local login lane / principal seam (P2.22, D125).** `Identity\PrincipalUserProvider
  implements Illuminate\Contracts\Auth\UserProvider` — all six interface methods, table/columns
  entirely from `config('corex-auth.identity')` (`connection`/`table`/column map), queries via
  `DB::connection($conn)->table($table)` (no `corex/tenancy` model import, AC-28). The "live
  user" filter (`active = true AND deleted_at IS NULL`) is applied in BOTH `retrieveById()` and
  `retrieveByCredentials()` — a deactivated user's live session does not outlive the
  deactivation. `retrieveByCredentials()` ignores the `password` key when building the WHERE
  (Laravel canon) and matches e-mail case-insensitively (`whereRaw('lower(...) = ?')` — the
  schema's unique index is exactly that). `validateCredentials()` is `Hash::check()` only, never
  a string comparison. `rehashPasswordIfRequired()` issues an `UPDATE` iff
  `Hash::needsRehash($hash) || $force`, otherwise zero queries. `retrieveByToken()`/
  `updateRememberToken()` are permanent no-ops — `users` (P2.7, frozen DDL) carries no
  `remember_token` column and one will not be added.
- `Access\PrincipalIdentity` gains an optional trailing constructor parameter `?string
  $passwordHash = null`; `getAuthPassword()` now returns it (previously always `null`).
  `fromPrincipal()` and the `agent`/`api_key` lanes are unaffected — only
  `PrincipalUserProvider` ever passes a hash.
- `config/corex-auth.php` — new `identity` section (`enabled`/`connection`/`table`/`columns`)
  + `Auth::provider('corex-principal', …)` registered in `AccessServiceProvider::boot()`
  under `corex-auth.identity.enabled`. The package never writes to `config('auth.*')` itself —
  a consumer opts in with `auth.providers.users.driver = 'corex-principal'` (README).
  Parity with the OIDC cloud lane (`corex/tenancy`, P2.22) is structural: both logins end at
  this same provider — `Auth::attempt()` locally, `Auth::loginUsingId($sub)` after a successful
  OIDC callback (premortem C4).

- Added three tenant migrations for the role layer above AzGuard (`aut_roles`, `aut_role_matrix`, `aut_role_assignments`, D15). A separate cloud migration installs cross-package foreign keys from `workspace_id` to `wsp_workspaces` and from `granted_by` to `users` (D102).
- Added `Contracts\{Principal,RoleMatrixCompiler}` and `Enums\{Scope,PrincipalType}`.
- Added `Models\{Role,RoleMatrixRow,RoleAssignment}` and `Roles\RoleCloner`. `Roles\SystemRoleImmutable` protects system roles and their matrix rows (AC-21).
- `Matrix\DatabaseRoleMatrixCompiler` compiles `aut_role_matrix` into `az_guard_role_permissions` in a single transaction using `{panel}.{entity_key}.{action}` keys (D100). The last step increments the tenant-specific scope map version in `Matrix\ScopeMapVersion` (D103).
- `Matrix\MatrixPermissionCatalogBuilder` registers compiled keys in AzGuard's catalog so its resolver and validator retain them (findings/P2.8-azguard-inventory §7).
- `Matrix\MatrixValidator` warns when a role assignable to `agent` or `api_key` uses `own`, `dept` or `dept_tree` in its matrix.
- `database/seeders/SystemRolesSeeder` creates owner, admin and employee roles with an empty matrix. Modules populate the matrix through `Extend\Permissions` (P2.9).
- `config/corex-auth.php` defines the matrix UI panel and AzGuard configuration pins (`table_names.roles`, `column_names.morph_type = uuid`, D104). `AccessServiceProvider` applies them to `az-guard.*`.
- Added the PostgreSQL 16 suite in `tests/Pg` with `PgTestCase`. The `test` and `test:pg` scripts retain the existing seven SQLite tests (D105).
- `Contracts\AccessChecker` and `RecordContext` provide two-phase permission checks (B-11 §5.5). `allowed()` uses AzGuard's capability cache. `scopeFor()` resolves the effective matrix scope, with a context row overriding the base row. `canOnRecord()` checks the record predicate or an active direct grant. `Access\DefaultAccessChecker` implements the contract.
- `Access\AutMatrixGrantSource` implements `AzGuard\Registry\Contracts\GrantSource` at priority 85 (D101). It reads `aut_role_assignments` subject to workspace and expiry (AC-26). An active system `owner` assignment grants `PermissionSet::wildcard()` without storing a literal `'*'` (D103).
- `Access\PrincipalIdentity` implements `Principal`, `Authenticatable` and `AzGuard\Contracts\HasPermissions` for `user`, `agent` and `api_key` principals without a `users` row (AC-25). `allowed()` passes this identity to the public AzGuard permission check.
- `Access\WorkspacePermissionLayer` implements `AzGuard\Contracts\PermissionLayer` and partitions the AzGuard cache by active workspace. `corex-auth.bind_permission_layer` controls the binding because AzGuard has a single `PermissionLayer` slot.
- For `agent` and `api_key` principals, `own`, `dept` and `dept_tree` resolve to `Scope::None` (ENFORCE, B-11 §5.5).
- `Contracts\ScopeResolver`, implemented by `Access\DefaultScopeResolver`, applies the effective scope to listing queries. It mirrors the record check by considering a direct grant first, then checking the capability and predicate. The fifth optional `?RecordContext` parameter extends the specification example (B-11 §5.5, D116).
- `Access\ScopePredicate` implements all six scopes for queries (`applyTo()`) and records (`matches()`). `DefaultAccessChecker` delegates both paths to it (D110). The explicit `workspace` predicate works independently of global scopes (D107). When the context workspace has `cascade_view === 'true'`, it includes descendants along `wsp_workspaces.path` (D117).
- Extracted active AzGuard direct-grant lookup from `DefaultAccessChecker` into `Access\DirectGrantLookup`, shared by record and query checks.
- `Access\ScopeAvailability` normalizes query scopes against entity capabilities. If both the capability and its column are absent, it resolves to `account`. A column present without its declared capability raises `Exceptions\ScopeCapabilityMismatch`, preventing a silent grant when the declaration drifts (D114).
- `Access\WorkspaceScopeWiring` automatically registers the global `WorkspaceScope` on `workspace_scoped` models, following `AuditCapabilityWiring` (D115). `corex-auth.workspace_scope_class` accepts a class name so `corex/auth` can operate independently of `corex/tenancy` (D109).
- `Matrix\MatrixValidator` also warns about matrix scopes whose entity column exists without the required capability.
- PostgreSQL fixtures add `users.department_id` and `wsp_workspaces.settings` and `path`. `PgTestCase::migrateCoreDepartments()` runs the actual `sys_departments` migration from `corex/core` to test `dept` and `dept_tree` against real tables (P2.20).
- `Matrix\ActiveEntityFilter` owns the decision to compile an `entity_key` (P2.21, AC-27). Keys missing from `EntityRegistry` or belonging to an inactive module (`ModuleRegistry::isActive()`) are excluded. `Matrix\DatabaseRoleMatrixCompiler` and `Matrix\MatrixPermissionCatalogBuilder` share the filter. An inactive module's `aut_role_matrix` rows remain stored and take effect again on enable (D113).
- `Extend\PermissionsMerger` listens for `ModuleEnabled` and `ModuleDisabled` in `AccessServiceProvider::boot()`. Each transition calls `RoleMatrixCompiler::recompileAll()`; modules own the rows (D113). `merge()` checks `Packman\Modules\Data\Extenders\Permissions` declarations against `EntityRegistry`, ignores unknown entities and reports them through the returned warnings.
- `Access\PermissionSurface` exposes the active modules' declared entity keys, actions and permitted scopes to the matrix UI. It uses `Access\ScopeAvailability` (P2.9). When `isScopeable() === false`, the only permitted scope is `account`.
- `AccessServiceProvider::register()` now binds `Contracts\RoleMatrixCompiler` to `Matrix\DatabaseRoleMatrixCompiler`, making the interface resolvable through the container.

### Requires

- The `aut_*` schema requires **PostgreSQL 16** for `jsonb`, `CHECK`, `UNIQUE NULLS NOT DISTINCT` and `COMMENT ON`. At this release `composer test` on SQLite covers the `TenantContextGuard` bridge. Run `composer test:pg` to verify DDL parity and compiler transaction behavior.
