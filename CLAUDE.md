# CoreX — контекст для ИИ-ассистентов

Монорепо ядра CoreX (Laravel, MIT). `corex/*` — уровень 0: абстрактный загрузчик модулей и утилиты,
годные ЛЮБОМУ Laravel-проекту, БЕЗ привязки к конкретному продукту. Flex (FlexCMS → FlexCRM) —
сегодняшний потребитель, а не владелец ядра. Публикация — split-зеркалами по пакету.

## Конвенции кода

- `declare(strict_types=1);` в каждом PHP-файле; все классы `final`, кроме абстрактных точек расширения.
- Constructor property promotion, явные типы и возвраты, `#[Override]` где уместно.
- Именованные аргументы при нескольких однотипных параметрах.
- Контракты — только интерфейсы в `src/Contracts/`; реализации скрыты. Контракты не меняем без major ([[ADR-008]]).
- Расширение — только через события/Observer и `EntityRegistry`; monkey-patching запрещён.
- Pint preset `laravel` (`composer lint`), PHPStan (`composer analyse`), Pest (`composer test`).

## Архитектура ядра (`packages/core`)

- `BaseModel` (ULID + SoftDeletes) + трейты `Concerns/*` (для моделей, что не могут наследовать BaseModel, напр. `User`).
- Мульти-тенантность — **физическая DB-per-account** (stancl/tenancy v4; ADR-004 переписан D1):
  контракты (`TenantContext`, `TenancyManager`, `AccountConnectionResolver`) живут в `corex/core`,
  реализация — пакет `corex/tenancy` (boxed-профиль ставится без него). Legacy row-level каркас
  удалён (P2.14) — в дереве его нет, новые фичи на нём не строятся.
- `EntityRegistry` — реестр сущностей; источник морф-карты (`Relation::enforceMorphMap`).
- `Settings` (System→Tenant→User), `Audit` (append-only), `MoneyCast` (`brick/money`, minor units).
- ID/морфы/деньги — по ADR-003/004/005; стек — ADR-001 (Laravel 12–13, PHP 8.3–8.5).
- Ядро требует **PostgreSQL 16** (тенантская БД) — SQLite-лейн тестов покрывает только pure-PHP код (OQ-12).

## Где что

- Библиотечный код пакета: `packages/<name>/src`, `config/`, `database/migrations/`.
- Тесты — **per-package** (канон D81): новые тесты пакета кладутся в `packages/<pkg>/tests/**`.
  Корневой `tests/` — харнесс core/modules (Arch/Feature/Unit/PHPStan) + инструменты качества
  (`phpunit.xml`, `pint.json`, `phpstan.neon`).
- Спека и ADR — в Brain-vault (CoreX), не в репозитории кода.
- **Канонический DB-контракт** (все таблицы/поля/связи по префиксам, approval-gate) —
  `~/Vaults/Brain/05-Projects/01-Products/FlexCRM/plans/flex/60_contracts/DB_SCHEMA.md`. Миграции
  ядра (`root_/sys_/mod_/wsp_/aut_`) ОБЯЗАНЫ ему соответствовать — статус контракта `FROZEN v1.0`,
  сверка финальна. Порядок фаз реализации —
  `~/Vaults/Brain/05-Projects/01-Products/FlexCRM/plans/flex/50_execution/00_EXECUTION_ROADMAP.md`;
  исполняемый план ядра — `plans/archive/COREX-CORE/`.

## Доктрина разработки

- Три уровня (D91/D70): **0 — CoreX** (`corex/*`, загрузчик модулей + утилиты для любого проекта) →
  **1 — FlexCMS** (`flex/*`, компоненты поверх CoreX) → **2 — FlexCRM** (композиция). Зависимости —
  строго вниз; cross-ecosystem-концерны — standalone, не в `corex/*`.
- Тест агностичности: «`corex/*` полезен проекту БЕЗ CRM/CMS/Store»; зависимость `corex/*` →
  `flex/*` запрещена (гейт — `tests/Arch/LayerTest.php`). Flex-строк/DDL в `corex/*` быть не должно.
- Полные правила, гейты и их честное состояние — **`docs/dev-doctrine.md`** (входной документ).

<!-- swissknifeman:hub:start -->
# Swissknifeman: хаб скиллов

<!-- Сгенерировано scripts/generate-hub.sh из реестра swissknifeman. -->
<!-- НЕ редактируйте вручную: перегенерация затрёт правки. -->
<!-- swissknifeman:registry sha=7046e720edde skills=229 -->

## Приоритет источников правил

1. Проектные правила и скиллы (`.ai/guidelines/`, `.ai/skills/`, корневые инструкции проекта) — высший приоритет.
2. Скиллы swissknifeman (нативный листинг скиллов клиента) — архитектура, структура, практики, процессы. Если в проект добавят Laravel Boost, его версионно-специфичные скиллы приоритетнее пересекающихся.

Конфликт правил: побеждает более специфичный источник (проект > Boost > реестр swissknifeman).

## Как найти нужный скилл

1. Скиллы swissknifeman уже перечислены НАТИВНЫМ листингом клиента (имя + описание каждого доступного скилла резидентны с начала сессии) — ищи по нему; здесь индекс не дублируется.
2. Выбери скилл по описанию; внутри скилла открывай сниппеты только по его таблице «когда какой сниппет открывать» — не загружай всё подряд.
3. Структурные вопросы нового кода решай в порядке: структура (`laravel-structure`) -> взаимодействие (`dependency-injection`) -> реализация (профильный скилл).
4. Устройство системы скиллов в проекте (источник истины, симлинки, gitignore) — скилл `skills-ssot`.
5. Полный индекс с описаниями (если нужен человеку): `skiller status` в проекте либо `generate-hub.sh --target <проект> --full-index --dry-run`.

## Скиллы вне нативного листинга (2)

> Эти скиллы установлены swissknifeman, но не подтверждены в нативном активном наборе проекта — перечислены поимённо, чтобы не потерять обнаружение.

### system — Local environment topology: the three hub nodes (Brain vault, swissknifeman, projects base) and how agents navigate between projects and their docs

- **cross-project-coordinator** — Orchestrate cross-project code analysis: walk linked projects (via topology), find dups/divergence/shared-package candidates, emit prioritized unification recommendations. Read-only — no edits, no PRs. Run for broad cross-project work, not a single repo.
- **shared-memory** — Constellation shared brain via hook ~/.claude/hooks/memory/ + mAInd daemon. Recall before work, remember after. Project canon = MODE=maind (daemon :4111, scope isolation maind-side): private project:<id> + tag-shared ns:<namespace> + global; recall unions them; SessionStart auto-injects digest (native memory off). Routing rule: one fact in one store. Swappable backend: maind|native|file|federation|off.
<!-- swissknifeman:hub:end -->
<!-- maind:healthcheck hash=2bb4346a5dd4 (managed by maind — не редактировать вручную) -->
## ⚠ ПРИОРИТЕТ №1 — связь с mAInd (общая память + граф)

Перед началом работы убедись, что проект подключён к хабу **mAInd**. Это критично:
без связи изменения этой сессии **не попадут** в общую память и граф — в истории
разработки появятся невосстановимые пробелы, и дальнейшие связи будет не вывести.

1. Проверь связь: `maind health --cwd`
2. Если статус `partial` / `standalone` — почини: `maind health --cwd --fix`
   (поднимет демон памяти и допишет MCP-серверы хаба в этот проект).
3. Приступай к задаче только при статусе `connected`.

Память **общая** (демон mAInd), scope этого проекта — `project:corex`; факты всей экосистемы — в
scope `global`. Связи проекта с остальными: `maind graph --project corex`.
<!-- /maind:healthcheck -->
