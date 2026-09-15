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

## Где что

- Библиотечный код пакета: `packages/<name>/src`, `config/`, `database/migrations/`.
- Тесты — **per-package** (канон D81): новые тесты пакета кладутся в `packages/<pkg>/tests/**`.
  Корневой `tests/` — харнесс core/modules (Arch/Feature/Unit/PHPStan) + инструменты качества
  (`phpunit.xml`, `pint.json`, `phpstan.neon`).
- Спека и ADR — в Brain-vault (CoreX), не в репозитории кода.

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
<!-- swissknifeman:registry sha=b4d6d25e2758 skills=232 -->

## Приоритет источников правил

1. Проектные правила и скиллы (`.ai/guidelines/`, `.ai/skills/`, корневые инструкции проекта) задают локальные соглашения.
2. Скиллы swissknifeman (нативный листинг скиллов клиента) — архитектура, структура, практики, процессы. Если в проект добавят Laravel Boost, его версионно-специфичные скиллы приоритетнее пересекающихся.

Скиллы дополняют инструкции среды и запрос владельца; внутри совместимых правил выбирай более специфичный источник (проект > Boost > реестр swissknifeman).

## Как найти нужный скилл

1. Скиллы swissknifeman уже перечислены НАТИВНЫМ листингом клиента (имя + описание каждого доступного скилла резидентны с начала сессии) — ищи по нему; здесь индекс не дублируется.
2. Выбери скилл по описанию; внутри скилла открывай сниппеты только по его таблице «когда какой сниппет открывать» — не загружай всё подряд.
3. Открывай архитектурные скиллы при изменении соответствующих контрактов; обычная правка следует существующей структуре проекта.
4. Устройство системы скиллов в проекте (источник истины, симлинки, gitignore) — скилл `skills-ssot`.
5. Полный индекс с описаниями (если нужен человеку): `skiller status` в проекте либо `generate-hub.sh --target <проект> --full-index --dry-run`.

## Агенты проекта (11)

Роли в `.claude/agents/`. Преднагруженные скиллы — поле `skills:` (⚠ = имя не найдено в реестре, preload молча не сработает).

| Агент | Назначение | Преднагруженные скиллы |
|---|---|---|
| `corex-api-surface` | >- | — |
| `corex-contribution-reviewer` | >- | gh-review, code-review, dependency-audit, dx-design, git-commit-rules |
| `corex-issue-triager` | >- | github-flow, oss-governance, oss-development, issue-triage |
| `corex-orchestrator` | >- | — |
| `corex-release-conductor` | >- | release-engineering, github-flow, dx-design, git-commit-rules |
| `corex-reviewer` | >- | laravel-package-testing, laravel-package-service-provider, laravel-package-compatibility, dependency-injection, code-style-spatie, laravel |
| `corex-security-review` | >- | — |
| `corex-slice-builder` | >- | laravel-package-scaffold, laravel-package-service-provider, laravel-package-compatibility, dependency-injection, code-style-spatie, named-arguments |
| `corex-spec-writer` | >- | architecture, api-design, task-brief-template |
| `corex-test-verifier` | >- | — |
| `corex-test-writer` | >- | laravel-package-testing, test-isolation-guard |
<!-- swissknifeman:hub:end -->
