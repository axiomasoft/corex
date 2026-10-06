# Changelog

## Unreleased — baseline v1

- Схема: наборы `tenant` и `tenant-cloud`, baseline v1. Cloud FK требуют tenancy; boxed использует только `tenant`. Baseline заменяет невыпущенную upgrade-цепочку.

## Unreleased — deterministic schema (P08.2)

- Generate model identifiers with Laravel HasUuids (UUIDv7); schema keys use literal UUID columns.

## Unreleased — native authorization (P07.4)

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `corex:schema:migrate`; migration publishing is removed.

- Breaking before 1.0: access evaluates its own role matrix and direct grants; AzGuard integration moved to the optional `corex-access-azguard` package.
- Added `aut_direct_grants` and the `RoleMatrixCompiled` event. Compilation dispatches synchronously in the tenant transaction and invalidates scope versions after commit.
- Removed `aut_roles.azguard_role_id`, `RoleView::azguardRoleId`, `RoleMatrixData::bindAzGuardRole`, and AzGuard options. Access checks deny inactive entities before reading cached scopes or grants.

## Unreleased — thin providers and registrars (P05.2)

- `CoreAuthServiceProvider` delegates to `AccessBindingsRegistrar` and `AccessIntegrationRegistrar`; bindings, order and lifetimes are unchanged.

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

Все заметные изменения `corex/auth` документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Пакет pre-1.0 — breaking-изменения публичных контрактов легальны в MINOR до 1.0 (D17).

## [Unreleased]

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

### Changed

- Password rehash delegates to the identity table owner's `IdentityPasswordRehasher` port with compare-and-swap inputs; auth no longer updates `users` directly.
- P7.7: `DefaultAccessChecker`, matrix/access helpers и компилятор матрицы
  получают `AuthOptions` вместо прямых вызовов `config('corex-auth.*')`.
- P7.6: `AuthOptions::fromArray` валидирует снимок `corex-auth`; провайдер и
  `PrincipalUserProvider` читают identity и pins из Options.
- Released metadata no longer declares development `path` repositories or an
  unconfirmed AzGuard remote. Private AzGuard resolution is a consumer-owned,
  canonical immutable channel; credentials are never stored in this package.

## [0.1.0] - 2026-07-27

First tagged release — roadmap-фаза P1 / B-11 (AzGuard-мост, entity-scoped RBAC, OIDC).
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
  + `Auth::provider('corex-principal', …)` registered in `CoreAuthServiceProvider::boot()`
  under `corex-auth.identity.enabled`. The package never writes to `config('auth.*')` itself —
  a consumer opts in with `auth.providers.users.driver = 'corex-principal'` (README).
  Parity with the OIDC cloud lane (`corex/tenancy`, P2.22) is structural: both logins end at
  this same provider — `Auth::attempt()` locally, `Auth::loginUsingId($sub)` after a successful
  OIDC callback (premortem C4).

- Схема `aut_*` (роль-надстройка над AzGuard, D15): 3 тенантские миграции (`aut_roles`,
  `aut_role_matrix`, `aut_role_assignments`) + отдельная cloud-миграция cross-package FK
  (`workspace_id → wsp_workspaces`, `granted_by → users`, D102).
- Контракты `Contracts\{Principal,RoleMatrixCompiler}` + enum'ы `Enums\{Scope,PrincipalType}`.
- Модели `Models\{Role,RoleMatrixRow,RoleAssignment}`; немутируемость системных ролей и их
  матричных строк (`Roles\SystemRoleImmutable`, AC-21) + `Roles\RoleCloner`.
- `Matrix\DatabaseRoleMatrixCompiler` — компилирует `aut_role_matrix` в
  `az_guard_role_permissions` (ключ `{panel}.{entity_key}.{action}`, D100) одной транзакцией,
  бамп версии scope-карты последним шагом (`Matrix\ScopeMapVersion`, tenant-солёный ключ, D103).
- `Matrix\MatrixPermissionCatalogBuilder` — регистрирует скомпилированные ключи в каталоге
  AzGuard (иначе резолвер/валидатор их вырезают, findings/P2.8-azguard-inventory §7).
- `Matrix\MatrixValidator` — предупреждение (не исключение) для роли с `assignable_to ⊇
  agent|api_key`, чья матрица использует own/dept/dept_tree.
- `database/seeders/SystemRolesSeeder` — сидирует owner/admin/employee с пустой матрицей;
  наполнение матрицы — задача модулей (`Extend\Permissions`, P2.9).
- `config/corex-auth.php` — панель UI-матрицы + конфиг-пины AzGuard (`table_names.roles`,
  `column_names.morph_type = uuid`, D104), применяются на `az-guard.*` в
  `CoreAuthServiceProvider` без правки самого пакета AzGuard.
- Второй (PG16) тест-лейн пакета: `tests/Pg` + `PgTestCase`, скрипты `test`/`test:pg`
  (D105) — существующий sqlite-лейн (7 тестов) не тронут.
- `Contracts\AccessChecker` (+`RecordContext`) — двухфазная проверка прав (B-11 §5.5):
  `allowed()` (capability, AzGuard-кеш), `scopeFor()` (эффективный scope по матрице,
  контекстная строка замещает базовую) и `canOnRecord()` (предикат по записи ИЛИ активный
  `direct_grant`). Реализация — `Access\DefaultAccessChecker`.
- `Access\AutMatrixGrantSource implements AzGuard\Registry\Contracts\GrantSource` (priority 85,
  D101) — читает `aut_role_assignments` с учётом workspace/`expires_at` (AC-26); действующее
  назначение системной роли `owner` даёт `PermissionSet::wildcard()` без хранения `'*'` (D103).
- `Access\PrincipalIdentity implements Principal, Authenticatable,
  AzGuard\Contracts\HasPermissions` — носитель принципала `user`/`agent`/`api_key` без строки
  `users` (AC-25); `allowed()` проверяет права через НЕЁ и настоящий публичный путь AzGuard.
- `Access\WorkspacePermissionLayer implements AzGuard\Contracts\PermissionLayer` — дискриминатор
  кеша AzGuard по активному workspace; биндинг за конфиг-ключом `corex-auth.bind_permission_layer`
  (слот `PermissionLayer` в AzGuard один).
- `own`/`dept`/`dept_tree` вырождаются в `Scope::None` для принципалов `agent`/`api_key`
  (ENFORCE, B-11 §5.5).
- `Contracts\ScopeResolver` (+ `Access\DefaultScopeResolver`) — Builder-аналог `canOnRecord()`
  (B-11 §5.5): накладывает WHERE по эффективному scope на листинг, зеркаля двухфазность
  `canOnRecord()` (direct-grant обход → capability-гейт → предикат), пятый опциональный параметр
  `?RecordContext` сверх снипета спеки (D116).
- `Access\ScopePredicate` — единственный владелец семантики 6 скоупов (`applyTo()` для Builder,
  `matches()` для записи); `DefaultAccessChecker` переведён на делегирование в него без изменения
  наблюдаемого поведения (D110). `workspace`-предикат явный (не полагается на глобальный scope,
  D107) и несёт модификатор `cascade_view` — иерархический просмотр родитель→потомки по
  `wsp_workspaces.path`, флаг читается строго `=== 'true'` у самого ctx-workspace (D117).
- `Access\DirectGrantLookup` — вынесенная из `DefaultAccessChecker` проверка активного
  AzGuard direct grant, общая для record- и builder-пути.
- `Access\ScopeAvailability` + `Exceptions\ScopeCapabilityMismatch` — нормализация scope по
  capability сущности на builder-пути: нет capability и нет колонки ⇒ `account`; нет capability,
  колонка ЕСТЬ ⇒ fail-closed исключение (declaration drift, D114), никогда не тихое расширение прав.
- `Access\WorkspaceScopeWiring` — автоматическая регистрация `WorkspaceScope` (глобальный scope)
  на моделях с capability `workspace_scoped` (прецедент `AuditCapabilityWiring`, D115); конфиг-пин
  `corex-auth.workspace_scope_class` (строка — `corex/auth` не зависит от `corex/tenancy`, D109).
- `Matrix\MatrixValidator` — второе правило-предупреждение: строка матрицы со scope, недопустимым
  для capability сущности (колонка есть, capability не объявлена), попадает в список предупреждений.
- PG-фикстуры `users.department_id` + `wsp_workspaces.settings`/`path` + хелпер
  `PgTestCase::migrateCoreDepartments()` (реальная миграция `sys_departments` из `corex/core`) —
  закрывают долг P2.20 «dept/dept_tree не прогнаны против реальных таблиц».
- `Matrix\ActiveEntityFilter` (P2.21, AC-27) — единственный владелец предиката «даёт ли
  entity_key ключ прямо сейчас»: entity_key неизвестен `EntityRegistry` ИЛИ его модуль неактивен
  (`ModuleRegistry::isActive()`) ⇒ fail-closed исключение из компиляции. `Matrix\DatabaseRoleMatrixCompiler`
  и `Matrix\MatrixPermissionCatalogBuilder` фильтруют через НЕГО (один шов, не два расходящихся
  условия); строки `aut_role_matrix` неактивного модуля не удаляются — только перестают давать
  ключ, восстанавливаются на re-enable без правки матрицы (D113).
- `Extend\PermissionsMerger` — листенер `ModuleEnabled`/`ModuleDisabled` (`CoreAuthServiceProvider::boot()`),
  на каждое enable/disable перекомпилирует всю матрицу (`RoleMatrixCompiler::recompileAll()`);
  строк матрицы сам не создаёт (D113 — это дело модулей). `merge()` также валидирует декларации
  `CoreX\Modules\Data\Permissions` против `EntityRegistry`: сущность, неизвестная реестру,
  игнорируется fail-closed и попадает в возвращаемый список предупреждений (никогда не в лог).
- `Access\PermissionSurface` — read-only читатель декларированной поверхности прав
  `(entity_key, action, допустимые scope)` активных модулей для UI-матрицы, поверх
  `Access\ScopeAvailability` (P2.9): `isScopeable() === false` ⇒ единственный допустимый scope —
  `account`.
- `Contracts\RoleMatrixCompiler` теперь забинжен в контейнере на `Matrix\DatabaseRoleMatrixCompiler`
  (`CoreAuthServiceProvider::register()`) — ранее ничего не резолвило интерфейс, только прямое
  конструирование класса.

### Requires

- **PostgreSQL 16** для `aut_*` DDL (`jsonb`, `CHECK`, `UNIQUE NULLS NOT DISTINCT`,
  `COMMENT ON` — без sqlite-эквивалента). `composer test` (sqlite) покрывает только существующий
  мост `TenantContextGuard`; гарантии DDL-парити и транзакционности компилятора проверяются
  исключительно на `composer test:pg`.
