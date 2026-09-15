# FLEX-IMPL — Программа реализации экосистемы Flex (кросс-реповый мастер-индекс)

## 0. Meta

| Поле | Значение |
|:--|:--|
| Plan ID | FLEX-IMPL |
| Title | Программа реализации Flex — единый Status Board над исполняемыми repo-планами (CoreX → OSS → FlexCRM → Satellites → Store/CMS) |
| Version | 0.1.0 |
| Status | 🟡 In progress |
| Document Type | Program Master Plan (coordination) |
| Authoring Model | opus |
| Last Updated | 2026-07-08 |
| Repository | vault Brain (`~/Vaults/Brain`), артефакт — `plans/FLEX-IMPL`; КОД — в репах corex · flex/* · flexcrm-app · store/cms |
| Related Packages | corex/* · flex/{workflow,attributes,import-export} · AzGuard · Vaulter · ChatomIo · RegHub · FlexCRM/FlexStore/FlexCMS |
| Execution Mode | phase-first (программный уровень; код-exec делегирован repo-планам) |
| Target Operator Models | opus (program-drive / design нового repo-плана) · sonnet (exec внутри repo-плана) · haiku (LOW) |
| Approval Owner | Dmitry Vostrikov |
| superseded by | FLEX-2, 2026-07-11 |

## 1. Context

Дизайн-программа Flex завершена (vault `plans/flex/`, Этапы 0–8 ☑; `FLEX-CODE` 🟢 done —
C-01..C-06 код-паттерны). Реализация раздроблена по репам с разным тулингом (roadmap
`plans/flex/50_execution/00_EXECUTION_ROADMAP.md` §3.1: «item = один слой/репа»). Этот
план — **программный индекс** этой рыхлой roadmap-прозы в строгом формате `plan-protocol`:
единый Status Board + handoff-указатель «одно следующее действие на всю экосистему».

**FLEX-IMPL не приземляет код.** Каждый item — МАЙЛСТОУН (repo-план доведён до gate,
репа создана, setup выполнен), а не код-задача. Гранулярные `Pn.m` с кодом живут в
**исполняемых repo-планах** (`corex/plans/COREX-CORE` — уже создан, 26 items; будущие
`flex/*/plans`, `flexcrm-app/plans`, …), где закрытие сверяется с git ТОЙ репы. Так §3.1
не нарушен: git-верификация делегирована аудиту repo-плана, а не выдумывается в vault.

Фундамент (roadmap-фазы P0/P1 = B-10 примитивы+модули, B-11 tenancy+auth) уже покрыт
планом **COREX-CORE** — P0 этого плана делегирует его, не дублируя. Дизайн FlexCRM ещё
не финализирован (`FLEXCRM-W2` 🟡 in progress) — это ГЕЙТ перед импл-фазой FlexCRM (P2).

Source истины порядка/зависимостей — roadmap §4/§3.2/§3.3 (CRM-first D6, core-first,
loop-back). DB-контракт всех migration-фаз — `plans/flex/60_contracts/DB_SCHEMA.md`.
**Vault не перемещать** — repo-планы ссылаются на его пути в Required Reads.

## 2. Execution Rules

- **Program-семантика:** item закрывается, когда его **Validation** выполнена (аудит
  делегированного repo-плана GREEN · репа существует · design-gate закрыт). Код в vault
  НЕ пишется.
- **Исполнение item'а — НЕ `task:plan-exec` в vault** (кода тут нет), а команда в целевой
  репе: `task:plan-design` нового repo-плана → `task:plan-exec`/`task:plan-audit` там;
  либо ручной setup (фаза S); либо `task:dev` для OSS-пакета. См. поле **Code Guidance**
  каждого item'а — там протокол делегирования.
- Один item за раз; статус (Phase Status + Status Board здесь) обновляется ДО начала (🟡)
  и по завершении (🟢/🟠). Статусы — 6 канонических строк байт-в-байт (plan-protocol §5).
- **Phase-first:** фазы закрываются последовательно. Исключение (Routing §3): P1 (OSS)
  разрешено вести параллельно P0 — оба предусловие P2, но закрываются в порядке P0→P1.
- **Loop-back (roadmap §3.2):** repo-планы живые. Нехватка ядра в ходе FlexCRM → новый
  item в COREX-CORE (core-first, тег, симлинк), не импровизация. Program-строка держит пару.
- План разошёлся с roadmap/каноном → не импровизировать, эскалировать (Escalation Needed + 🔴).
- После закрытия item/фазы — перезаписать `plans/FLEX-IMPL/handoff.md` целиком.

## 3. Routing

Отклонения от SSOT-матрицы plan-protocol §7:
- **Природа плана:** программный (координация), не код. Внутри-item «exec» — это запуск
  `task:plan-design`/`task:plan-exec`/`task:plan-audit` в ЦЕЛЕВОЙ репе; модель/effort
  выбираются там по её Routing. Здесь драйв программы — opus/high (зависимости, гейты).
- **Нет composer-гейта на этом уровне:** package/app-гейты (`composer lint&analyse&test`,
  реальная PG) живут в repo-планах; FLEX-IMPL проверяет их РЕЗУЛЬТАТ (аудит GREEN).
- **Параллелизм P0∥P1** разрешён (см. Execution Rules), вопреки строгой последовательности §7.

## 4. Phase Index & Status Board

| Phase | Title | Items 🟢/всего | Status |
|:--|:--|:--|:--|
| P0 | CoreX foundation & auth/tenancy — делегирует COREX-CORE (roadmap P0/P1, B-10/B-11) | 0/2 | ⬜ Not started |
| P1 | OSS-выносы: flex/workflow · flex/attributes · flex/import-export (roadmap P2, B-12/B-15/B-18) | 0/3 | ⬜ Not started |
| P2 | FlexCRM-приложение: setup + сущности/commerce/agents (roadmap S+P3, B-13/B-14) | 0/5 | ⬜ Not started |
| P3 | Satellite-контракты Vaulter/Chatom (roadmap P4, B-16) | 0/2 | ⬜ Not started |
| P4 | FlexStore + FlexCMS (roadmap P5, B-14 commerce / B-17 components) | 0/4 | ⬜ Not started |

## 5. Decision Log

| D# | Дата | Решение | Почему |
|:--|:--|:--|:--|
| D1 | 2026-07-08 | Ввод «FLEX-IMPL» → **программный (координационный) мастер-план** в vault; исполнение кода делегируется repo-планам. Roadmap-проза формализована в plan-protocol. | Единый Status Board/handoff над кросс-реповой реализацией отсутствовал; §3.1 запрещает git-исполняемый план в vault — программный индекс его не нарушает (кода не приземляет). |
| D2 | 2026-07-08 | Foundation (roadmap P0/P1) НЕ дублируется — P0 делегирует уже существующий `corex/plans/COREX-CORE` (создан 2026-07-06, 26 items). | Дублировать granular code-items в vault запрещено §3.1; COREX-CORE — source истины фундамента. |
| D3 | 2026-07-08 | Порядок P0→P1→P2→P3→P4 (D6 CRM-first, core-first). P1 (OSS) может идти параллельно P0, но P0∧P1 — предусловие P2. | roadmap §4/§3.2: фундамент-first, OSS не блокируют corex жёстко до P3. |
| D4 | 2026-07-08 | Импл-часть FlexCRM (P2.3+) гейтится завершением дизайна `FLEXCRM-W2` (сейчас 🟡). | W2 закрывает B-13-гапы (tasks-collab, соц-контур, process-studio, agentic surface); код по незакрытому дизайну = переделка. |
| D5 | 2026-07-08 | Item-исполнение — командами ЦЕЛЕВОЙ репы (`task:plan-design` нового repo-плана / ручной setup / `task:dev`), НЕ `task:plan-exec FLEX-IMPL …`. | Program-план координирует, gate = аудит repo-плана GREEN; в vault нет git-фактов кода. |
| D6 | 2026-07-08 | Имя нового FlexCRM-app репо НЕ фиксируется здесь (открытое решение шага S, roadmap §7) — P2.1 включает выбор имени/расположения. | Пользовательское решение на setup; program-план не предопределяет топологию репы. |

## 6. Update Log

| Дата | Кто (role/model) | Что |
|:--|:--|:--|
| 2026-07-08 | issue-planner/opus | План создан как program-index над roadmap P0–P5 (5 фаз, 16 items-майлстоунов). Foundation делегирован COREX-CORE (не дублируется). Vault `plans/ACTIVE.md` переключён THEMEON→FLEX-IMPL. |
| 2026-07-11 | plan-design/fable | План superseded → plans/archive/ (консолидация FLEX-2: carry-over открытых items; done-факты сохранены) |
