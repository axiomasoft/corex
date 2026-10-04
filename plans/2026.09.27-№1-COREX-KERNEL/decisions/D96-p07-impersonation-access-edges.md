---
id: D96
date: 2026-10-04
status: accepted
item: P07
items: [P07, P07.3, P07.4, P07.5]
supersedes: []
superseded_by: null
---
# D96 — p07-impersonation-access-edges (accepted)

**Actor:** author-choice (designer/claude-code/claude-opus-5-5/high) по мандату B6 и D72.
**Evidence:** RAG:— `artifacts/P07-design/facts/{impersonation,azguard-coupling,package-edges}.txt`, `research/04-architecture.md` §4 и §9, D14, D16, D69 п.7, D70 п.6 и п.9; RAG:✅ `research/P07-design-rag.md` W6–W7 (`pplx-03`, `http-01`).

## Solution

### A. Impersonation (P07.3)

1. **Доменные порты** (не обёртки фреймворка, D70 п.6) в `CoreX\Tenancy\Impersonation\Contracts`, `#[Spi(Implement)]`:
   `ImpersonationGrantStore` — `claim(GrantClaim $claim): ?ClaimedGrant` (атомарный однократный захват по правилам профиля) и `claimedReason(string $grantId): ?string`;
   `ImpersonationAuditSink` — `record(ImpersonationAuditEvent $event): void`.
   Штатные реализации: `DatabaseImpersonationGrantStore` (central через `ConnectionResolverInterface`), `DualImpersonationAuditSink` (строка `root_account_events` в central, затем зеркало в tenant через `CoreX\Audit\Contracts\AuditLogger`).
2. Остальные зависимости — контракты фреймворка и ядра: `Illuminate\Contracts\Session\Session` (из текущего запроса в scoped-фабрике), `Illuminate\Contracts\Auth\Factory`, `Illuminate\Contracts\Events\Dispatcher`, `Psr\Clock\ClockInterface`, `CoreX\Context\Contracts\TenantContextResolver` вместо `tenancy()->tenant`. В `packages/tenancy/src/Impersonation/**` нет фасадов, helper-функций и static-состояния; строки Impersonation удаляются из `scripts/runtime-globals-baseline.json` (D70 п.9: baseline обнуляется).
3. **Write-guard**: `ImpersonatedModelWriteGuard` — scoped, `allowing()` — метод экземпляра; слушатели `eloquent.*` разрешают guard из контейнера при каждом событии. Текст `ImpersonationRestrictedException` ссылается на экземплярный `allowing()`.
4. **Профили** `CoreX\Tenancy\Impersonation\Enums\ImpersonationProfile` = `dev|standard|regulated`, ключ `corex-tenancy.impersonation.profile`, default `standard`; заменяет `require_approved_by` без моста (D01).

   | Правило | dev | standard | regulated |
   |:--|:--|:--|:--|
   | `approved_by` | не требуется | не требуется | обязателен и ≠ `staff_identity_id` |
   | максимум `expires_at − created_at` | 480 мин | 60 мин | 30 мин |
   | зеркало аудита в tenant | нет | да | да |
   | сбой записи аудита при start | отказ | отказ | отказ |
   | запись моделей под impersonation | запрещена (L1–L3) | запрещена | запрещена |

   Правила профиля проверяются в WHERE атомарного claim (без изменения схемы). `dev` входит в закрытый список опасных режимов D69 п.7: в `production` без `corex.acknowledged_dangerous_modes` содержащего `corex-tenancy.impersonation.profile` → `InvalidConfiguration` (`RuntimeConfigCheck` пакета tenancy).
5. Порядок `start`: claim → аудит `impersonation.started` (central, затем tenant-зеркало; любой сбой → отказ, сессия не открыта, claim остаётся использованным) → session regenerate + ключи → login. Сбой login → очистка сессии + `impersonation.failed`. `stop`: очистка сессии → аудит `impersonation.ended`. Каждое событие аудита содержит grant id, staff id, account id, target user id, reason, профиль, исход и `expires_at` (`pplx-03`).

### B. Access без AzGuard (P07.4)

6. `corex-access` не зависит от AzGuard (D14): `AccessChecker::allowed()` вычисляется собственными данными — активное назначение роли принципала в текущем workspace с ненулевым scope в `aut_role_matrix` для `(entity, action)` **или** активный прямой grant в новой tenant-таблице `aut_direct_grants` (`id uuid`, `principal_type`, `principal_id uuid`, `permission_key`, `expires_at nullable`, `revoked_at nullable`, timestamps; индекс по `(principal_type, principal_id, permission_key)`). `RoleMatrixCompiler::compile()` валидирует матрицу и повышает `ScopeMapVersion`, затем диспатчит `RoleMatrixCompiled(roleId)`. `PrincipalIdentity` не реализует AzGuard-контракты. Колонка `aut_roles.azguard_role_id` удаляется из миграции access (D01, правка на месте; baseline — P08).
7. **Мост** `packages/access-azguard` — `axiomasoft/corex-access-azguard`, `CoreX\AccessAzguard\`, тип `bridge`; требует `axiomasoft/corex-access` и `axioma-studio/azguard-core ^0.3`. Переносит в мост: `TenantContextGuard` (AzGuard `ContextGuard`), `WorkspacePermissionLayer`, `AutMatrixGrantSource`, `MatrixPermissionCatalogBuilder`, `AzGuardRoleModel`, синхронизацию `az_guard_role_permissions` по `RoleMatrixCompiled`, связь ролей в таблице моста `aut_azguard_role_links (role_id uuid pk, azguard_role_id bigint)`, AzGuard config pins и Blade-директивы. `extra.corex.stability = "unreleased"`: `scripts/manifest-check.py` принимает это значение только для закрытого списка `UNRELEASED = {"axiomasoft/corex-access-azguard"}`; релизный набор P09 его исключает до публикации стабильного AzGuard (owner-gate Q2). Строка в glossary §Packages.

### C. Рёбра модулей (P07.5)

8. Новый модуль ядра `CoreX\Scheduling` (glossary §Kernel modules) публикует контракты отложенного пробуждения: `Contracts\{WakeupScheduler, DueWakeupSource, DueWakeupHandler}`, `Data\{WakeupRequest, WakeupWindow, WakeupDelivery, WakeupHandlerDefinition, ScheduleResult, CancelResult, SweepResult}`. Пакет `corex-wakeup` их реализует и сохраняет свои `DueWakeupSweeper`, `WakeupScheduleHint`. Перенос — без алиасов (D01).
9. Обработчики пакетов регистрируются тегом `WakeupHandlerDefinition::TAG` (`corex.scheduling.wakeup-handlers`) на биндинге, возвращающем `WakeupHandlerDefinition`; источники — тегом `DueWakeupSource::TAG` (`corex.scheduling.due-sources`). `corex-wakeup` объединяет тегированные определения с `corex-wakeup.handlers` хоста; повтор `kind` → `InvalidConfiguration` при boot. Запись чужого config-ключа (`corex-wakeup.handlers` из notifications) запрещена.
10. `corex-notifications` требует только ядро; digest при `digest_enabled = true` и отсутствии биндинга `WakeupScheduler` → `InvalidConfiguration` (RuntimeConfigCheck notifications), а не тихое отключение. `corex-tasks-workflow` теряет `suggest` на wakeup. `tools → operations` и `webhooks → operations` остаются жёсткими рёбрами: оба пакета регистрируют свои операции в движке operations и без него бессмысленны (D16).
11. `scripts/package-graph-gate.php` в целевом графе: ребро `notifications → wakeup` запрещено; негативный контроль — внесённый импорт `CoreX\Wakeup\` в notifications красит гейт.

## Why

A: `pplx-03` — just-in-time grant, short TTL, two correlated audit views, fail-closed; D70 п.9 требует обнулить baseline. B: D14 и AzGuard не опубликован (`http-01`, 404). C: D16 и `research/04` §4 («notifications ↛ wakeup, контракт в ядре»); запись чужого config-ключа — скрытое ребро.

## Consequences

P07.3 — A; P07.4 — B; P07.5 — C. Golden/BC: новые порты и enum (Spi/Api), перенос symbols wakeup в `CoreX\Scheduling`, удаление AzGuard-типов из manifest access, новый пакет — reviewed semantic delta (D64). P08 включает `aut_direct_grants` и `aut_azguard_role_links` в baseline и удаляет `root_accounts.data`.
