# Аудит архитектуры CoreX — чистая архитектура, независимость пакетов, вынос tenancy

- **Дата:** 2026-10-17
- **Дерево:** ветка `chore/skm-upgrade`, `74f1df78`, рабочее дерево чистое
- **Метод:** статический анализ — `use`-импорты (python-подсчёт), `composer.json` всех пакетов, миграции, `scripts/package-graph-gate.json`, решения PLAN3 (`D13`, `D25`, `D26`, `D29`, `D32`, `D95`, `D112`), `docs/dev-doctrine.md`.
- **Актуальность:** аудит снят с `74f1df78` (база `main` = `40691de8`). На момент коммита `origin/main` ушёл на 47 коммитов вперёд (`586012eb`: закрытие PLAN3, «resolve audit findings», hardening ядра; 148 изменённых файлов в `core/src/{Context,Storage,Messaging/Events,Database}` и `tenancy/src`). Находки требуют перепроверки на текущем `main` перед планированием.
- **Ограничение:** гейты и тесты НЕ запускались — в дереве нет `vendor/`, системный PHP 8.3 (политика — 8.4 через `scripts/php84`). Все выводы — по исходникам; счётчики по `use` не учитывают inline-FQCN и строковые имена классов.

---

## 0. Резюме

| # | Вывод | Тяжесть |
|---|---|---|
| 1 | Цель «tenancy отдельно, corex — надстройка» противоречит принятым D26/D29 (tenancy-SPI сознательно в ядре). По доктрине §8 нужен новый D#, отменяющий D26. | блокер процесса |
| 2 | `axiomasoft/corex-tenancy` существует, но не независим: 438 `use`-импортов из ядра (Attributes 204, Context 110, Errors 59, Modules 18, Storage 13, Config 12, Messaging 10, Audit 6, Database 4, Data 2). | P0 |
| 3 | Ядро содержит 9 tenancy-контрактов (DB-per-account, provisioning, pg_dump/DROP, IdP-пароли, impersonation), большинство с единственным потребителем — tenancy. | P0 |
| 4 | Продуктовая семантика в L0: `ProvisionParams{embeddingModel, embeddingDim, verticalCode, locale='ru'}`, `AccountRef{features, limits}` (биллинг) — тест агностичности не пройден. | P0 |
| 5 | `corex-tenancy` — монолит из ~5 bounded contexts (движок, control plane, OIDC IdP + impersonation, membership/workspaces, domain trust), владеет таблицами приложения `users`/`sessions`. | P1 |
| 6 | Межпакетные FK и ALTER чужих таблиц (tenancy → `sys_departments`, `users`); `tasks` жёстко ссылается на `users`, access — через конфиг. | P1 |
| 7 | Пакеты не тестируемы автономно: все package-скрипты гоняют `../../vendor/bin/pest`; у search/transfer/wakeup/workflow/recurrence нет своих dev-инструментов. | P1 |
| 8 | Dev-tooling в runtime `src` ядра: PHPStan-правила (41 импорт `PHPStan\*`), `SurfaceScanner` на nikic/php-parser (50 импортов) — пакеты только в `require-dev`/`suggest`. | P1 |
| 9 | Четыре пересекающихся механизма «что включено»: `sys_feature_flags`, `root_feature_flags`, `AccountRef.features` (везде `[]`), editions + `ModuleActivationGate`. | P2 |
| 10 | Момент удачный: 1.0 не выпущен, по D17 breaking до 1.0 — в minor; P08 (D112/D113) и так переписывает схему. | возможность |

---

## 1. Tenancy внутри ядра (`packages/core/src/Context`, `Storage`)

### 1.1 Инвентарь и потребители

| Символ ядра | Потребители вне core (файлы) | Рекомендация |
|---|---|---|
| `Contracts\AccountConnectionResolver`, `Data\DatabaseConnectionConfig` | tenancy 5/6, flex-crm 1 | → движок tenancy; flex-crm явно требует tenancy |
| `Contracts\TenantDatabaseProvisioner`, `Data\ProvisionParams` | только tenancy | → tenancy |
| `Contracts\TenantDatabaseLifecycle` (pg_dump `-Fc`, S3, DROP) | только tenancy | → tenancy |
| `Contracts\IdentityPasswordRehasher` | access вызывает (`PrincipalUserProvider.php:117`), tenancy реализует | → порт владельца в access (или в identity-пакет) |
| `Contracts\TenancyManager` + `Internal\NullTenancyManager` | access 3, access-azguard 2, operations 3, tenancy 19 | → tenancy; читателям — resolver (см. 1.4) |
| `Contracts\ImpersonationService` (start/stop/active/state) + `NullImpersonationService` | core Audit (8), tenancy (7) | ядру — только read-порт `ImpersonationStateReader`; start/stop → identity |
| `Contracts\ImpersonationGuard`, `Enums\ImpersonationRestrictedAction` | core, tenancy | оставить в ядре поверх read-порта |
| `Data\TenantContext`, `AccountRef`, `WorkspaceRef` | все пакеты | оставить как нейтральный **Scope**, урезать (1.2) |
| `Contracts\TenantContextResolver`, `TenantContextRestorer`, `SingleAccountResolver`, `GuardedTenantContextRestorer` | 6 пакетов | оставить; переименовать в `Scope*` |
| `Storage\Contracts\TenantStorage` | media 4, transfer 1, tenancy 3 | оставить, переименовать в `ScopedStorage` |

### 1.2 Продуктовая и биллинговая семантика в L0

- `packages/core/src/Context/Data/ProvisionParams.php:20-24` — `embeddingModel`, `embeddingDim` (AI-эмбеддинги), `verticalCode` (вертикаль продукта), `locale = 'ru'`. Нарушает доктрину §1 («`corex/*` полезен проекту без CRM/CMS/Store»; AI-gateway/биллинг/метеринг → standalone).
- `Context/Data/AccountRef.php` — `features: array`, `limits: array` (тариф). При этом **все** построения `AccountRef` в tenancy передают `features: []`, `limits: []` (`SqlAccountRead.php:26`, `AccountLifecycle.php:100,361`, `MigrationWaveOrchestrator.php:125`, `RootWakeupReconciler.php:113,194`) — поля мёртвые, но входят в публичный `#[Api]`.
- `AccountRef.status` (строка `'active'`) проверяется в `flex-crm/src/Context/ContextGuard.php` — lifecycle-семантика аккаунта протекла в контракт ядра.

### 1.3 Tenant-контекст прошит в базовые типы ядра

- `Messaging/Events/DomainEvent.php` — публичное `TenantContext $tenant` в КАЖДОМ событии; дефолт берётся service-locator'ом `Container::getInstance()` из конструктора value-объекта (`Messaging/Internal/DomainEventDefaults.php`). Событие зависит от контейнера; каждое несёт `AccountRef` с `features/limits`.
- `Modules\Contracts\ModuleRegistry::active(TenantContext)`, `isActive(string, TenantContext)`, `ModuleActivationGate::check(TenantContext, …)`, `ModuleStateReader::enabledFor(TenantContext)`, `Modules\Data\ModuleRuntimeContext::$tenant`.
- `Scheduling\Data\WakeupRequest::$context`, `Data\Data\DataScope::fromTenantContext()`, `Audit\Data\AuditEntry::withImpersonation()`.
- Внутриядерная связность на `Context`: Modules 20, Audit 6, Config 9, Storage 4, Messaging 3, Scheduling 3, Data 1.

**Вывод:** ядру нужен не «tenancy», а нейтральный *scope* (`accountId`, `workspaceId?`, `boxed`). Он почти есть — `DataScope` состоит только из скаляров.

### 1.4 Нарушение ISP

- Изменяющий `TenancyManager` (`initialize/setWorkspace/end/runFor`) инжектится ради одного `->context()`:
  `access/src/Access/Internal/DefaultAccessChecker.php:43,141,204`, `access/src/Access/Internal/ScopePredicate.php:41,168,200`, `access-azguard/src/Internal/WorkspacePermissionLayer.php:25,37`, `access-azguard/src/Internal/AutMatrixGrantSource.php:38,60`.
- Два read-порта с разной nullability: `TenancyManager::context(): ?TenantContext` vs `TenantContextResolver::current(): TenantContext`. Потребитель не знает, какой канонический.
- `operations/src/Invocation/Internal/TrustedContextResolver.php:37` использует `initialized()` и сравнение `context() != $tenant` — это проверка «физической» tenancy; ей место в bridge-пакете, не в operations.

### 1.5 Два механизма override одной привязки

- Конфиг: `corex.tenant_context.{resolver,manager,impersonation}` → `ConfiguredImplementation::resolve()` (`core/src/Context/Laravel/ContextRegistrar.php:30-38`).
- Контейнерный ребайнд: `tenancy/src/Laravel/RuntimeRegistrar.php:119-147` (`TenancyManager`, `TenantContextResolver`, `TenantStorage`), `AuthenticationRegistrar.php:143` (`ImpersonationService`).
- Итог зависит от порядка провайдеров (package discovery), конфиг-значение молча игнорируется при установленной tenancy. Выбрать один механизм (рекомендация — контейнер; конфиг-ключи удалить в духе D112).

### 1.6 Конфиг ядра знает о tenancy

`packages/core/config/corex.php:52-74` и `:195-205` — комментарии и ключи про `axiomasoft/corex-tenancy`, DB-per-account, «P2», «D13». Ядро документирует поведение конкретного внешнего пакета.

---

## 2. `corex-tenancy` — монолит

| Подсистема (`src/…`) | LOC | Таблицы | Целевое место |
|---|---|---|---|
| `Runtime` + `Provisioning` + DB lifecycle | ~2.8k | `root_clusters`, `root_templates`, `root_account_schema_state`, `root_migration_batches`, `root_account_exports` | **движок tenancy** |
| `Account` (control plane) | 1.5k | `root_accounts`, `root_account_products`, `root_usage_snapshots`, `root_feature_flags`, `root_account_events` | движок или `tenancy-control-plane` |
| `DomainTrust` | 0.7k | `root_domains` | движок (host-идентификация) |
| `Authentication` (OIDC) + `Identity` + `Impersonation` | 2.2k | `root_identities`, `root_identity_accounts`, `root_oauth_clients`, `root_oauth_refresh_tokens`, `root_idp_sessions`, `root_impersonation_grants`, tenant `users`, `sessions` | **identity-пакет** |
| `Membership` + `Workspace` | 3.3k | `wsp_workspaces`, `wsp_workspace_members`, `wsp_invitations`, membership adapter | **workspaces-пакет** |
| `Laravel` (регистраторы, options, http, commands) | 2.8k | — | распределить по пакетам |
| `Integration/Wakeup` | 0.4k | `root_wakeup_hints`, `root_wakeup_reconciliation_state` | bridge |

Дополнительно:
- Раскладка контрактов непоследовательна: DomainTrust/Provisioning-контракты в общем `src/Contracts/` (8 файлов), остальные — в `<Sub>/Contracts`.
- Tenancy публикует миграцию `users` (с колонкой ядра `department_id`, `create_users_table.php:27`) и `sessions` — конфликт с собственной миграцией хоста; пакет присваивает таблицы приложения.
- Опциональная зависимость через `use` + `interface_exists`: tenancy → wakeup (`TenancyServiceProvider.php:26-27,111-112`, `Integration/Internal/Wakeup/*`) — wakeup лишь в `require-dev`/`suggest`; узаконено allowlist'ом `package-graph-gate.json`.
- Публичная поверхность: 130 из 214 классов помечены `#[Api]`/`#[Spi]` (61%) — для движка SaaS-инфраструктуры слишком широкая SemVer-обязанность.

---

## 3. Целевая архитектура: tenancy standalone, corex — надстройка

### 3.1 Рекомендуемый вариант — порты/адаптеры без общего контракт-пакета

```
                 ┌───────────────────────────────┐
                 │ axiomasoft/corex (L0)          │
                 │  Context\ScopeResolver          │  Scope{accountId, workspaceId?, boxed}
                 │  Context\ScopeRestorer          │  SingleScopeResolver (boxed default)
                 │  Context\ImpersonationStateReader│ (read-only)
                 │  Storage\ScopedStorage          │
                 └──────────────▲────────────────┘
                                │ implements
┌───────────────────────┐  ┌────┴──────────────────────┐  ┌────────────────────────┐
│ axiomasoft/tenancy    │◄─┤ axiomasoft/corex-tenancy  │─►│ corex-identity         │
│ standalone Laravel,   │  │ НАДСТРОЙКА (bridge)       │  │ OIDC, identities,      │
│ НЕ требует corex      │  │ Scope ← Tenant            │  │ impersonation, users   │
│ TenancyManager        │  │ ProblemDefinitions/codes  │  ├────────────────────────┤
│ TenantResolver        │  │ ConfigReportSection       │  │ corex-workspaces       │
│ Bootstrappers         │  │ Audit/DomainEvent mapping │  │ membership, wsp_*      │
│ ConnectionResolver    │  │ ScopedStorage(S3)         │  └────────────────────────┘
│ Provisioner/Lifecycle │  │ ModuleStateReader per acc │
│ host identification   │  │ wakeup integration        │
│ root_* control plane  │  │ physical-tenancy guard    │
└───────────────────────┘  └───────────────────────────┘
```

- Ядро ничего не знает о tenancy; tenancy ничего не знает о corex; связь — только в bridge (паттерн уже закреплён доктриной §5 на `search-intl`).
- Цена: маленький дублирующийся VO + маппер в bridge.
- **Альтернатива:** `axiomasoft/tenancy-contracts` (zero-dep), который требуют и core, и tenancy. Миграция проще, но ядро остаётся tenant-aware по именам.

### 3.2 Что отвязать в tenancy для standalone

| Импорт из corex | `use` | Замена |
|---|---|---|
| `Attributes\Api`, `Spi`, `SpiKind` | 204 | zero-dep пакет `axiomasoft/api-attributes` (его же требует core) или `@api` phpdoc |
| `Context\*` | 110 | собственные `Tenant`/`AccountRef`/`WorkspaceRef`; порты переезжают в tenancy |
| `Errors\CodedException`, `ErrorDescriptor`, `ErrorCategory`, `DescribesError`, `ProblemDefinitions` | 59 | собственные исключения; маппинг в problem-коды — bridge |
| `Modules\Contracts\PackageRegistrar`, `RegistersNothingOnBoot` | 18 | обычные `ServiceProvider`/приватные регистраторы |
| `Storage\*` | 13 | S3-реализация `ScopedStorage` → bridge |
| `Config\RuntimeConfigCheck`, `ConfigReportSection`, `InvalidConfiguration`, `SecretRef`, `CoreOptions` | 12 | bridge / собственное исключение |
| `Messaging\DomainEvent`, `PubSubDriver` | 10 | обычные события; bridge оборачивает в `DomainEvent` при необходимости |
| `Audit\*` | 6 | bridge |
| `Database\HasConfiguredId`, `BaseModel` | 4 | удаляются по D112 |
| `Data\JsonDocument`, `DataScope` | 2 | собственные/bridge |
| `Wakeup\*` (опционально) | 4 | bridge; опциональная зависимость исчезает |

### 3.3 Поэтапный план (каждый шаг — отдельный зелёный коммит)

| # | Шаг | BC | Размер |
|---|---|---|---|
| 0 | Новый D#, отменяющий D26 (и Departments-часть D29); обновить `dev-doctrine.md` §1/§5 | — | S |
| 1 | ISP: access/azguard → `TenantContextResolver`; единый read-порт с явной nullability | нет | S |
| 2 | Перенести в tenancy: `AccountConnectionResolver`, `TenantDatabaseProvisioner`, `TenantDatabaseLifecycle`, `ProvisionParams`, `DatabaseConnectionConfig`, `TenancyManager`, `NullTenancyManager`; flex-crm `require` tenancy | breaking (до 1.0 ок) | M |
| 3 | `IdentityPasswordRehasher` → access; `ImpersonationService` → identity; в ядре — `ImpersonationStateReader` | breaking | M |
| 4 | Урезать `TenantContext`/`AccountRef` до scope; удалить `features/limits/status`; `ProvisionParams` без embedding/vertical/locale (продукт передаёт свои параметры через extension-point движка) | breaking | M |
| 5 | Один механизм override (контейнер); удалить `corex.tenant_context.*` | breaking (config) | S |
| 6 | `DomainEvent`: убрать service locator; scope ставит диспетчер/outbox (envelope-метаданные) | breaking | M–L |
| 7 | Создать bridge `corex-tenancy`; движок → `axiomasoft/tenancy`, снять `require axiomasoft/corex`; гейт R6: forbidden edge `tenancy → corex` | новый пакет | L |
| 8 | Выделить `corex-identity` (Authentication/Identity/Impersonation + `users`/`sessions`, имя таблицы — порт) и `corex-workspaces` (Membership/Workspace) | новые пакеты | L |
| 9 | Схема: удалить `add_cross_package_fks_table`; FK кладёт пакет, явно зависящий от обоих владельцев (bridge) — вписать в P08 baseline | миграции | M |
| 10 | Гейты: `LayerTest` — «нет `Tenant*`/`Provision*`/`Impersonation*Service` в `core/src`»; package-graph — forbidden edges | — | S |

---

## 4. Независимость пакетов (вне tenancy)

### 4.1 Граф зависимостей (src, `use`-импорты)

```
core ◄── все
operations ◄── tools (55), webhooks (43)
operations ··► transfer (11, опционально, interface_exists)
search ◄── search-intl, search-meilisearch;  intl ◄── search-intl
tasks, workflow, recurrence ◄── tasks-workflow
access ◄── access-azguard
tenancy ··► wakeup (4, опционально)
flex-crm ──► core, intl, operations  (+ неявно tenancy через AccountConnectionResolver)
```

- Импортов чужого `Internal` — **0** (гейт R2 работает).
- Dev-цикл: operations `require-dev` webhooks (тест `packages/operations/tests` импортирует `CoreX\Webhooks`), а webhooks `require` operations.

### 4.2 Опциональные зависимости через `use` + `interface_exists`

- operations → transfer: `Bulk/Data/BulkTransferSession.php` (публичный `#[Api]`-тип ссылается на необязательный пакет), `Bulk/Internal/TransferBulkExecutor.php:81-82`, `Bulk/Laravel/BulkBindingsRegistrar.php:71`.
- tenancy → wakeup (см. §2).
- Рекомендация: bridge-пакеты `corex-operations-transfer` (Bulk-over-Transfer) и интеграция wakeup в `corex-tenancy`. Это убирает allowlist-исключения из `package-graph-gate.json` и делает граф честным.

### 4.3 Composer-манифесты

| Проблема | Где |
|---|---|
| `laravel/framework` целиком + дублирующие `illuminate/*` | core; `laravel/framework` целиком также в media, notifications, tasks, recurrence, tenancy, flex-crm — остальные на `illuminate/*` |
| Используется, но не объявлено (по `use`) | access: `illuminate/cache`, `illuminate/container`; access-azguard: `illuminate/view`; panels: `illuminate/database`; search-intl: `illuminate/contracts`; transfer: `illuminate/contracts` (только dev); tools: `opis/json-schema` (dev/suggest); tenancy: `aws/aws-sdk-php` (за `class_exists`) |
| Runtime-импорты из dev/suggest | core: `phpstan/phpstan` 41, `nikic/php-parser` 50, `open-telemetry/api` 3 |
| Объявлено, но не найдено в `use` (кандидаты, проверить inline-FQCN) | tools: `illuminate/{auth,http,database,validation,console}`; webhooks: `illuminate/{auth,validation}` |
| Нет собственных dev-инструментов | recurrence (пустой `require-dev`, нет `psr/clock`), search, transfer, wakeup, workflow (нет pest/larastan/pint) |
| Мусор | custom-fields `suggest: axiomasoft/corex` (уже в require); `repositories: path ../*` во всех пакетах (для non-root игнорируется, раскрывает раскладку монорепо); access ссылается на `../tenancy` |
| Тип пакета vs доктрина §4 («модуль = `corex-module` + `ModuleDefinition`») | `corex-module` без `extra.corex.module`: custom-fields, search, search-intl, search-meilisearch, transfer; tasks-workflow указывает на `Internal\TaskWorkflowIntegration`; access/operations/tenancy/webhooks/tools/recurrence — `library` |

### 4.4 Тестовые харнессы

- Все package-скрипты: `../../vendor/bin/pest --bootstrap=../../vendor/autoload.php` (core даже `--configuration=../../phpunit.xml`). После split-зеркала (ADR-002) ни один пакет не тестируется. Доктрина §2 («самостоятельные пакетные харнессы») не соответствует дереву.
- Тесты ядра разнесены: `packages/core/tests` + корневой `tests/Feature` (Audit, Department, FeatureFlags, Settings, ModuleDiscovery/Lifecycle/Registry, PubSub, RegistryCompiler, TenantContext, BoxedTenancy…) — при split корневые не поедут с пакетом.
- Корневой `autoload-dev` содержит classmap/psr-4 тестов 22 пакетов → тесты одного пакета видят фикстуры всех остальных; скрытые зависимости не ловятся.
- Соотношение test/src файлов низкое: tasks 11/87, recurrence 4/20, workflow 5/18, panels 10/32, media 10/41.
- Рекомендация: в каждом пакете собственный `require-dev` (pest, testbench, larastan, pint), `vendor/bin/*` локально, CI-матрица `composer install` в каталоге пакета (изоляционный install-тест из доктрины §7).

---

## 5. Схема и владение данными

- **Чужие ALTER/FK:** `tenancy/database/migrations/tenant/2026_07_26_190004_add_cross_package_fks_table.php` — `users.department_id → sys_departments`, `sys_departments.workspace_id → wsp_workspaces`, `sys_departments.head_user_id → users`, `sys_department_user.user_id → users`. Tenancy изменяет таблицы ядра; ядро (Departments) неявно зависит от `wsp_workspaces` tenancy. Аналогично `access/database/migrations/tenant-cloud/…_add_aut_cross_package_fks.php`.
- **Таблица `users`:** создаёт tenancy; `tasks` ставит `constrained('users')` в своей миграции (`2026_09_11_000260_align_tasks_with_approved_schema.php:87,130,131,168,176,187`) и `table('users')` в коде (`CreateTask.php:88`, `UpdateTask.php:89`, `PlanningCommand.php:344`) без зависимости от tenancy; access делает имя таблицы конфигурируемым (`corex-access.identity.table`), но `ScopePredicate.php:86-140` всё равно хардкодит `users.department_id`. Нужен единый порт «identity table/lookup».
- **Runtime-интроспекция схемы на горячем пути:** `ScopePredicate` вызывает `hasTable('users')`/`hasTable(departments)` при каждой проверке.
- **Префиксы таблиц непоследовательны:** `sys_` используют core, custom-fields (`sys_attribute_*`), operations (`sys_api_idempotency`), wakeup, workflow — «system» перестаёт значить «ядро»; `corex_` — intl, search, transfer, tasks-workflow и tasks (`corex_task_operations` рядом с `tsk_*`); короткие `med_/ntf_/tsk_/aut_/mod_/root_/wsp_`. Вписать единое правило «префикс = владелец» в P08 baseline (D113).
- **D112 ещё не исполнен:** `HasConfiguredId`, `IdColumns`, `IdStrategy` живы, flex-crm использует их через `app(CoreOptions::class)->idStrategy` (`CrmIdentifier.php:25`, `ContextGuard.php:111,116`, `ConvertLead.php:92`).

---

## 6. Чистота ядра (`axiomasoft/corex`, 328 файлов, 21k LOC)

### 6.1 Dev-tooling в runtime
- `Attributes/Tooling/SurfaceScanner.php` (1116 LOC, nikic/php-parser), `Errors/Tooling/ErrorSourceScanner.php`, `Modules/PHPStan/Rules/*` (10 правил), `Messaging/PHPStan/*`, `extension.neon`. Пакеты в `require-dev`/`suggest` → `src` ядра не загружаем без dev-зависимостей.
- Рекомендация: `axiomasoft/corex-devtools` (PHPStan-расширение + сканеры). `Testing`-неймспейсы (`Modules/Testing`, `Messaging/Testing`, `Time/Testing`, `operations/src/Testing`) — кандидаты в `corex-testing`.

### 6.2 Departments в L0
- Оргструктура (`sys_departments`, `sys_department_user`, `users.department_id`, `SettingScope::Department`) — предметный домен, не платформа. Единственный внешний потребитель — `access/.../ScopePredicate.php` (через статический `DepartmentQueries`, не через контракт). D29 оставил в ядре из-за вплетения в Settings scope.
- Рекомендация: `corex-departments` (или в access); `SettingScope` сделать расширяемым (строковый scope + регистрация), а не enum с доменными значениями.

### 6.3 Четыре механизма «что включено»
1. `core/Features` — `sys_feature_flags` + `sys_feature_overrides` (tenant DB); вне ядра **0 потребителей** `FeatureFlags`.
2. `tenancy` — `root_feature_flags` (central, per-account).
3. `AccountRef.features` — всегда `[]`.
4. Module editions + `ModuleActivationGate` (billing/edition gate).
Нужен один источник истины (entitlements-порт в движке tenancy → bridge отдаёт в `ModuleActivationGate`/`FeatureFlags`).

### 6.4 Extender-система
- `RegistryCompiler.php` (1135 LOC, 49 методов) содержит `collect*/filter*/normalize*` для каждого из 8 extenders — нарушение OCP: каждый extender диктует правку компилятора. Уже есть SPI `ManifestSectionCompiler` (D32) только для `api`/`tools` — распространить на все секции (стратегия на extender).
- `Filament` в закрытом наборе extenders L0: 5 файлов ядра, **ни один пакет не использует**. Конкретная UI-библиотека в агностичном ядре — кандидат на удаление/вынос в `flex/*` (требует RFC по доктрине §4).
- `DatabaseModuleLifecycle.php` — 842 LOC, 41 метод; `ReadRegistryIntrospection.php` — 465 LOC.

### 6.5 Прочее
- `DomainEvent` service locator (см. 1.3).
- Докблоки публичных контрактов ссылаются на внутренние ID планов и vault (`B-11 §5.9`, `D139`, `P2.5`, `AC-31`, `PLAN3.D95`, `DB_SCHEMA.md §3.1`), смешение RU/EN — для внешнего потребителя нечитаемо; rationale → ADR, в докблоке — поведение.
- Публичная поверхность ядра: 233 из 328 классов `#[Api]`/`#[Spi]` (71%); operations 115/172 (67%).

---

## 7. Кандидаты на рефакторинг (по размеру/ответственностям)

| Файл | LOC | Проблема |
|---|---|---|
| `core/src/Modules/Internal/Compiler/RegistryCompiler.php` | 1135 | God-class, OCP (6.4) |
| `core/src/Attributes/Tooling/SurfaceScanner.php` | 1116 | dev-tooling в runtime |
| `core/src/Modules/Internal/Lifecycle/DatabaseModuleLifecycle.php` | 842 | FSM + persistence + hooks в одном |
| `tenancy/src/Laravel/Support/TenancyOptions.php` | 528 | один options-класс на 5 подсистем → по пакету |
| `operations/src/Bulk/Internal/SqlBulkOperationStore.php` | 514 | |
| `custom-fields/src/Internal/Values/AttributeBackfill.php` | 471 | |
| `recurrence/src/Internal/RRuleRecurrenceExpander.php` | 471 | |
| `core/src/Modules/Internal/ReadRegistryIntrospection.php` | 465 | |
| `tasks/src/Planning/Laravel/PlanningCommand.php` | 437 | бизнес-логика + SQL в artisan-команде; хардкод `users` |
| `core/src/Modules/Data/Api.php` | 401 | DTO на 400 строк |
| `tenancy/src/Account/Internal/Lifecycle/AccountLifecycle.php` | 393 | |

**operations (11.5k LOC)** — «фреймворк во фреймворке»: Bulk 2.1k, Cost 1.4k (бюджеты/троттлинг = метеринг → по доктрине §1 standalone), Idempotency 1.0k, Definition 1.0k, Invocation 0.8k, Pagination 0.7k, OpenApi 0.7k, Validation 0.7k, Versioning 0.6k. Кандидаты на выделение: `operations-bulk` (+transfer bridge), `operations-cost`.

**flex-crm**: 11 `app()` в моделях/скоупах/actions (`HasCrmContext.php:16,24,33`, `CrmScope.php:22`, `ContactCompany.php:35,47`, `RecordRules.php:28`) — service location в Eloquent-хуках; `ContextGuard` проверяет PDO/`current_database()` на каждый вызов.

---

## 8. Документация и процесс

- `docs/dev-doctrine.md` устарел: §1/§6 — пакеты `modules`, `auth` (слиты/переименованы, D25), правило flex-литералов для `packages/{core,modules,tenancy,auth,tasks}`; §2 — «агрегатор `test:packages` (цикл auth/crm/tasks/tenancy)», фактически цикл по 22 каталогам; §5 — «каталоги Contracts у core (13) и modules (10)». Доктрина §8 сама требует исправления при расхождении.
- Объём governance против кода: `scripts/` — 149 файлов, ~24k LOC гейтов; код пакетов — ~83k LOC; `plans/` — 8.7k tracked-файлов; `.git` pack 171 MiB. Много самописных source-scan гейтов (package-graph, naming, ownership×3, runtime-globals, config-composition, rename-completeness, provider-size, quality-ceilings…). Рекомендация: консолидировать правила графа/слоёв в один конфиг (PHPStan-правила или deptrac-подобный граф) и удалить пересекающиеся скрипты.
- CI: 28 джобов в `ci.yml`, отдельные джобы есть только у части пакетов (operations, tools, webhooks, notifications, panels, media, tenancy, crm, tasks, auth); остальные — через `packages-tests` из корневого vendor.

---

## 9. Сильные стороны (сохранить)

- Чужие `Internal` не импортируются (0 нарушений) — граница `Internal` реально держится.
- `app()`/`config()`/`DB::` в `src` почти отсутствуют (кроме core Database/id-стратегии и flex-crm) — DI-дисциплина высокая.
- Typed options (`CoreOptions`, `TenancyOptions`, `ApiOptions`), compiled registries, transactional outbox/inbox, null-object дефолты для boxed-режима.
- Маркировка поверхности `#[Api]/#[Spi]` + гейты BC, контракт-guard, mutation-check.
- Bridge-паттерн уже есть (`search-intl`, `search-meilisearch`, `tasks-workflow`, `access-azguard`) — целевую архитектуру можно строить по существующему образцу.
- Свой движок tenancy без stancl (D95) — выносить нечего «чужого», только своё.

---

## 10. Приоритизированный бэклог

| Приор. | Задача | Раздел |
|---|---|---|
| P0 | D#, отменяющий D26; целевая схема пакетов tenancy/identity/workspaces/bridge | 0, 3 |
| P0 | Вынести из ядра single-consumer tenancy-контракты (шаг 2–3) | 1.1 |
| P0 | Убрать продуктовую семантику из `ProvisionParams`/`AccountRef` | 1.2 |
| P1 | ISP: read-порт scope; один механизм override | 1.4, 1.5 |
| P1 | Standalone `axiomasoft/tenancy` + bridge `corex-tenancy` | 3.2 |
| P1 | Межпакетные FK, порт identity-таблицы, префиксы — в P08 baseline | 5 |
| P1 | Автономные харнессы пакетов + изоляционный install в CI | 4.4 |
| P1 | `corex-devtools`: PHPStan-правила и сканеры из runtime | 6.1 |
| P2 | Bridge-пакеты вместо `interface_exists`-опциональности | 4.2 |
| P2 | Composer-манифесты (`illuminate/*` вместо `laravel/framework`, недекларированные зависимости) | 4.3 |
| P2 | Единый entitlements-источник вместо 4 механизмов | 6.3 |
| P2 | Departments из ядра; расширяемый `SettingScope` | 6.2 |
| P2 | `DomainEvent` без service locator | 1.3 |
| P3 | `RegistryCompiler` → стратегии на extender; Filament-extender — RFC | 6.4 |
| P3 | Разделить operations (Bulk, Cost) | 7 |
| P3 | Актуализировать `dev-doctrine.md`; докблоки без plan-ID | 6.5, 8 |
| P3 | Консолидация гейтов | 8 |

---

## Приложение A. Импорты из ядра по пакетам (`use`, src)

```
access          Attributes 38, Config 3, Context 6, Database 3, Departments 1, Errors 8, Modules 21
access-azguard  access 14, Attributes 4, Config 1, Context 2, Modules 2
custom-fields   Attributes 34, Config 2, Context 2, Data 45, Errors 19, Messaging 12, Modules 11
intl            Attributes 70, Config 4, Data 23, Errors 26, Modules 6
media           Attributes 34, Config 3, Context 1, Modules 4, Storage 4
notifications   Attributes 46, Config 6, Context 5, Data 8, Errors 11, Modules 5, Scheduling 13, Settings 3
operations      Attributes 193, Config 13, Context 25, Data 46, Database 1, Errors 37, Modules 41, Time 1, transfer 11
panels          Attributes 35, Config 3, Context 2, Data 3, Modules 13
recurrence      Attributes 19, Errors 11
search          Attributes 35, Config 4, Data 16, Errors 8
search-intl     Attributes 5, Config 1, Data 3, intl 11, search 6
search-meili    Attributes 6, Config 4, Data 2, Errors 8, search 14
tasks           Attributes 78, Audit 2, Context 16, Data 6, Database 5, Errors 14, Modules 4
tasks-workflow  Attributes 12, Audit 1, Config 4, Context 6, Data 17, Errors 14, Modules 2, Scheduling 4, recurrence 12, tasks 24, workflow 3
tenancy         Attributes 204, Audit 6, Config 12, Context 110, Data 2, Database 4, Errors 59, Messaging 10, Modules 18, Storage 13, wakeup 4
tools           Attributes 18, Context 1, Data 6, Errors 5, Modules 13, operations 55
transfer        Attributes 38, Config 1, Data 17, Storage 1
wakeup          Attributes 12, Config 5, Context 8, Data 2, Modules 6, Scheduling 16, Time 2
webhooks        Attributes 34, Audit 4, Config 3, Context 11, Data 16, Errors 10, Messaging 13, Modules 2, operations 43
workflow        Attributes 17, Config 4, Context 5, Data 4, Modules 6
```

`Attributes` — самая массовая зависимость (≈1100 импортов): вынос `#[Api]/#[Spi]` в zero-dep пакет снимает «обязательное ядро» с любого пакета, которому от corex нужны только маркеры.

## Приложение B. Внутриядерные зависимости модулей

```
Modules  -> Attributes 132, Errors 52, Context 20, Messaging 5, Features 4, Config 4, Data 4, Audit 3, Settings 2
Config   -> Attributes 17, Context 9, Audit 6, Settings 6, Errors 4, Messaging 4, Departments 3, Features 3, Data 3, Database 2, Modules 1
Messaging-> Attributes 50, Errors 20, Config 7, Observability 4, Context 3, Modules 1, Time 1
Context  -> Attributes 30, Errors 12, Config 2, Data 2, Modules 1
Audit    -> Attributes 20, Context 6, Data 5, Config 4, Database 2, Modules 1
```

Циклы между модулями ядра: Config↔Audit/Settings/Features/Departments/Messaging/Context, Modules↔Errors/Context/Messaging, Data↔Context. `Config` знает все модули (центральный options-объект `CoreOptions`) — при выносе Departments/Features/tenancy он должен распасться на options per module.

## Приложение C. Воспроизведение

Скрипты подсчёта лежали во временном каталоге сессии; эквивалент — `grep -rh '^use CoreX\\' packages/<pkg>/src | sort | uniq -c`. Перед реализацией прогнать: `composer package-graph-gate`, `composer test:arch`, `composer deps:analyse` (не запускались в этом аудите — нет `vendor/`).
