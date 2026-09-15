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

## Скиллы вне нативного листинга (17)

> Эти скиллы установлены swissknifeman, но не подтверждены в нативном активном наборе проекта — перечислены поимённо, чтобы не потерять обнаружение.

### devops — Docker environments, CI/CD pipelines, GitOps

- **ci-cd** — GitHub Actions / GitLab CI pipelines for Laravel: lint, tests, build, release + security gates (SAST, dependency audit, image scan) + coverage-gate in CI (Clover threshold, jobs)
- **db-test-preflight** — Pre-flight check of test DB (Postgres in Docker) BEFORE running tests: container up (docker compose ps / pg_isready), target DB exists and its name ends with _test (not dev/prod), migrations applied; distinguishes host vs docker connection. Activate when running Postgres tests, wiring hooks/CI, or when 'tests fail on DB connection'.
- **docker** — Router for Dockerizing a Laravel/PHP app: directs to the right sub-skill (PHP image, Postgres, dev/prod compose split, full service stack, Vite HMR, Makefile). Use when the task involves Docker/Compose for this stack.
- **docker-dev-prod** — Split docker-compose into dev (build + bind-mount) and prod (prebuilt image + named volumes), local override, &app_env anchor
- **docker-php** — Production PHP Docker image: alpine-fpm or all-in-one serversideup/php (FPM+Nginx), entrypoint.d hooks, healthcheck on /up
- **docker-postgres** — PostgreSQL in Docker: pg_isready healthcheck, named volume for data, init script creating test DB <app>_test for test isolation
- **docker-services** — Full Laravel service stack from one image: app (fpm-nginx), queue, scheduler, reverb, optional docs; healthcheck /up and depends_on service_healthy
- **docker-vite** — Vite dev server as separate compose service for Laravel: named volume for node_modules, HMR via localhost, host 0.0.0.0
- **gitops** — Use when defining team Git strategy: branch model (main/develop/feature/hotfix), PR workflow, branch protection.
- **makefile** — Makefile as single entrypoint: help, .PHONY, Docker command aliases; delegating root Makefile pattern with catch-all to docker/Makefile

### system — Local environment topology: the three hub nodes (Brain vault, swissknifeman, projects base) and how agents navigate between projects and their docs

- **agent-packs** — Install and VERSIONED upgrade of agent-packs (Claude Code agents/workflows/hooks) from harness into a consumer project: stack detect -> interview -> render+copy -> lock; upgrade via CHANGELOG delta with 3-way reasoning over sha256 in lock, no diff engine. Activate on: install/upgrade agent-pack, add roles+dev-loop+review-gate to project, versioned upgrade of agent templates, agent-pack.lock.
- **brain-locator** — Reach the Brain vault (ecosystem Obsidian docs) from a project — non-hardcoded path resolution (maind/$HOME), own and linked-project docs, sync
- **cross-project-coordinator** — Orchestrate cross-project code analysis: walk linked projects (via topology), find dups/divergence/shared-package candidates, emit prioritized unification recommendations. Read-only — no edits, no PRs. Run for broad cross-project work, not a single repo.
- **local-topology** — Root map of the local dev environment — three hub nodes (Brain vault, swissknifeman, projects base) and navigation between projects, their code and docs. Read from ~/.swissknifeman/topology.json
- **session-observer** — EXPERIMENTAL (enable per-project). Post-hoc Claude Code session analysis from JSONL logs — finds weak spots on 3 axes: agents (skill-skip, drift, loops), skills (gaps, dead weight, thin), settings (hooks, permissions). Findings → learnings via memory-hook, human promotes. Activate AFTER a work cycle / at session end / on request «analyze session» — only if enabled in project.
- **shared-memory** — Constellation shared brain via hook ~/.claude/hooks/memory/ + mAInd daemon. Recall before work, remember after. Project canon = MODE=maind (daemon :4111, scope isolation maind-side): private project:<id> + tag-shared ns:<namespace> + global; recall unions them; SessionStart auto-injects digest (native memory off). Routing rule: one fact in one store. Swappable backend: maind|native|file|federation|off.
- **swissknifeman-locator** — Reach swissknifeman (skills node) from an external repo — non-hardcoded path resolution, root config.json as registry of linked projects, project↔hub link scheme
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
