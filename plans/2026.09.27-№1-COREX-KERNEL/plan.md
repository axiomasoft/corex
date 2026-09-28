# 2026.09.27-№1-COREX-KERNEL — План №1 — CoreX Kernel 1.0: архитектурный идеал ядра и чистый API

## 0. Meta

| Поле | Значение |
|:--|:--|
| Plan ID | 2026.09.27-№1-COREX-KERNEL |
| Short ID | PLAN3 |
| Title | План №1 — CoreX Kernel 1.0: архитектурный идеал ядра и чистый API |
| Layout | v2 |
| Document Type | Executable Master Plan (катящийся дизайн с немедленным исполнением — D21) |
| Authoring Model | claude-opus-5-5/high (initial); codex/gpt-6-astra/high (P00 audit remediation, 2026-09-27) |
| Repository | corex |
| Related Packages | все `packages/*`, `docs/`, `docs-public/`, CI, `scripts/`, `tests/` |
| Input | `audits/2026-09-27-perplexity-1.md` (вход, не норматив; вердикты — `findings/00-audit-verification.md`) |
| Execution Mode | design Pn (`backlog/` → `phases/`) → finish → design audit → execute Pn → design Pn+1 по фактическому коду (D21, D24) |
| Target Operator Classes | per-item class/effort — §3 Routing; конкретное расписание — `roadmap.md` `execution-sheet/v1`; pool/runbooks — `session-routing.md` |
| Approval Owner | repository owner |
| Home | repo:corex |
| visibility | private |
| supersedes | PLAN2 `2026.09.19-№1-COREX-PLATFORM-HARDENING` (D23) |
| Successor | — |
| Paused By | — |

## 1. Context

CoreX — Laravel-монорепо платформенного ядра (уровень L0 экосистемы `axiomasoft`): 20 composer-пакетов
(`corex/core`, `corex/modules`, `corex/tenancy`, `corex/operations`, …) + продукт `flex/crm`. PLAN1/PLAN2 довели код до
кандидата релиза с сильными участками (typed `CoreOptions`, compiled registries, transactional outbox, idempotent
operations, SSRF-hardened webhooks, guarded tool invocation, DB-per-account tenancy) и развитой доктриной
(`docs/architecture/constitution.md`: PKG/STR/API/DATA/RUN/CFG/MSG/EXT).

Проверка аудита 2026-09-27 (`findings/00-audit-verification.md`) показывает, что публиковать API как 1.0 рано: vendor
`corex` на Packagist занят чужим владельцем; ядро владеет корнем `CoreX\` и чужим сегментом `CoreX\Tenancy`; в 7 пакетах
namespace собирается из многих каталогов (путь ≠ namespace); публичная поверхность объявлена списками namespace и
неполна (~100 межпакетных импортов необъявленных символов); Eloquent-модели — public API; static `Config` с 43
геттерами; перегруженные providers с анонимными классами; `corex/operations` (namespace `CoreX\Api`) — god-package;
tenancy зависит от неопубликованного снимка stancl `dev-master`; access — от VCS `@dev` AzGuard и только Laravel 12;
CI без SHA-pin/permissions/audit, Roave — report-only; PHPStan level 6.

**Ключевой факт:** ничего не опубликовано (Packagist `axiomasoft` пуст; тег `v0.1.0` — только в приватном GitHub).
Владелец: «неприкасаемого кода нет» (D01). План использует это окно полностью.

Цель — **ядро CoreX 1.0 с архитектурой, пригодной для многих проектов**: однозначные границы пакетов (vendor `axiomasoft`,
namespace = путь, один владелец), feature modules с `Internal`, машинно-защищённые Application API / Extension SPI
(`#[Api]/#[Spi]`), DTO вместо моделей, typed compiled config без static, тонкая композиция без фасадов в домене,
строковые коды ошибок с реестром и RFC 9457, стандартный wire (CloudEvents / Standard Webhooks / OpenAPI 3.1), разрезанный
operations, независимые модули, детерминированная схема, планка качества и supply chain экосистемы — всё на принципах,
общих с chatom PLAN2 (D22).

Целевая архитектура — `research/02-target-architecture.md` (норма по D01–D23; после P00 — `research/04-architecture.md`).
Внешние факты — `research/01-external-research.md`. Спорное — `open-questions.md`.

## 2. Execution Rules

- **Неприкасаемого кода нет** (B4, D01). Любое имя/namespace/таблица/config-ключ/wire-поле/версия меняется, если это
  приближает к целевой архитектуре. Критерий — качество ядра, не объём диффа. Решения прошлых планов не защищают код от
  изменения.
- **Без алиасов и deprecated-мостов** (D01). Перенос = перемещение + правка всех ссылок + тесты + docs в одном item.
  Остаточная ссылка на старое имя — дефект (гейт rename-completeness).
- **Катящийся дизайн с немедленным исполнением** (D21). Фаза проектируется `task:plan-design <ID> <Pn>` по фактическому коду
  после закрытия предыдущей, проходит phase design check (plan-lint + self-check §Self-check досье → `finish` →
  `task:plan-audit <ID> design` другой моделью, D24) и сразу исполняется по execution-sheet. Фазы после текущей хранятся
  skeleton в `backlog/Pnn.md` (не phase carriers) до своей очереди; design audit охватывает только детализированные фазы,
  дизайн вперёд не делается (владелец, B5; D24).
- Порядок фаз: механические структурные изменения (P02, P03) — до проектирования API/подсистем (P04+), чтобы не делать
  работу дважды.
- Каждое принятое решение — `decisions/D##-*.md` (append-only). Proposal в `research/02` не норма без D#. Автор плана
  утверждает решения с очевидно лучшим вариантом без эскалации (B6); owner-gate — только внешние/необратимые действия
  (публикация, создание репозиториев, выдача trust, установка системного ПО).
- Страховочная сетка P01 зелёная перед P02 и после каждого item (`composer` гейты + per-package тесты + API-снимок).
- Механические массовые переносы — **инструментом** (Rector `RenameNamespaceRector`/`RenameClassRector` по карте, `git mv`,
  скрипт); карта переносов коммитится как артефакт фазы; ручные правки — только остаток.
- Item закрывается только по своим Validation-командам; «тест есть» ≠ доказательство; негативный контроль (тест падает на
  внедрённом нарушении) обязателен для каждого нового гейта.
- Комментарии в коде объясняют «почему», не историю плана. Ссылки на работу вне каталога плана — только квалифицированные
  (`PLAN3.D04`) и только когда без них решение непонятно.
- Документация меняется в том же item, что и код (docs-гейты `package-docs-gate`, claims).
- Repository-grounded утверждения — `RAG:—`; внешние — `RAG:✅` с источником; непроверенное — `[UNVERIFIED]`/`VERIFY-AT-EXEC`.
- **Внешний ресерч обязателен и сохраняется** (B7): каждая фаза при дизайне и исполнении использует perplexity-web
  (+ context7 для документации библиотек) для решений, зависящих от внешних фактов, и **каждый ответ** сохраняет дословно в
  `artifacts/<Pn>-design-rag/<tool>-NN-<topic>.md` (запрос, дата, инструмент, ответ, источники) + строка в индексе
  `README.md`; синтез — `research/<Pn>-design-rag.md`. Captures верхнего слоя — `artifacts/research-rag/`. Утверждение,
  меняющее контракт, подтверждается первичным источником (Packagist/официальная документация/исходники) — capture `http-NN`.
  Не ищется: факты репозитория (`RAG:—`), внутренние решения, покрытое свежими captures. No-search record допустим только
  при пустом списке внешних решений фазы.
- **Стандарт детализации — план самодостаточен для исполнителей codex/cursor** (B10; как chatom PLAN2 §2). Для каждой фазы с
  implementation/economy items:
  1. **Досье** `research/<Pn>-design.md`: (a) карта кода — каждый затрагиваемый файл с якорями и текущим состоянием;
     (b) предвычисленные факты (списки классов/таблиц/команд/ключей/зависимостей) командой воспроизведения + сырой вывод в
     `artifacts/<Pn>-design/facts/`; (c) runbook на item: «команда → ожидаемый результат → действие при расхождении»;
     (d) failure playbook по классам ошибок, включая «стоп и эскалация»; (e) решения по всем развилкам — «выбрать одно»,
     «по образцу», «при необходимости» без конкретики запрещены.
  2. **Примеры** `artifacts/<Pn>-design/examples.md`: готовые скелеты новых файлов, before/after манифестов/конфигов,
     worked examples «вход → выход» для каждого гейта/скрипта, эталонные выходы гейтов на текущем коде.
  3. **Item-контракт** ссылается на досье/примеры в `Inputs`/`Required Reads` с якорем секции; Validation — точные команды
     с ожидаемым выводом (число, строка, exit code).
  4. **Объём (ориентир):** implementation-item — контракт 80–150 строк + секция досье 100–300 строк + примеры;
     economy-item — каждое изменение перечислено; frontier-item — варианты, критерии выбора, acceptance, без runbook.
  5. **Self-check** перед `finalize-design`: по каждому implementation/economy item — какие факты исполнитель ищет сам
     (цель — ни одного), какие решения ему остаются (цель — ни одного), чем доказывает готовность (точные команды).
- **Пропорциональность дизайна.** Сухой прогон (scratch-worktree + полный suite) обязателен только для массовых механических
  items (≥ ~20 файлов по карте); для нового кода — готовый код и тесты с негативным контролем в `examples.md`. Скрипты
  переиспользуются между фазами (`artifacts/<Pn>-design/scripts/*`). Маршруты items не понижаются ради экономии; понижение —
  только D#.
- **Окружение исполнения** (F00 A12.7): PHP 8.4 + Composer 2 + PostgreSQL (docker-compose `pgsql`) обязательны; на хосте
  автора — PHP 8.3 без Docker. Каждая фаза объявляет `$PHP84` (команда-обёртка) из P01.1; без PHP 8.4 — стоп и эскалация,
  не выполнение на 8.3.
- Внешние действия (Packagist, split repos, теги, push в чужие репозитории, создание репозиториев) запрещены: публикация —
  owner-gate P09.
- Canonical statuses: `⬜ Not started`, `🟡 In progress`, `🟢 Done`, `🟠 Done with deviations`, `🔴 Blocked`,
  `⛔ Skipped by decision`.
- Generated carriers (state, status, bundles, decision views, journal) — только через Task tooling.
- **Маршрут запуска** (как chatom PLAN2.D34): семантический class/effort — §3 Routing; конкретный executor/reviewer —
  `roadmap.md` `execution-sheet/v1`; native launch — `session-routing.md`. Запуск на более слабом классе/effort, чем у
  члена батча, запрещён; понижение — только D#.
- **frontier-items не смешиваются в батче с implementation/economy.**
- **Review `full` = независимое ревью другой моделью** (обязательно для GREEN): reviewer из строки execution-sheet, свежий
  контекст без reasoning исполнителя; вход — item-spec + diff + вывод Validation; строка
  `review: <provider>/<model> <GREEN|findings:N>` в Completion Notes; ≤2 fix/recheck-раунда, затем эскалация. Review
  `light` — self-check + affected tests той же сессией.
- Каждая фаза при дизайне переносит эти правила в Validation своих items (строка «Independent review: …» для `full`).

## 3. Routing

Детализированы: P00, P01. Фазы P02–P09 — skeleton в `backlog/` (D21, D24), детализируются по очереди. Batch — предложение группировки;
окончательная — execution-sheet в `roadmap.md`.

| Batch | Items | Model class/effort | Exec | Review | Почему |
|:--|:--|:--|:--|:--|:--|
| B00-A | P00.1 | frontier/high | manual | full | recommended: glossary и карта пакетов/namespace задают имена всего ядра; решения с фактами. |
| B00-A | P00.2 | frontier/high | manual | full | recommended: финальная архитектура `research/04-architecture.md` + delta доктрины по glossary P00.1; вторым в B00-A. |
| B00-A | P00.3 | frontier/high | manual | light | recommended: перевод PLAN2 в superseded и `plans/ACTIVE.md` — bookkeeping решения D23; третьим в B00-A. |
| solo | P01.1 | implementation/high | manual | full | recommended: окружение PHP 8.4 и манифесты — предусловие всех остальных items; owner gates O-1/O-2 проверяются первыми шагами. |
| B01-B | P01.2 | implementation/high | manual | full | recommended: golden на зелёном стеке P01.1; общий контекст сетки с P01.3. |
| B01-B | P01.3 | implementation/high | manual | full | recommended: ratchet-гейты после golden (анализ `tests/Golden`, anti-weakening правится последовательно); вторым в B01-B. |
| solo | P01.4 | implementation/high | manual | full | recommended: CI подключает команды, созданные P01.1–P01.3; отдельная сессия — workflow-hardening и zizmor. |

## 4. Update Log

| Дата | Изменение | Автор |
|:--|:--|:--|
| 2026-09-27 | План создан: верификация аудита, ресерч, D01–D23, целевая архитектура, P00 детализирована, P01–P09 skeleton (карта фаз — `roadmap.md`) | claude-opus-5-5/high |
| 2026-09-27 | `task:plan-design P00`: D24 (skeleton P01–P09 → `backlog/`, цикл finish + design audit на фазу); досье P00 дополнено (`research/P00-design-rag.md`, `examples.md`, no-search record); Code Guidance P00.2/P00.3 | claude-opus-5-5/high |

| 2026-09-27 | P00 audit remediation A1–A5: graph prerequisites before split, deterministic map/doctrine checks, fresh supersede baseline, portable chatom read; B00-A same-session Codex + Cursor reviewer, semantic route unchanged; scope remains design only | codex/gpt-6-astra/high |
| 2026-09-28 | `task:plan-design P01`: `backlog/P01.md` → `phases/P01/`, items P01.1–P01.4, D36–D39, досье `research/P01-design.md`, `research/P01-design-rag.md`, примеры/факты/скрипты `artifacts/P01-design/`, captures `artifacts/P01-design-rag/`; Routing solo P01.1, B01-B, solo P01.4 | claude-opus-5-5/high |

## 5. Decision Log

Каталог решений: `decisions/`; собрать: `python3 <task-package>/scripts/plan-views.py decisions --plan-dir <plan-dir>`.

## 7. Contracts

### Exported

| Контракт | Версия | Статус | Потребители | Уведомлены |
|:--|:--|:--|:--|:--|
| `research/03-glossary.md` + `research/04-architecture.md` (имена и границы ядра) | P00 | draft | все фазы PLAN3, chatom (сверка D22) | — |
| `packages/*/resources/contracts/php-api.json` (Application API + Extension SPI) | P04 | draft | приложения, пакеты экосистемы | — |

### Consumed

| Контракт | Донор-план | Pinned | Замечено | Реакция |
|:--|:--|:--|:--|:--|
| Кандидат релиза PLAN2 (P17) | `corex:PLAN2` | `ea0b024` | 2026-09-27 | Исходная точка; совместимость не обязательна (D01); PLAN2 superseded (D23). |
| Принципы экосистемы, `#[Api]/#[Spi]`, wire-стандарты | `chatom:PLAN2` (`research/02-target-architecture.md`) | 2026-09-27 | 2026-09-27 | Приняты как нормы (D22); расхождения — §12 `research/02`. |
