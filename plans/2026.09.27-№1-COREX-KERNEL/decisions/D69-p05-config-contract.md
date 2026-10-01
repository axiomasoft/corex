---
id: D69
date: 2026-10-01
status: accepted
item: P05
items: [P05, P05.1, P05.4]
supersedes: []
superseded_by: null
---
# D69 — P05: контракт typed config (уточнение D08)

**Actor:** author-choice (designer/claude-opus-5-5/high) по мандату B6 и D72; D08 остаётся нормой, здесь — развилки по фактическому коду после P04.
**Evidence:** RAG:— `artifacts/P05-design/facts/summary.txt` (commit `39ae489a`), `packages/core/src/Config/Internal/Config.php`, `packages/core/src/CoreServiceProvider.php:100-106`, раскладка существующих Options (`facts/options-inventory.tsv`); RAG:✅ `artifacts/P05-design-rag/ctx7-01-testbench-define-environment.md`, `ctx7-02-octane-container-lifetime.md`, `ctx7-03-config-cache-merge.md`, `pplx-02-layered-validation-production-guards.md`.

## Решение

1. **Один Options на config-файл; новый Options — внутренний тип композиции.** `<Pkg>Options` — `final readonly`, `@internal`, `src/Internal/<Pkg>Options.php` корневого модуля, без `#[Api]`. Его читают только публичный provider и registrars (composition root, исключение R5). Runtime-класс получает конструктором нужные значения либо узкий value object, а не весь Options. Новые 11: `CustomFieldsOptions`, `IntlOptions`, `NotificationsOptions`, `PanelsOptions`, `SearchOptions`, `SearchIntlOptions`, `MeilisearchOptions`, `TasksWorkflowOptions`, `TransferOptions`, `WakeupOptions`, `WorkflowOptions`. Четыре уже публичных (`CoreOptions`, `WebhookOptions`, `BulkOptions`, `CostOptions`) и пять внутренних существующих не переносятся; публичные можно инъектировать.
2. **Форма.** `fromArray(array $config): self` проверяет только форму и детерминированные инварианты: типы, диапазоны, допустимые значения, `class-string` через `is_a(…, true)`. Дефолты живут в `fromArray` (`mergeConfigFrom` сливает лишь первый уровень). Нарушения копятся в `ConfigurationValidator`, один `failIfAny()`. Секрет — `SecretRef`; отсутствие секрета формой не считается. `fromArray` на дефолтах без единой переменной окружения обязан пройти.
3. **Биндинг.** `singleton(XOptions::class, fn => XOptions::fromArray(config-key))` в `register()`; публичный provider в `boot()` резолвит свой Options (проверка формы при загрузке, CFG-004). `bind` с повторным разбором и `instance()` в `register()` запрещены. Формулировка `research/04-architecture.md` §7 «в `register()` → `instance(Options)`» заменяется этим пунктом: Testbench `defineEnvironment` выполняется между register и boot, host-providers также пишут config в `register()`.
4. **Static `CoreX\Config\Internal\Config` удаляется целиком** (42 метода, 2 static-свойства; у `enableOctaneWorkerOptIn` вызовов нет). Замена: `src` — `CoreOptions` конструктором; миграции и тесты — `app(CoreOptions::class)->…` (`database/` и `tests/` — граница composition); `scripts/table-write-ownership-gate.php` и `scripts/mutations/P1.14-pg-lane-split.patch` правятся в том же item. Конфигурируемость имён таблиц не меняется (P08/D20).
5. **Тесты.** Конфигурация задаётся в `defineEnvironment`; запись config после boot сопровождается `$this->app->forgetInstance(XOptions::class)` до первого резолва потребителя. Скрытое перечитывание не возвращается.
6. **Граница чтения config.** `config()`/config repository/`env()` в `packages/*/src` — только `*ServiceProvider.php`, `**/Laravel/*Registrar.php`, `**/Laravel/*Factory.php`, `*ConfigSection.php`, `Config/Internal/Commands/ValidateConfigCommand.php`. Glob `Commands/*.php` из allowlist удаляется. Гейт `config-composition-gate`: C1 `config()`, C2 любое обращение к config repository, C3 ссылка на `CoreX\Config\Internal\Config`, C4 `env()`.
7. **Опасные режимы.** Закрытый список: `corex-workflow.enforce_declarations = false`; `accept_legacy_unstamped = true` (`corex-notifications.digest`, `corex-tasks-workflow.wakeup`, любой `corex-wakeup.handlers.<kind>`). В `production` включённый опасный режим → `InvalidConfiguration`, если его точный путь не перечислен в `corex.acknowledged_dangerous_modes` (список строк; шаблоны и «подтвердить всё» не поддерживаются). Подтверждённый режим виден в `corex:config:validate` как предупреждение. `corex-operations.require_physical_tenancy = false` в список не входит: это уже именованный явный opt-out штатного boxed-режима; отчёт показывает его как уведомление.
8. **Octane opt-in** остаётся полем `CoreOptions::pubsubOctaneWorkerOptIn`, которое выставляет конфигурация выделенного worker-процесса; static-override удалён.
9. **`docs/architecture/configuration.md` генерируется** `scripts/configuration-reference.php` из config-файлов и Options (колонка `typed_options_field` заполнена для каждого ключа пакетного config); `--check` — блокирующий.
10. **Runtime-проверки** — отдельный слой после формы: наличие секрета у включённой функции и опасные режимы п.7. Контракт `CoreX\Config\Contracts\RuntimeConfigCheck` (Spi Implement, `violations(bool $production): list<string>`), реализации тегируются пакетом-владельцем. Запуск — до приёма работы процессом: HTTP — по завершении boot; console — на `CommandStarting`, кроме build-команд (`package:discover`, `config:cache`, `config:clear`, `optimize`, `optimize:clear`, `route:cache`, `route:clear`, `view:cache`, `view:clear`, `event:cache`, `event:clear`, `vendor:publish`, `key:generate`, `storage:link`, `list`, `help`, `about`, `corex:config:validate`). `corex:config:validate` выполняет те же проверки всегда и служит deploy-gate.

## Почему

- После P02–P04 в `src` остался один потребитель static `Config` (сам provider); 132 из 134 ссылок — миграции, тесты, скрипты.
- `CoreOptions` и `ApiOptions` пересобираются на каждый `make()`; 11 из 16 пакетов с config читают массив по ключам; 4 runtime-класса получают config repository мимо гейта.
- п.1: P04 сознательно минимизировал поверхность; 5 из 9 существующих Options внутренние. Публичный Options превращает переименование config-ключа в BC-break PHP API; сервис, принимающий значения, не зависит от формы конфигурации пакета.
- п.2, п.10: сборка образа (`composer install` → `package:discover`) не должна требовать runtime-секретов, но процесс не должен начинать приём работы без секрета включённой функции (`pplx-02`).
- п.7: небезопасный режим блокирует старт в production; законное исключение — узкий поимённый opt-in, а не общий выключатель (`pplx-02`: Elasticsearch bootstrap checks, Django deployment checklist).
- Отклонено: eager `instance` в `register()`; проверка секретов «при первом использовании» (воркер стартует и падает на конкретном job); общий флаг отключения guard; guard на `require_physical_tenancy` (дублирует собственный явный флаг).

## Consequences

P05.1 реализует п.1–8 и п.10; P05.4 — п.9, `config:cache`, сборочный сценарий без окружения и статьи CFG-002…CFG-005. Golden php-surface: удаление `Config`, +`RuntimeConfigCheck` (Spi Implement), +поле `CoreOptions::acknowledgedDangerousModes`, +2 поля `ApiOptions` — reviewed semantic delta по D64. Новых `#[Api]` Options нет.
