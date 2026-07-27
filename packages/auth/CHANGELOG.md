# Changelog

Все заметные изменения `corex/auth` документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Пакет pre-1.0 — breaking-изменения публичных контрактов легальны в MINOR до 1.0 (D17).

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
  `CoreX\Modules\Extend\Permissions` против `EntityRegistry`: сущность, неизвестная реестру,
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
