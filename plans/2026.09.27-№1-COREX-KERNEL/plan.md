# 2026.09.27-№1-COREX-KERNEL — План №1 — CoreX Kernel 1.0: архитектурный идеал ядра и чистый API

## 0. Meta

| Поле | Значение |
|:--|:--|
| Plan ID | 2026.09.27-№1-COREX-KERNEL |
| Short ID | PLAN3 |
| Title | План №1 — CoreX Kernel 1.0: архитектурный идеал ядра и чистый API |
| Layout | v2 |
| Document Type | Executable Master Plan (катящийся дизайн с немедленным исполнением — D21) |
| Authoring Model | claude-opus-5-5 (owner-authorized P10/P05 design audit remediation, D76, 2026-10-01); claude-opus-5-5/high (P05 и P10 design, 2026-10-01); codex/gpt-6-sol/high (owner-authorized P00–P04 finish, D65, 2026-09-29); codex/gpt-6-sol/high (owner-authorized P04 design, D62, 2026-09-29); codex/gpt-6-sol/xhigh (P03.2 routine input/formatter repair D61, 2026-09-29); claude-opus-5-5/high (initial, P01 design); codex/gpt-6-astra/high (P00 audit remediation, 2026-09-27; P00–P01 finish, 2026-09-28); codex/gpt-6-sol/high (P01 B1–B3 owner-prescribed remediation and finish, 2026-09-28); codex/gpt-6-sol/high (owner-authorized inline audit corrections, 2026-09-28); claude-opus-5-5/high (P02 design, 2026-09-28); codex/gpt-6-sol/high (owner-authorized P02 A1–A4 remediation, 2026-09-28); claude-opus-5-5/high (P03 design, 2026-09-29); codex/gpt-6-astra/high (owner-authorized P03 A1–A5 remediation + finish, D58, 2026-09-29); codex/gpt-6-astra/xhigh (owner-authorized P03 full replay and inline design fixes, D59, 2026-09-29); codex/gpt-6-sol/high (P03.1 historical evidence scan clarification, D60, 2026-09-29) |
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
  `task:plan-audit <ID> design` другой моделью, D24; текущий P04 принят владельцем без повторного audit по D66) и сразу исполняется по execution-sheet. Фазы после текущей хранятся
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
- **Hosted CI — граница приёмки фазы** (D73, заменяет D47): фаза закрывается только при зелёном workflow `CI` на своём
  финальном commit; падающий тест или гейт не обходится — исправление либо карантин отдельным D# с фазой-владельцем;
  гейты герметичны; пороги не понижаются.
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

Детализированы: P00, P01, P02, P03, P04, P05, P10. Вставная P10 исполняется до P05 (D73). Фазы P06–P09 — skeleton в `backlog/` (D21, D24), детализируются по очереди. Batch — предложение группировки;
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
| B02-A | P02.1 | implementation/high | manual | full | recommended: швы ядра на общем инструментарии швов (`check-maps`, `seams`, `golden-delta`); первым в B02-A; старт только после O-3. |
| B02-A | P02.2 | implementation/high | manual | full | recommended: швы operations тем же инструментарием и runbook; вторым в B02-A, после зелёного P02.1. |
| solo | P02.3 | implementation/xhigh | manual | full | recommended: механический перенос ≥500 файлов с сухим прогоном и полным стеком — отдельная сессия и максимальный effort класса. |
| solo | P02.4 | implementation/high | manual | full | recommended: идентификаторы и гейты на перенесённом дереве; отдельная сессия после P02.3. |
| solo | P03.1 | implementation/high | manual | full | recommended: гейт раскладки, ретайр гейта tenancy и STR-доктрина — CI/docs-контекст, отдельный от массового переноса; первым в P03. |
| solo | P03.2 | implementation/xhigh | manual | full | recommended: механический перенос 278 классов с сухим прогоном, Rector и полным стеком — отдельная сессия и максимальный effort класса, как P02.3; после зелёного P03.1. |
| solo | P04.1 | frontier/high | manual | full | recommended: scanner/name-resolution/PHPDoc fixture setup is an independent tooling boundary; validate report semantics before rewriting all consumers. |
| solo | P04.2 | frontier/high | manual | full | recommended: 1117 type verdicts, model/transaction DTO seams and R5=91 need a bounded public-contract replay/review context separate from registry boot. |
| solo | P04.3 | frontier/high | manual | full | recommended: package error ownership and persisted idempotency reconstruction form a separate boot/failure boundary after the classified surface. |
| solo | P04.4 | implementation/high | manual | full | recommended: fixed strict graph/manifest/BC/docs rollout consumes both producer results; route differs from frontier items and cannot share their batch. |
| solo | P10.1 | implementation/high | manual | light | recommended: CI-рецепт и скрипты; результат проверяет сам hosted CI, после item отказы читаемы — первым в фазе. |
| solo | P10.2 | implementation/high | manual | full | recommended: меняет правило формирования публичного манифеста и раскладку; полноту манифеста CI не видит — независимое ревью (D75). |
| solo | P10.3 | implementation/high | manual | full | recommended: граница отказа валидации схем инструментов; fail-closed и завершение процессов на всех ветках проверяются чтением — независимое ревью (D75). |
| solo | P10.4 | implementation/high | manual | light | recommended: лейны тестов, покрытие, остаточные отказы матрицы; закрывает фазу полностью зелёным hosted CI. |
| B05-A | P05.1 | implementation/high | manual | light | recommended: Options, lazy singleton и удаление static Config правят те же providers, что делит P05.2; первым в B05-A. Review light по D71. |
| B05-A | P05.2 | implementation/high | manual | light | recommended: деление providers на registrars по уже переведённым на Options файлам; вторым в B05-A, после зелёного P05.1. |
| solo | P05.3 | implementation/high | manual | full | recommended: самый объёмный item (16 пакетов); меняет время жизни зависимостей, ошибки класса «чужой tenant» тесты ловят слабо — отдельная сессия и независимое ревью (D71, D72). |
| solo | P05.4 | implementation/high | manual | light | recommended: проверки итога (резидентный режим, config:cache), генератор reference, доктрина и CI — отдельный docs/CI-контекст; закрывает фазу. |

## 4. Update Log

| Дата | Изменение | Автор |
|:--|:--|:--|
| 2026-10-01 | Design audit fingerprint `f188df5f…` — ATTENTION, 4 Major (`findings/P05-P10-design-audit.md`). Правка по разрешению владельца, D76: критерий агрегатора в P10.1; CHANGELOG и arch-тесты в Files P10.2; `test:hermetic` определяется в P10.1, Pest `^4.7` в `ci-local.sh`; две команды Validation P05.3; hosted CI на каждом item P05; независимое ревью продуктовых правок P10.4. Маршруты и порядок строк 14–20 не менялись. Нужны новый finish и design audit независимой моделью; исполнение не начиналось, продукт не менялся. | auditor/designer claude-opus-5-5 |
| 2026-10-01 | `task:plan-design finish`: сверены зависимости P00–P05 и P10 (P04 → P10.1 → P10.2 → P10.3 → P10.4 → B05-A → P05.3 → P05.4); в execution-sheet записаны строки 14–20 без изменения групп, маршрутов и review из D71/D75; исполнитель и reviewer — по пулу `session-routing.md`. Привязка досье P10 дополнена в Inputs P10.3 и P10.4; заполнен Phase Handoff P04 по evidence закрытия. Новых решений нет. Новый fingerprint ждёт независимого design audit; исполнение не начиналось, продукт не менялся. | designer/claude-opus-5-5/high |
| 2026-09-29 | Owner D66 accepts GREEN P04 finish and waives another independent design audit for this fingerprint. Legacy gate records explicit owner acceptance; handoff/bundle now point to execution-sheet row 10, P04.1. No independent audit or runtime execution claimed. | designer/codex/gpt-6-sol/high |
| 2026-09-29 | `task:plan-design finish`: D65 owner-authorized Sol/high; validated P00–P04 dependencies and 13 execution-sheet admissions; confirmed four sequential P04 solo routes/full reviews; reused linked dossiers/captures and seven sealed P04 inputs. New finish fingerprint awaits independent design audit; execution not started. | designer/codex/gpt-6-sol/high |
| 2026-09-29 | `task:plan-design P04`: D62 owner-authorized Sol/high authoring; D63 symbol surface/DTO/transaction seam; D64 typed error registry and reviewed semantic Golden delta. P04.1–P04.4, all-type/source/85-site maps, dossier/retrieval/examples, proposed solo routes; runtime not executed. | designer/codex/gpt-6-sol/high |
| 2026-09-29 | P03.2 owning admission repair D61: lossless full-map reading projection and inherited adapter formatting; product scope and gates preserved | executor/codex/gpt-6-sol/xhigh |
| 2026-10-01 | `task:plan-design P05`: `backlog/P05.md` → `phases/P05/`, items P05.1–P05.4; D69 (config-контракт: Options на config-файл, lazy singleton + boot-валидация, удаление static Config, закрытый список опасных режимов), D70 (registrars, пороги provider, порты = PSR/`Illuminate\Contracts`, baseline Impersonation для P07, сценарий резидентного режима), D71 (implementation/high; review light, для P05.3 — full), D72 (мандат владельца: критерий — качество ядра). Первая редакция D69–D71 пересмотрена до finish по D72 и двум perplexity-capture: Options внутренние, слои валидации с runtime-проверками и поимённым acknowledgement, БД только через resolver, lifetime-гейт L1–L3 со сканом registrars, PG-сценарий смены tenant. Инвентарь перемерен по commit `39ae489a`: skeleton-цифра «41 потребитель Config» устарела. Досье, примеры, факты, три context7- и два perplexity-capture сохранены. Продукт не менялся; проверки автором не запускались (`vendor/` отсутствует). | designer/claude-opus-5-5/high |
| 2026-10-01 | Корректировка по мандату владельца (D72, D73): только чтением установлено, что hosted CI красный с 2026-07-15 и на commit закрытия P04 падает 21 джоб из 37 — `findings/01-hosted-ci-debt.md`, классы H1–H12. D47 прекращён; вставлена фаза P10 «зелёный hosted CI на базе P04» перед исполнением P05; с P10 фаза закрывается только при зелёном hosted CI. P05 получила зависимость от P10 и hosted CI в приёмке. Продукт не менялся. | designer/claude-opus-5-5/high |
| 2026-10-01 | `task:plan-design P10`: `backlog/P10.md` → `phases/P10/`, items P10.1–P10.4; D74 (манифесты только из собственных объявлений при прежней схеме `php-api/1`, мост `Tooling/` и R6, объявленные зависимости, протокол готовности worker схем, `test:hermetic`, CI без изменения манифестов, `contract-guard` по манифесту), D75 (implementation/high; review full для P10.2 и P10.3). Причины H2, H5, H7, H11 воспроизведены в чистой копии и записаны в `artifacts/P10-design/facts/`. Items P05 переведены на `test:hermetic`. Продукт не менялся. | designer/claude-opus-5-5/high |
| 2026-09-27 | План создан: верификация аудита, ресерч, D01–D23, целевая архитектура, P00 детализирована, P01–P09 skeleton (карта фаз — `roadmap.md`) | claude-opus-5-5/high |
| 2026-09-27 | `task:plan-design P00`: D24 (skeleton P01–P09 → `backlog/`, цикл finish + design audit на фазу); досье P00 дополнено (`research/P00-design-rag.md`, `examples.md`, no-search record); Code Guidance P00.2/P00.3 | claude-opus-5-5/high |

| 2026-09-27 | P00 audit remediation A1–A5: graph prerequisites before split, deterministic map/doctrine checks, fresh supersede baseline, portable chatom read; B00-A same-session Codex + Cursor reviewer, semantic route unchanged; scope remains design only | codex/gpt-6-astra/high |
| 2026-09-28 | `task:plan-design P01`: `backlog/P01.md` → `phases/P01/`, items P01.1–P01.4, D36–D39, досье `research/P01-design.md`, `research/P01-design-rag.md`, примеры/факты/скрипты `artifacts/P01-design/`, captures `artifacts/P01-design-rag/`; Routing solo P01.1, B01-B, solo P01.4 | claude-opus-5-5/high |
| 2026-09-28 | `task:plan-design finish`: согласованы зависимости и три запуска P01 в execution-sheet; явная ссылка на индекс captures; handoff на независимый design audit текущего fingerprint. Граница — дизайн P00–P01 по D24. | designer/codex/gpt-6-astra/high |
| 2026-09-28 | Manual B1–B3 autofix + `task:plan-design finish`: D40 supersedes D38 (симметричная golden-проекция), обязательные поля php-surface, Validation с exit 0 на корректном дереве; доказательства — `findings/design-remediation-2026-09-28.md`. Продукт и plan-run не затронуты. Фактический маршрут implementation/high вместо рекомендованного frontier/high записан в provenance. | designer/codex/gpt-6-sol/high |

| 2026-09-28 | Owner-authorized audit autofix: D41, actual-declaration golden collector, access dev closure, wire fixture/exit/cleanup, scoped derived normalization, float-preserving JSON, successful baseline mode, initial ownership-baseline allowance and fail-closed analyser. Independent review within current audit; product unchanged. | auditor/fixer/codex/gpt-6-sol/high |
| 2026-09-28 | `task:plan-design P02`: `backlog/P02.md` → `phases/P02/`, items P02.1–P02.4, D44–D45, досье `research/P02-design.md`, `research/P02-design-rag.md`, примеры/факты/скрипты `artifacts/P02-design/` (перенос `p02-transfer.py` с сухим прогоном без Rector), captures `artifacts/P02-design-rag/`; ремонт строки карты `StubToolHandler` (D44 п.7); Routing B02-A, solo P02.3, solo P02.4; исполнение P02 ждёт O-3. | claude-opus-5-5/high |
| 2026-09-28 | `task:plan-design finish`: согласованы P00–P02; execution-sheet строки 5 (B02-A), 6 (solo P02.3, implementation/xhigh), 7 (solo P02.4); P02 допускается после GREEN design audit и O-3. Граница — дизайн P00–P02 по D24. | designer/claude-opus-5-5/high |

| 2026-09-28 | Owner-authorized same-session P02 A1–A4 remediation: D46, config-filename references and negative gates, root classmap/files projection and existence checks, predecessor-produced Required Read, protected Golden diff scope; dossier/examples aligned. Product unchanged; actual implementation/high, recommended frontier/high recorded. | fixer/codex/gpt-6-sol/high |
| 2026-09-29 | `task:plan-design P03`: `backlog/P03.md` → `phases/P03/`, items P03.1–P03.2, D57 (заменяет D05: корень пакета = модуль, 278 переносов, 68 остаются, продукт вне N5, гейт `module-layout` R0–R5, ретайр `tenancy-structure-gate`), glossary N5; досье `research/P03-design.md`, примеры/скрипты/эталоны `artifacts/P03-design/`, `artifacts/P03/`; черновые выводы проверены (поправки: 23 внешних FQCN меняются, продукт не переносится, config/glossary-классы остаются); Routing solo P03.1, solo P03.2. | claude-opus-5-5/high |
| 2026-09-29 | P03 audit A1–A5 remediation: D58; manifest/lock sync, Golden-before-verify, R5 namespace resolution и прямой Laravel bridge, точный kernel provider. Проверки — artifacts/P03-design-remediation/. Продукт и статусы исполнения не изменены; новый finish, независимый audit ожидается. | designer/codex/gpt-6-astra/high |
| 2026-09-29 | P03 full Rector replay: implicit imports, baseline paths, architecture fixtures, AST-verified hashes, portable harness setup; D59; design-only scope | auditor/designer codex/gpt-6-astra/xhigh |
| 2026-09-29 | P03.1 execution: D60 clarifies the active-source retirement scan while preserving immutable audits; current baseline and module gate copied unchanged, STR doctrine/CI updated. The unchanged P03.2 helper lint findings remain with their owner. | executor/designer/codex/gpt-6-sol/high |

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
