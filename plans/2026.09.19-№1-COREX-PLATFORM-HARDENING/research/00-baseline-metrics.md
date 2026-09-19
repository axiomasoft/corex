# Измеренная база CoreX — 2026-09-19

Все числа получены чтением дерева на рабочей копии `main` (после `b4302c6`) в день создания
плана. `RAG:—` для всей страницы: это факты репозитория, а не внешние утверждения. Страница —
нормативный вход всех фаз: любое утверждение вида «пакет большой» или «конфигурация
глобальная» должно ссылаться сюда, а не на текст аудита.

## 1. Пакеты: объём, метаданные, контуры

| Пакет | composer `name` | PHP-файлов в `src` | `extra.corex` | Тесты | README | Миграций |
|:--|:--|--:|:--|:--|:--|--:|
| api | corex/api | 55 | нет | да | да | 0 |
| attributes | corex/attributes | 36 | нет | да | да | 1 |
| auth | corex/auth | 34 | нет | да | да | 4 |
| core | corex/core | 109 | нет | да | да | 11 |
| crm | flex/crm | 56 | да (`module`) | да | да | 10 |
| import-export | — (тумбстоун) | 0 | — | нет | нет | 0 |
| intl | corex/intl | 67 | да (`module`) | да | да | 2 |
| media | corex/media | 35 | да (`module`) | да | да | 4 |
| modules | corex/modules | 88 | нет | да | да | 4 |
| notifications | corex/notifications | 44 | да (`module`) | да | да | 6 |
| panels | corex/panels | 11 | да (`module`) | да | да | 0 |
| recurrence | corex/recurrence | 16 | нет | да | да | 0 |
| search | corex/search | 29 | нет | да | да | 1 |
| search-intl | corex/search-intl | 8 | нет | да | да | 0 |
| search-meilisearch | corex/search-meilisearch | 4 | нет | да | да | 0 |
| tasks | corex/tasks | 59 | да (`module`) | да | да | 8 |
| tasks-workflow | corex/tasks-workflow | 16 | да (`module`) | да | да | 1 |
| tenancy | corex/tenancy | 159 | нет | да | да | 30 |
| transfer | corex/transfer | 32 | нет | да | да | 2 |
| wakeup | corex/wakeup | 16 | да (`module`) | да | да | 1 |
| workflow | corex/workflow | 14 | да (`module`) | да | да | 1 |

Итого: 20 живых пакетов + 1 тумбстоун; **888** PHP-файлов в `src`; **86** файлов миграций.

> **Поправка 2026-09-19 (детализация P1).** Исходная редакция страницы давала «838 файлов»
> и «85 миграций». Оба числа — ошибки сложения, а не измерения: сумма самой таблицы выше
> даёт 888, а у `auth` четыре миграции (четвёртая — `tenant-cloud/2026_07_26_200004_add_aut_cross_package_fks.php`),
> а не три. Проверка: `find packages/*/src -name '*.php' | wc -l` = 888,
> `find packages/*/database/migrations -name '*.php' | wc -l` = 86. Разбор — `P1-design.md` §12.

**`extra.corex` есть в 9 пакетах и содержит только ключ `module`** (имя класса модуля).
Ни `package-type`, ни `stability`, ни `public-api` не объявлены нигде.

### Порог профиля структуры (по измеренным счётчикам)

- >100 файлов (профиль C, bounded-context-first): `tenancy` 159, `core` 109.
- 40–100 (профиль B, подсистемы возможности): `modules` 88, `intl` 67, `tasks` 59, `crm` 56, `api` 55, `notifications` 44.
- <40 (профиль A, компактный): `attributes` 36, `media` 35, `auth` 34, `transfer` 32, `search` 29, `recurrence` 16, `tasks-workflow` 16, `wakeup` 16, `workflow` 14, `panels` 11, `search-intl` 8, `search-meilisearch` 4.

## 2. Скрытые зависимости и глобальное состояние (`packages/*/src`)

| Паттерн | Всего | Горячие точки |
|:--|--:|:--|
| `config(` | 152 | tenancy 75 (из них `TenancyServiceProvider` 47), core 41 (из них `Support/Config` 31), auth 18, modules 11, media 6 |
| `app(` | 30 | core 8, crm 7, tenancy 6, auth 4, tasks-workflow 4 |
| `resolve(` | 19 | api 6, tenancy 4, notifications 3 |
| фасады `DB:: Auth:: Cache:: Event:: Log:: Storage::` | 159 | tenancy 105, auth 19, modules 13, core 11 |
| `now(` | 151 | tenancy 51, media 31, search 16, notifications 12 |
| `static $` | **0** | — (утверждение аудита о статиках не подтвердилось) |

`Internal/` — 19 каталогов, 188 файлов. Крупнейший: `tenancy/src/Membership/Internal` (39 файлов,
то есть весь Membership). `Support/` — 6 каталогов (attributes 15, search 15, core 9, tasks 5, crm 4).
`Data/` — 18 каталогов (intl 26, transfer 19, notifications 18).

## 3. Composition roots

| Provider | Строк | Байт | `singleton`/`scoped`/`bind` | `config(` | Декомпозиция |
|:--|--:|--:|:--|--:|:--|
| `tenancy/src/TenancyServiceProvider.php` | 524 | 27 513 | 23 / 5 / 2 | 47 | нет |
| `modules/src/ModulesServiceProvider.php` | 374 | 17 587 | 12 / 0 / 0 | 7 | нет |

Tenancy-провайдер также регистрирует 4–5 команд, 1 `loadRoutesFrom` (3 OIDC-маршрута),
до 5 middleware-алиасов и 7 `Event::listen` (в том числе три wildcard `eloquent.*`).
Ни в одном пакете нет типизированного объекта опций (`*Options`): 0 совпадений.

## 4. Durable-граница

`packages/core/src/Support/Data/DataScope.php` (32 строки):

```php
final readonly class DataScope
{
    public function __construct(
        public TenantContext $context,      // ← объект, не скаляр
        public string $connectionIdentity,
        public string $actorId,
        public string $restoreEpoch,
    ) {}
}
```

`TenantContext` несёт `AccountRef`, необязательный `WorkspaceRef` и приватный `bool $boxed`.
Производственная точка конструирования `DataScope` **одна**:
`packages/tasks-workflow/src/Internal/MaterializationWakeupHandler.php:38`. Во всех прочих
пакетах `DataScope` передаётся параметром. Это делает миграцию на скаляры дешёвой.

`DataScopeCodec` отсутствует. `TenantContextResolver` существует
(`core/src/Tenancy/Contracts/TenantContextResolver.php`), но его метод — `current(): TenantContext`,
то есть «текущий контекст процесса», а не восстановление из durable-скоупа. Прецедент codec'а
в дереве есть: `packages/search/src/Internal/Query/SearchCursorCodec.php`.

Инъектируемого Clock в `corex/core` нет; время берётся через `now()` (151 вызов).
Outbox/inbox не существует ни в одном пакете. Webhook-поверхности нет (только два комментария
в `modules/src/Contracts/{PurgeHook,DisableHook}.php`).

## 5. Внешний API (`packages/api`, 55 файлов)

Есть: реестр операций из compiled-манифестов модулей, dispatcher (match → principal → context →
authorize → validate → handler), конверт `{data, error, meta}` с `X-Request-ID`, маппинг
исключений в коды, генератор OpenAPI 3.1.0, каталог инструментов и MCP-проекция (2025-11-25),
seam'ы `ToolExecutionGuard`/`ToolPolicy`/`PrincipalResolver`/`ContextResolver`, декларативная
метка идемпотентности на tool-операциях, физическая tenancy обязательна по конфигу.

Нет: собственной регистрации маршрутов (монтирует приложение), внешнего версионирования кроме
`^v[1-9][0-9]*$`, режимов операции (query/command/async/bulk), HTTP-idempotency-store, курсорной
пагинации, бюджетов стоимости и throttling, committed snapshot'ов схемы, capability/discovery
эндпоинтов, bulk/LRO-хранилища, webhooks, GraphQL, отдельного correlation-id, enforcement
`onlyFields` в dispatcher (метаданные есть, проверки в диспетчере нет).

Единственный конфиг-ключ пакета: `require_physical_tenancy`.

## 6. Существующие гейты (что уже защищено)

- `tests/Arch/LayerTest.php` — 4 правила доктрины уровней (в том числе запрет flex-литералов).
- `tests/Arch/PackageGraphTest.php` + `scripts/package-graph-gate.php` — R1 незаявленная
  cross-package зависимость, R2 импорт чужого `Internal`, R3 владение неймспейсом; allowlist
  и legacy-исключения в `scripts/package-graph-gate.json`.
- 10 собственных PHPStan-правил (`modules/src/PHPStan/Rules/*`, `tenancy/src/PHPStan/Rules/StanclImportBoundaryRule`)
  с pin-тестами в `tests/PHPStan/`.
- `scripts/contract-guard.sh` — диф `src/Contracts/**` требует записи в CHANGELOG пакета
  (warn-прокси, реального BC-анализа не делает).
- `scripts/anti-weakening-gate.sh`, `scripts/mutation-check.sh` (30 патчей), coverage-floor.
- CI: матрица PHP 8.3–8.5 × Laravel 12/13, отдельный PG16-лейн, отдельные джобы api, tenancy,
  crm, panels, media, notifications, wakeup, tasks, auth, cohort-агрегатор, layer-gate,
  contract-snapshot guard, static analysis, mutation registry.

### Измеренные дырки в контуре

- ~~`composer test:packages` перечисляет **17 пакетов из 21**~~ — **опровергнуто 2026-09-19**
  при детализации P1. Фактически цикл перечисляет **все 20 живых пакетов**: api, attributes,
  auth, core, crm, transfer, intl, search-intl, media, modules, notifications, panels,
  recurrence, search, search-meilisearch, tasks, tasks-workflow, tenancy, wakeup, workflow.
  `import-export` включён быть не может — у тумбстоуна нет `composer.json`. Проверка —
  чтение `scripts.test:packages` корневого `composer.json`. Следствие: вердикт N02 матрицы
  становится ✗, соответствующая работа из P3 снимается (`P1-design.md` §12).
- Корневой `phpstan.neon` анализирует **7 пакетов** (api, core, crm, tasks, modules, tenancy,
  workflow); `transfer`, `search`, `intl`, `media`, `notifications`, `attributes`, `auth`,
  `panels`, `recurrence`, `wakeup`, `tasks-workflow`, `search-intl`, `search-meilisearch`
  покрыты только своими пакетными конфигами и не все — в CI.
- Нет гейта публичной поверхности, нет BC-диффа, нет гейта сериализуемости durable-payload,
  нет гейта словаря имён, нет проверки направления зависимостей по типу пакета.
- `docs/architecture/` не существует. Ни `constitution.md`, ни `naming.md`, ни
  `table-ownership.md`, ни `configuration.md`. Ближайшее: `docs/dev-doctrine.md`,
  `docs/03_Dev/Architecture_CoreX.md`, `docs/03_Dev/ADR/*`.

## 7. Владение таблицами (сырой срез по `Schema::create`)

| Префикс | Владелец | Пример |
|:--|:--|:--|
| `root_` | tenancy | `root/2026_07_18_170002_create_root_templates_table.php` |
| `wsp_` | tenancy | `2026_07_26_190001_create_wsp_workspaces_table.php` |
| `users`, `sessions` | tenancy (tenant-слой) | `tenant/2026_07_18_180001_create_users_table.php` |
| `aut_` | auth | `tenant/2026_07_26_200001_create_aut_roles_table.php` |
| `sys_` (settings/audit/views) | core | `core/database/migrations/pg/*` |
| `sys_attribute_*` | attributes | `2026_09_16_000001_create_sys_attribute_documents.php` |
| `sys_workflow_*` | workflow | `2026_09_15_000001_create_sys_workflow_intents_table.php` |
| `sys_wakeup_*` | wakeup | `2026_09_15_000001_create_sys_wakeup_requests_table.php` |
| `mod_` | modules | `2026_07_13_000000_create_mod_modules_table.php` |
| `crm_` | flex/crm | `2026_06_14_000100_create_crm_pipelines_table.php` |
| `tsk_`, `corex_task_*` | tasks, tasks-workflow | `000071_create_task_operation_receipts.php` |
| `ntf_` | notifications | `2026_09_15_000000_create_ntf_notifications_table.php` |
| `med_` | media | `2026_09_15_000000_create_med_files_table.php` |
| `corex_transfer_*`, `corex_export_*` | transfer | `2026_09_15_000001_create_corex_transfer_tables.php` |
| `corex_search_*` | search | `2026_09_15_000001_create_corex_search_lifecycle_tables.php` |
| `corex_reference_*`, `corex_rate_*` | intl | `2026_09_15_000073_create_rate_snapshots.php` |

Префикс `sys_` разделён между четырьмя пакетами (core, attributes, workflow, wakeup) — это не
дефект, но требует явной фиксации владения по таблице, а не по префиксу.

## 8. Ключевые реализации, которые аудит недооценил

- **Поиск уже разделён** на query / schema / documents / index / hydration; провайдеры
  возвращают `Candidate`+`RecordRef` и не грузят Eloquent. Гидратор — отдельный
  `PolicySearchHydrator` с авторизацией `search.hydrate`.
- **Второй провайдер поиска уже есть**: `PostgresSearchProvider` (плюс null-драйвер
  `UnsupportedSearchProvider`), Meilisearch подключается через `SearchManager::extend()`.
- **`corex/attributes` не пишет в поисковый индекс** и не тянет Meilisearch — связи нет вообще.
- **`RecordRef` уже opaque**: `entity` — зарегистрированный handle с валидацией регуляркой,
  а не имя класса модели и не morph-алиас.
- **`packages/modules` уже компилирует** реестр (`RegistryCompiler` → `CompiledRegistry` →
  `CompiledModuleRegistry`, `failIfMissing` в production); не хватает именно freeze и запрета дубликатов.
- **`corex/search-intl` не владеет saved views**: `SqlSavedViewLabelReader` делегирует в
  `CoreX\Intl\Contracts\TranslationReader`, а `SavedViewRepository` живёт в `corex/core`.
- **`RecordRules` в `corex/auth` не существует**: есть `AccessChecker`, `DefaultAccessChecker`,
  `ScopePredicate`, `DefaultScopeResolver`, `DirectGrantLookup`, `WorkspacePermissionLayer`.
- **`packages/import-export` — не дубль**, а тумбстоун из одного `CHANGELOG.md`; ссылок на
  `CoreX\ImportExport` в коде нет.
