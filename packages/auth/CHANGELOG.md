# Changelog

Все заметные изменения `corex/auth` документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Пакет pre-1.0 — breaking-изменения публичных контрактов легальны в MINOR до 1.0 (D17).

## [Unreleased]

### Added

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

### Requires

- **PostgreSQL 16** для `aut_*` DDL (`jsonb`, `CHECK`, `UNIQUE NULLS NOT DISTINCT`,
  `COMMENT ON` — без sqlite-эквивалента). `composer test` (sqlite) покрывает только существующий
  мост `TenantContextGuard`; гарантии DDL-парити и транзакционности компилятора проверяются
  исключительно на `composer test:pg`.
