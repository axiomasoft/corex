# 2026.09.19-№1-COREX-PLATFORM-HARDENING — План №1 — Платформенное доведение CoreX до коммерческого 1.0

## 0. Meta

| Поле | Значение |
|:--|:--|
| Plan ID | 2026.09.19-№1-COREX-PLATFORM-HARDENING |
| Short ID | PLAN2 |
| Title | План №1 — Платформенное доведение CoreX до коммерческого 1.0 |
| Layout | v2 |
| Document Type | Executable Master Plan |
| Authoring Model | claude-opus-5/high (верхний слой, 2026-09-19, provider-attested); P14: gpt-6-astra/high; P15–P17 and DA01–DA14 remediation: gpt-5.6-sol/high (2026-09-20, provider-thread-state); DA09 closure: codex-gpt-6/effort-unverified (2026-09-20, `ROUTE_LAUNCH_MISMATCH` disclosed); roadmap D114: gpt-5.6-sol/high (2026-09-22, provider-thread-state); D118 execution batching: gpt-6-astra (2026-09-23, current session) |
| Repository | corex |
| Related Packages | все 21 каталога `packages/*`; нормативные документы `docs/architecture/**`; корневые гейты `scripts/**`, `tests/Arch/**`, `.github/workflows/**` |
| Execution Mode | phase-first: верхний слой + скелеты 17 фаз; каждая фаза детализируется отдельным `task:plan-design <ID> Pn` перед исполнением |
| Target Operator Classes | D118: Composer 2.5 / Grok 4.6 for bounded leaves, Grok 4.7 for integration, GPT-5.6 Sol for DDL/durable/security and phase qualification; final freeze uses Codex GPT-6 Sol or Cursor GPT-5.6 Sol xhigh/max |
| Approval Owner | Dmitry Vostrikov |
| Home | repo:corex (локальный план; Brain sync не выполнялся) |
| visibility | private |
| supersedes | — |
| Paused By | — |

### Update Log

| Дата | Кто (role/model) | Что |
|:--|:--|:--|
| 2026-09-25 | owner + plan-maintenance / codex-gpt-5.6-sol/high | D127: sealed migration repairs only four invalid E14a invocation digests derived from unchanged membership; Task owner receives the bounded compatible bugfix. |
| 2026-09-25 | owner + plan-maintenance / codex-gpt-6/high | D125 supersedes D124 for P14.3: item-specific `G-DDL-P14.3` prevents collision with P13.5 while preserving branch, scope and local-only boundary. |
| 2026-09-25 | owner + plan-maintenance / codex-gpt-6/high | D124: P14.3 G-DDL limited to owner-session/local-source against `corex_test`; production migration and rollout remain forbidden. |
| 2026-09-25 | owner + plan-maintenance / codex-gpt-6-sol/xhigh | D122: P13.12 G-API ограничен локальным checkout через owner-session/local-source; исходные branch, scope, Files и Validation сохранены. Grant не выдан. |
| 2026-09-23 | plan-exec / cursor-composer-2.5/medium | P7.6–P7.7 закрыты: AuthOptions, identity и access/matrix без `config()` — детали см. phases/P7/P7.6.state.md и P7.7.state.md |
| 2026-09-23 | owner + plan-maintenance / gpt-6-astra | D118: post-P6 execution grouped into bounded batches with artifact inputs, exact model pins and intermediate qualification; P7.1 is the live frontier. |
| 2026-09-23 | owner + plan-exec / gpt-6-sol/medium | D117: владелец разрешил текущую независимую GPT-6-сессию только для P6.6 вместо pinned `gpt-5.6-sol/high`; остальные условия item сохранены. |
| 2026-09-22 | owner + plan-design / codex-gpt-5.6-sol/high | D114: D112 projected to exact Codex selectors; current P5.13 launch is `gpt-5.6-terra/medium`, with no change to scope, effort, review or owner gates. |
| 2026-09-22 | owner + plan-design / current session | D112: для всех оставшихся execution scopes зафиксированы exact native model, effort и independent qualification boundary; executor fallback запрещён. |
| 2026-09-22 | owner + plan-design / current session | D111: P1–P5 execution audit RED; P5.13–P5.18 remediation added, P6 blocked by P5.18, live execution routing changed from Composer-only to risk-routed Composer/Grok 4.7/Sol. |
| 2026-09-21 | owner / human | D109: выдан `[OWNER-GATE:G-API]` на P4.7 (флип `DataScope`, `main`, scope по `P4.7.md`). |
| 2026-09-21 | plan-exec / cursor-composer-2.5/medium | P2.5 закрыт: `verdicts.csv` (888), `VerdictRules` + CLI `verdicts`, 27 MOVE `corex/core`; receipt §P2.5 |
| 2026-09-21 | plan-exec / cursor-composer-2.5/medium | P1.5 закрыт: `api-policy.md` — три носителя, граница `@internal` vs пакет (D12), BC-список; детали см. phases/P1/P1.5.state.md |
| 2026-09-21 | plan-exec / cursor-composer-2.5/medium | Execution Rules: handoff и Next в чате обязаны указывать native `--model` вместе с class/effort; handoff P1.5 — строка Native model. |
| 2026-09-21 | plan-exec / cursor-composer-2.5/medium | Execution Rules + handoff: блок **⚠ LAUNCH** в начале handoff; завершение plan-exec в чате обязано копировать LAUNCH (class/effort/`composer-2.5`/полная `agent` команда). |
| 2026-09-20 | plan-designer / gpt-6-astra/high | P14: 21 bounded items, D74–D84, Q4/default budgets, transfer limits/public export port, SSRF/signing/replay and gated schemas/wiring. Design only; Next P15. |
| 2026-09-20 | plan-designer / gpt-5.6-sol/high | P15: 14 bounded items, D85–D88 accepted, D89–D90 proposed owner branches, complete wakeup/core FQCN mappings and negative completeness proof. Perplexity used; design only; Next P16. |
| 2026-09-20 | plan-designer / gpt-5.6-sol/high | P16: 13 bounded items, D91–D94; producer-adjusted API profile C, stable-FQCN physical moves, panels no-move verdict and dev-only Testing boundary. Perplexity used; design only; Next P17. |
| 2026-09-20 | plan-designer / gpt-5.6-sol/high | P17: 12 bounded items, D95–D97; 25+2 executable DoD, derived pristine release cohort, external fixtures/consumer/profiles/upgrade and sealed forward-only G-RELEASE publication. Perplexity used; design only; Next Stage 2 whole-plan audit. |
| 2026-09-20 | plan-designer / gpt-5.6-sol/high | RED-audit remediation DA01–DA14: D98–D108, owning/consumer contracts, dependencies, bounded Files and validation witnesses reconciled. Design only; no grants, execution or repeat audit. |
| 2026-09-20 | plan-designer / gpt-5.6-sol/high | Focused corrective pass over DA01–DA14 remediation: exact decision/item consumers, six P5 witnesses, P7.12 proof split, D107 owner, D108 manifest, roadmap and handoff reconciled. Design only; no product code, grants, execution or repeat audit. |
| 2026-09-20 | plan-designer / codex-gpt-6/effort-unverified | DA09: `CoreOptions` aligned as exact COMPOSITION across D101, P6.2, P7.1 and research P6/P7; independent bounded reviewer GREEN. Stage 2 GREEN; design only, owner gates remain pending. Route effort unavailable because the prose owner request carried no exact Task invocation line. |

## 1. Context

Задача — превратить CoreX из «набора работающих пакетов» в **сознательно спроектированную
платформу уровня 0**, пригодную к коммерческой эксплуатации в продакшене и к длительному
использованию FlexCRM/FlexCMS как потребителями, не владельцами ядра.

Вход — два внешних аудита, приложенных владельцем: `audits/2026-09-19-perplexity-3.md`
(архитектурный overlay второй итерации) и `audits/corex-api-shopify-analysis.md`
(API крупных платформ). Оба разобраны построчно: каждая идея либо подтверждена чтением кода
и внешним первичным источником, либо принята с изменением, либо отвергнута. Матрица —
`findings/audit-verdicts.md`, измеренная база — `research/00-baseline-metrics.md`, внешние
подтверждения — `research/perplexity-2026-09-19.md` (19 успешных запросов; первичные источники
Shopify, Stripe, GitLab, Atlassian, Grafana, Salesforce, Symfony, Drupal, Shopware, Laravel,
PostgreSQL, Standard Webhooks, OpenTelemetry).

Аудиты имеют статус **non-normative сырья**: в них есть ложные предпосылки (например,
`RecordRules` в `corex/auth` не существует; `corex/search-intl` не владеет SQL-репозиторием
saved views; разделение provider/hydration в поиске уже выполнено) и предложения, вредные
именно для self-hosted продукта (date-based версии внешнего API как основной механизм
совместимости). Копировать код и формулировки из `audits/*` запрещено — нормативен только
текст, произведённый фазами этого плана.

Сверх аудитов план несёт собственные находки, которых нет ни в одном из них
(`findings/audit-verdicts.md` §5, префикс `N`): отсутствие инъектируемого Clock при 151 вызове
`now()`, неполный контур `composer test:packages` (17 пакетов из 21), корневой PHPStan на 7
пакетах из 21, `extra.corex` лишь в 9 пакетах и только с ключом `module`, полное отсутствие
outbox и webhook-поверхности, пакет `packages/api` без собственной регистрации маршрутов.

План завершается измеримым Definition of Done 1.0: назначение каждого пакета — одно
предложение, публичная поверхность под автоматическим BC-гейтом, у каждой таблицы владелец,
конфигурация типизирована и валидируется, durable-сообщения сериализуемы и версионированы,
внешний API — самостоятельный продукт с профилями версий, идемпотентностью, курсорной
пагинацией и стабильными кодами ошибок, а универсальность доказана внешними extension-фикстурами
и не-CRM потребителем.

## 2. Execution Rules

- **Жизненный цикл плана — три стадии строго по порядку (D14):**
  1. **Детализация всех 17 фаз.** Каждая фаза разворачивается отдельным вызовом
     `task:plan-design <ID> Pn`; модель прохода выбирается по риску фазы из таблицы §3.3.
  2. **Общий аудит плана** — `task:plan-audit <ID> design` на frontier/high, свежим корнем
     по `cold-start-root: whole-plan-independent-audit`. Аудит проверяет план как целое:
     сквозную согласованность контрактов между фазами, отсутствие дублей и противоречий
     между `research/Pn-design.md`, полноту покрытия матрицы вердиктов и реалистичность
     объявленных зависимостей и owner-гейтов.
  3. **Исполнение items** — `task:plan-exec <ID> Pn.m`, и только после того, как аудит
     второй стадии закрыт.

  Ни один `task:plan-exec` не запускается, пока не детализирована последняя фаза и не закрыт
  общий аудит. Детализированная фаза с готовыми items **не даёт** права начать исполнение:
  это единственное отступление плана от дефолтного правила протокола «готовый item исполняется».
- Один item за раз; статус живёт в generated state, фаза закрывается по фактическим receipt'ам.
- Статусы — 6 канонических строк байт-в-байт (plan-protocol §4). GREEN только при зелёных
  Validation-носителях; пропущенная или недоступная проверка раскрывается, а не считается зелёной.
- **Исполнение items маршрутизируется по риску (D111).** Composer 2.5 — только
  ограниченные листовые правки; Grok 4.7 — cross-package/provider/public-surface integration;
  GPT-5.6 Sol — durable, DDL, security, public-contract и сводная квалификация. Каждая item-спека
  ОБЯЗАНА называть точный нормативный параграф; спека без него — дефект дизайна.
- **Бюджет контекста item'а — 200K токенов.** Жёсткие лимиты спеки: ≤14 Required Reads,
  ≤2500 строк обязательного чтения, ≤8 файлов в Files, один пакет (или один пакет + корневой
  гейт) на item. Не влезает — item делится при детализации фазы, а не при исполнении.
- Детализация фазы обязана произвести три носителя: `research/Pn-design.md`,
  `research/Pn-design-rag.md` и item-спеки `phases/Pn/Pn.m.md`. Порядок детализации фаз
  свободен в пределах графа зависимостей `roadmap.md` §2: фаза детализируется, когда
  детализированы фазы, чьи контракты она потребляет.
- **Perplexity (наследуется от PLAN1 D6):** CoreX — внутренняя разработка; у Perplexity не
  спрашивать про CoreX, его устройство, пути, гейты и состояние. Только общие практики,
  конкретные технические вопросы и публичные примеры, нейтральной формулировкой. Внешнее
  подтверждение несёт `RAG:✅`, факт о репозитории — `RAG:—`.
- Нормативные входы каждой фазы: `docs/architecture/**` (создаётся P1), `docs/dev-doctrine.md`,
  `findings/audit-verdicts.md`, `research/00-baseline-metrics.md`. Расхождение плана с кодом —
  эскалация, не импровизация.
- Перед P7–P17 запуском читать `execution-batches.md`: раздел «Как исполнять
  батч», строку своей фазы в карте входов/выходов и строку своей сессии.
  Item `Required Reads` сохраняет приоритет и читается полностью; карта не
  разрешает пропускать обязательные входы или закрывать несколько items одним receipt.
- **Эскизы верхнего слоя обязательны к прочтению при детализации.** `research/01-shapes-runtime.md`
  (P4–P8), `research/02-shapes-platform.md` (P1–P3, P10, P11), `research/03-shapes-api.md`
  (P12–P14) несут форму несущих контрактов, готовые сигнатуры, DDL-эскизы, перечни инвариантов и
  ловушек, а также строки «Сверить с деревом» с точными путями. Детализация фазы читает свой
  раздел и либо принимает форму, доводя её до готового кода в `research/Pn-design.md`, либо
  отклоняет с записью причины в D-лог фазы. Эскиз не является приёмкой: он не проверялся против
  каждого файла и обязан быть сверен по указанным путям.
- **Владение артефактами.** Всё, что план производит для собственного обоснования, живёт внутри
  `plans/<PLAN-ID>/` и никуда больше: `research/` (дизайн фаз и их RAG), `findings/` (разборы и
  receipt'ы), `artifacts/` (сырые внешние ответы с digest'ами, инвентаризация, графы, scorecard),
  `decisions/`, `inputs/` (провенанс входов), `scripts/` (плановые чекеры и сканеры), `bundles/`,
  `journal.jsonl`. Продукт репозитория — и только он — живёт в дереве кода: `docs/architecture/**`,
  гейты в `scripts/`/`tests/`, snapshot'ы схемы внешнего API, `fixtures/`, `CHANGELOG.md`.
  Плановый артефакт в корне репозитория и продуктовый документ внутри папки плана — оба дефект.
- **Внешний RAG сохраняется как артефакт, а не как пересказ.** На каждую детализацию фазы:
  `research/Pn-design-rag.md` (сводка с привязкой к пунктам) плюс `artifacts/Pn-design-rag/`
  с захватом ответов, `manifest.json` (точные строки запросов, статус, первичные источники,
  SHA256 файлов захвата) и честной границей захвата. Верхний слой уже несёт такой артефакт —
  `artifacts/rag-2026-09-19/`, его формат является образцом. Неполученный ответ фиксируется
  статусом, а не заменяется утверждением; пункт, который он должен был подтвердить, несёт
  `[UNVERIFIED]` до добора.
- Внешние входы не дублируются в план: они пинятся путём и digest'ом в `inputs/sources.md`.
  Расхождение digest'а — сигнал перепроверить зависящие вердикты, а не тихо переиспользовать.
- **DB-контракт `FROZEN v1.0`** (`~/Vaults/Brain/05-Projects/01-Products/FlexCRM/plans/flex/60_contracts/DB_SCHEMA.md`):
  ни один item не меняет существующие `packages/*/database/migrations/**` без отдельного owner-gate.
  Новые таблицы вводятся только там, где фаза это явно объявила, и сразу попадают в
  `docs/architecture/table-ownership.md`.
- Доктрина уровней (D70/D91) сохраняется: `corex/*` не зависит от `flex/*`, `corex/core` не
  зависит ни от одного пакета монорепо, flex-литералов в `packages/{core,modules,tenancy,auth,tasks}` нет.
- Закрытие code-item'а — оба лейна (D34): `composer lint:check && composer analyse && composer test`
  плюс `composer test:pg` для items, затрагивающих SQL-пути. Один sqlite-лейн = ложно-зелёное.
- Никаких массовых переносов каталогов до завершения P1–P3: сначала нормативы, измерение и гейты.
- Новые runtime-зависимости запрещены; dev-зависимости (BC-чекер, boundary-анализатор) вводятся
  только названными в решениях items и только в `require-dev`.
- После закрытия item/фазы — перезапись `handoff.md`; журнал append-only.
- **`handoff.md` и Next в чате.** Каждый handoff и рекомендация нового запуска
  несут lifecycle class/effort и native slug **активного provider** из §3.3 и
  `execution-batches.md`. Старое правило «весь implementation → Composer»
  историческое (D118). Блок launch включает `--model <slug>`; строка
  `task:plan-exec …` без модели — дефект. Таблица handoff имеет **Native model**
  рядом с **Model class**. Исполнитель копирует актуальный launch block, а не
  только текст «Next: Pn.m».
- **Завершение `task:plan-exec` в чате.** Последний абзац ответа — секция **LAUNCH** из
  `handoff.md` (таблица class/effort/native model + полная команда `agent … --model …`).
  Пропуск — дефект приёмки сессии наравне с пропуском Validation.

## 3. Routing

Routing покрывает каждый исполнимый item построчно (§3.1). Единицы работы, которые ещё не
стали items, — детализация оставшихся недетализированных фаз — перечислены в §3.1a списком: у них нет
адреса `Pn.m`, и таблица исполнения их выразить не может. §3.2 объявляет обязательный route
исполнения для items каждой будущей фазы; детализация обязана воспроизвести его построчно или
ужесточить с записью причины в D-лог. Ослабление объявленного route запрещено.

Строки §3.1 несут item-level lifecycle class и batch ID; исходный class/effort
может быть ниже native effort D118. Для P7–P17 actual session batching,
native model/effort и read/acceptance recipe определяет
`execution-batches.md` (D118); каждый item всё равно закрывается отдельно.
Стадии 1 и 2 завершены; текущий исполнимый frontier — P7.1.

### 3.1 Исполнимые items

| Batch | Items | Model class/effort | Exec | Review | Почему |
|:--|:--|:--|:--|:--|:--|
| solo | P1.1 | implementation/medium | plan-exec | full | Конституция: 58 статей и 22 анти-цели, на идентификаторы которых ссылаются все остальные документы фазы и шестнадцать фаз плана; ошибка формулировки или ложный `Enforcement` воспроизводится везде. Reuse невозможен: остальные items читают её результат. |
| B1 | P1.2 | implementation/medium | plan-exec | light | recommended: таксономия и её заполнение в 20 `composer.json` — одна механическая правка по готовой таблице §3.3 дизайна; общий контекст с P1.3/P1.4 (все три читают конституцию и базовые метрики) экономит повторное чтение. |
| B1 | P1.3 | implementation/medium | plan-exec | light | recommended: гид имён и реестр переименований опираются на тот же разбор вердиктов и ту же конституцию; ничего не исполняют. |
| B1 | P1.4 | implementation/medium | plan-exec | light | recommended: профили структуры читают те же измеренные числа, что и P1.2; отдельная сессия дала бы третье чтение той же базы. |
| solo | P1.5 | implementation/medium | plan-exec | full | Политика публичной поверхности несёт разделение «`@internal` не является машинной границей» (D12): ошибка здесь заставит P3 построить всегда-зелёный гейт — уже случавшийся в репозитории класс дефекта. |
| solo | P1.6 | implementation/medium | plan-exec | light | Четыре документа политик по четырём параграфам дизайна — самый объёмный item фазы по чтению; присоединение к B1 выводит сессию за объявленный бюджет item'а в 200K (B1 уже несёт три документа и правку 20 `composer.json`), а присоединение к P1.5 подняло бы review всей группы до `full` без причины. |
| solo | P1.7 | implementation/medium | plan-exec | light | Сводный реестр правил строится **чтением** всех созданных документов, поэтому item обязан идти последним и в отдельном запуске: в одной сессии с их написанием реестр отразил бы намерение, а не результат. |
| solo | P2.1 | implementation/medium | plan-exec | light | Сканер — производитель всех последующих артефактов фазы; reuse невозможен. |
| B2 | P2.2 | implementation/medium | plan-exec | light | recommended: три графа читают тот же проход, что построил `files.csv`. |
| B2 | P2.3 | implementation/medium | plan-exec | light | recommended: реестр таблиц читает колонку `tables` и те же миграции. |
| B2 | P2.4 | implementation/medium | plan-exec | light | recommended: реестр ключей читает `config_reads` того же инвентаря. |
| solo | P2.5 | implementation/medium | plan-exec | light | Вердикты — отдельный проход суждения (D15); в одной сессии с измерением они отразили бы намерение. |
| solo | P2.6 | implementation/medium | plan-exec | light | Scorecard читает все предыдущие артефакты и обязан идти последним. |
| solo | P3.1 | implementation/high | plan-exec | full | Корневой PHPStan задаёт, какие пакеты считаются проанализированными; дырка здесь — ложно-зелёный N03 на 14 фаз вперёд. |
| solo | P3.2 | implementation/high | plan-exec | full | Tokenizer — производитель рёбер для R4; без него P3.3 наследует G-GATE-BLIND. Reuse невозможен. |
| solo | P3.3 | implementation/high | plan-exec | full | R4/R4b/R5 с различимыми сообщениями (D12); ошибка диагностики — всегда-зелёный firewall. |
| solo | P3.4 | implementation/high | plan-exec | full | Единственная новая dev-зависимость и CI на PHP 8.4 при runtime 8.3–8.5 (D18). |
| solo | P3.5 | implementation/high | plan-exec | full | Гейт сериализуемости — вход P4; пропуск promoted properties = ложно-зелёное. |
| B4 | P3.6 | implementation/high | plan-exec | full | recommended: два строковых гейта и общий Arch-харнесс посадок. |
| B4 | P3.7 | implementation/high | plan-exec | full | recommended: Arch-инварианты и каталог таблиц; тот же SourceScanner. |
| solo | P4.1 | implementation/high | plan-exec | full | Транзитная форма `DataScope` — фундамент всей миграции фазы (D21): её ошибка воспроизводится во всех шести последующих items. Reuse невозможен: остальные items читают её результат. |
| solo | P4.2 | implementation/high | plan-exec | full | Контракт восстановления контекста — публичная поверхность ядра и единственное место, где сверяется соответствие скоупа активному аккаунту (D22); ошибка даёт молчаливую работу в чужом аккаунте. |
| B5 | P4.3 | implementation/high | plan-exec | full | recommended: два пакета `src`, читающие `$scope->context`, мигрируются по одному правилу §3.2 дизайна; общий контекст чтения (контракт restorer'а + форма замены) экономит повторное чтение. |
| B5 | P4.4 | implementation/high | plan-exec | full | recommended: та же замена в единственной производственной точке конструирования; отдельная сессия дала бы второе чтение того же параграфа. |
| B6 | P4.5 | implementation/high | plan-exec | full | recommended: механическая замена формы конструирования в тестах трёх поисковых пакетов; `src` не меняется, поведение не затрагивается. |
| B6 | P4.6 | implementation/high | plan-exec | full | recommended: та же механическая замена в тестах `intl` и `transfer`; общий с P4.5 параграф §3.1 и общая итоговая проверка полноты. |
| solo | P4.7 | implementation/high | plan-exec | full | Единственный ломающий item плана на сегодня: меняется контракт `shared-data-foundation/1` под owner-gate G-API; объединение с чем-либо скрыло бы гейт внутри батча. |
| solo | P4.8 | implementation/high | plan-exec | full | Codec — durable-формат с версией; ошибка «неизвестная версия трактуется как текущая» необратима для уже записанных payload'ов. |
| solo | P4.9 | implementation/high | plan-exec | full | Объявление зависимости `psr/clock` и публичная привязка контракта времени (D20): ошибка выбора дефолтной реализации рассинхронизирует два часовых домена во всём дереве. |
| solo | P4.10 | implementation/high | plan-exec | full | Durable-пути `wakeup`: lease и backoff; ошибка меняет семантику повторов, а признанный предел D23 обязан быть записан именно здесь. |
| solo | P4.11 | implementation/high | plan-exec | full | Доказательство изоляции — тест, который обязан уметь покраснеть; присоединение к продуктовому item'у создало бы соблазн «подправить тест» вместо фиксации находки. |
| solo | P4.12 | implementation/high | plan-exec | full | Гейт по определению может дать ложно-зелёное; требует сначала измерения и allowlist'а, потом включения правила. |
| solo | P5.1 | implementation/high | plan-exec | light | Документ политики — единственный item фазы без кода (§3.2 исключение); объединение с кодовым item'ом подняло бы review всей группы до `full` без причины, а сам документ является входом для остальных одиннадцати и обязан идти первым. |
| B7 | P5.2 | implementation/high | plan-exec | full | recommended: словарь сообщений и реестр — чистый код значений без обращения к БД; реестр потребляет `MessageContract`, произведённый здесь же, и обе спеки читают §2–§3 одного дизайна с одной формой приёмки (`composer test` + `analyse` пакета `core`). |
| B7 | P5.3 | implementation/high | plan-exec | full | recommended: тот же параграф дизайна, те же Required Reads, та же проверка; отдельная сессия дала бы второе чтение `MessageContract` ради шести тестовых случаев. |
| solo | P5.4 | implementation/high | plan-exec | full | Единственный DDL-item фазы и единственный носитель owner-gate `G-DDL`: объединение с чем-либо скрыло бы гейт внутри батча. Он же объявляет весь блок `corex.messaging`, который остальные items только читают. |
| solo | P5.5 | implementation/high | plan-exec | full | Инвариант «вставка в одной транзакции с бизнес-изменением» — единственная причина существования фазы; его доказательство требует отдельного pg-теста отката, и ошибка здесь обесценивает весь остальной механизм. |
| solo | P5.6 | frontier/high | plan-exec | full | Resume/recovery on GPT-5.6 Sol after missing terminal evidence. Lease/fence/backoff is a durable boundary; an independent pass reruns acceptance and emits the only valid terminal result. |
| solo | P5.7 | implementation/high | plan-exec | full | Кооперативная остановка по сигналу — отдельный класс риска (обработчик сигнала, прерываемый `sleep`, бюджет таймингов), не пересекающийся с механикой захвата; присоединение к P5.6 вывело бы сессию за бюджет 200K. |
| solo | P5.8 | implementation/high | plan-exec | full | Атомарность отметки и бизнес-эффекта — второй несущий инвариант фазы, с собственным доказательством гонки на двух соединениях; по данным зависит только от P5.4 и исполним независимо от ветки outbox. |
| B8 | P5.9 | implementation/high | plan-exec | full | recommended: retention и наблюдаемость — две read/maintenance-поверхности над одной таблицей, обе консольные, обе на тех же pg-фикстурах и том же `FrozenClock`; общий контекст чтения экономит повторную подготовку данных во всех четырёх состояниях строки. |
| B8 | P5.10 | implementation/high | plan-exec | full | recommended: тот же набор состояний строки, та же форма команды, та же проверка; отдельная сессия дала бы второе наполнение таблицы ради пяти утверждений. |
| solo | P5.11 | implementation/high | plan-exec | full | Гейт по определению может дать ложно-зелёное; приёмка требует двух посаженных нарушений и снятия обеих посадок — процедура, которую нельзя делить с продуктовым item'ом. Единственный item фазы, пишущий в корневой `tests/`. |
| solo | P5.12 | implementation/high | plan-exec | full | Доказательства обязаны уметь покраснеть; присоединение к продуктовому item'у создало бы соблазн «подправить тест» вместо фиксации находки — та же причина, что у P4.11. Требует готовыми все четыре механизма фазы, поэтому идёт последним. |
| solo | P5.13 | implementation/medium | plan-exec | full | Composer 2.5: bounded test-harness repair with exact two-file behavior and deterministic package/root witnesses. |
| solo | P5.14 | frontier/high | plan-exec | full | Grok 4.7: cross-package provider composition across wakeup and notifications; reverse-consumer qualification is mandatory. |
| solo | P5.15 | frontier/high | plan-exec | full | Grok 4.7: public/internal surface classification changes SemVer metadata and the package firewall. |
| solo | P5.16 | frontier/xhigh | plan-run | full | GPT-5.6 Sol, G-DDL-P5R: configurable migration identity and raw PostgreSQL identifiers are a durable schema boundary. |
| solo | P5.17 | implementation/medium | plan-exec | light | Composer 2.5: exact three-file formatting-only repair; no semantic discretion. |
| solo | P5.18 | frontier/xhigh | plan-exec | full | GPT-5.6 Sol: independent joined qualification; no product repair, all root/package gates and carrier reconciliation. |
| solo | P6.1 | implementation/high | plan-exec | full | Карта 109 файлов — вход всех последующих гейтов и P15; ошибка классификации воспроизводится в R-Z0/R-Z1. Reuse невозможен. |
| solo | P6.2 | implementation/high | plan-exec | full | Arch-гейт зон по определению может быть всегда-зелёным; три посадки и поглощение MessagingZoneTest не делятся с документом. |
| solo | P6.3 | implementation/high | plan-exec | full | Литмус с жёстким условием 3 (D34); allowlist Eloquent обязан уметь не расти. Отдельно от P6.2: другой набор посадок и другой Required Reads. |
| solo | P6.4 | implementation/high | plan-exec | full | Кандидаты §7 и продуктовый вердикт Departments — суждение, не код; в одной сессии с гейтом отразило бы намерение. Review `full` сохранён: §3.2 запрещает ослаблять route фазы. |
| solo | P6.5 | implementation/high | plan-exec | full | Staging N09 меняет composer.json ядра и вводит гейт, который может быть всегда-зелёным; полный вынос сломал бы test:packages (D33). |
| solo | P6.6 | frontier/medium | plan-exec | full | D117: owner разрешил текущую `gpt-6-sol/medium`-сессию; свод обязан уметь покраснеть, review `full` сохранён. |
| solo | P7.1 | implementation/medium | plan-exec | light | Шаблон и CoreOptions — вход всех остальных; reuse невозможен. |
| solo | P7.12 | implementation/medium | plan-exec | full | D100 proof отделён от восьмифайлового P7.1: behavior witnesses должны уметь покраснеть без product writes. |
| solo | P7.2 | implementation/medium | plan-exec | light | 75 чтений tenancy и три policy (D41); объединение с инъекцией HTTP скрыло бы объём провайдера. |
| E07d | P7.3 | implementation/medium | plan-exec | light | recommended: три item'а инъекции tenancy читают один `TenancyOptions` и один провайдер; общий контекст экономит повторное чтение §6–§7. |
| E07d | P7.4 | implementation/medium | plan-exec | light | recommended: та же фабрика, другая группа файлов; порядок внутри батча — по Files, не по данным. |
| E07d | P7.5 | implementation/medium | plan-exec | light | recommended: замыкает tenancy-инъекцию; команды остаются composition. |
| E07e | P7.6 | implementation/medium | plan-exec | light | recommended: AuthOptions и его потребители — один пакет, один параграф §8. |
| E07e | P7.7 | implementation/medium | plan-exec | light | recommended: семь читателей `panel` после готового DTO; отдельная сессия дала бы второе чтение §8. |
| E07f | P7.8 | implementation/medium | plan-exec | light | recommended: малые пакеты по одному шаблону §9 после P7.1; разный Files, общий fromArray. |
| E07f | P7.9 | implementation/medium | plan-exec | light | recommended: тот же шаблон, пакет `media`; не сливать с tenancy. |
| solo | P7.10 | implementation/medium | plan-exec | full | Секреты и redaction (§3.2 исключение): команда обязана уметь не напечатать plaintext. |
| solo | P7.11 | implementation/medium | plan-exec | full | Ужесточение §3.2: блокирующий гейт по определению ложно-зелёный без посадки; observational P3.6 нельзя делить с product-item. |
| solo | P8.1 | implementation/medium | plan-exec | light | Контракт и каталог — вход всех registrar'ов; reuse невозможен. |
| E08b | P8.2 | implementation/medium | plan-exec | light | recommended: два item'а tenancy register() читают один каталог и один провайдер. |
| E08b | P8.3 | implementation/medium | plan-exec | light | recommended: та же форма §5.1, другая группа модулей; optional-предикаты. |
| solo | P8.4 | implementation/medium | plan-exec | full | Порядок bootstrapper'ов tenancy (§3.2 исключение): нарушение ломает изоляцию молча. |
| solo | P8.5 | implementation/medium | plan-exec | light | Другой пакет; не сливать с tenancy. |
| solo | P8.6 | implementation/medium | plan-exec | full | Гейт по определению ложно-зелёный без посадки; ratchet нельзя делить с product-item. |
| solo | P9.1 | implementation/high | plan-exec | full | Атомарная карта и baseline — вход всех переносов; reuse невозможен. |
| solo | P9.2 | implementation/high | plan-exec | full | Bootstrapper order/lifetime и composition seams не делятся с доменными переносами. |
| solo | P9.3 | implementation/high | plan-exec | full | 51 файл Membership/Central и смена внутренних namespaces исчерпывают item-бюджет. |
| E09d | P9.4 | implementation/high | plan-exec | full | recommended: три непересекающихся группы читают одну compatibility map/PSR-4 проекцию. |
| E09d | P9.5 | implementation/high | plan-exec | full | recommended: OIDC/Identity не пересекается с Files P9.4/P9.6, но делит baseline. |
| E09d | P9.6 | implementation/high | plan-exec | full | recommended: три готовых context-ветки и та же Models/Events compatibility matrix. |
| solo | P9.7 | implementation/high | plan-exec | full | Optional cross-package boundary и негативную посадку не сливать с product moves. |
| solo | P9.8 | implementation/high | plan-exec | full | Final proof/ratchet обязан уметь покраснеть и исправлять owning item, а не expected output. |
| E10a | P10.1 | implementation/high | plan-exec | full | Composition меняет fail-closed поведение пакета без modules; отдельный baseline для остальных items. |
| E10a | P10.2 | implementation/high | plan-exec | full | Stable identity и snapshot — additive public surface, от которой зависят DDL и evolution. |
| solo | P10.3 | implementation/high | plan-exec | full | Единственный G-DDL item фазы; schema, merge и cache authority проверяются вместе. |
| solo | P10.4 | implementation/high | plan-exec | full | Dual-write/backfill живых значений и rollback исчерпывают item-бюджет. |
| solo | P10.5 | implementation/high | plan-exec | full | Storage routing и index DDL имеют отдельное измеримое PostgreSQL-доказательство. |
| E10e | P10.6 | implementation/high | plan-exec | full | Восемь security checks требуют независимой negative matrix на двух engines. |
| E10e | P10.7 | implementation/high | plan-exec | full | Transactional outbox и privacy payload — отдельная durable boundary. |
| solo | P10.8 | implementation/high | plan-exec | full | Final moves/ratchets обязаны краснеть независимо от product implementation. |
| solo | P11.1 | implementation/high | plan-exec | full | Identity/version primitives — вход compiler и всех durable consumers; они закрепляют additive public surface. |
| E11b | P11.2 | implementation/high | plan-exec | full | recommended: compiler и runtime publication разделяют одни canonical bytes/fingerprint и один набор corrupt-artifact fixtures. |
| E11b | P11.3 | implementation/high | plan-exec | full | recommended: тот же compiled artifact, но отдельный runtime item с freeze/worker-boundary доказательством. |
| solo | P11.4 | implementation/high | plan-exec | full | Workflow имеет свой existing durable carrier и compatibility matrix; с wakeup Files не пересекается. |
| solo | P11.5 | implementation/high | plan-exec | full | Wakeup handler registry, envelope, quarantine и read-only preflight образуют одну atomic durable boundary. |
| E11e | P11.6 | implementation/high | plan-exec | full | recommended: tasks-workflow и notifications — два независимых consumer proof на одном готовом P11.5 contract. |
| E11e | P11.7 | implementation/high | plan-exec | full | recommended: второй consumer закрывает legacy/mismatch matrix, не деля Files с P11.6. |
| solo | P11.8 | implementation/high | plan-exec | full | Contract suite и ratchet обязаны читать фактический результат всех веток и идти последним. |
| solo | P12.1 | implementation/medium | plan-exec | full | Public error vocabulary и compatibility policy — вход API/P13; P6 zone gate обязан увидеть новые core paths. |
| E12b | P12.2 | implementation/medium | plan-exec | full | recommended: API и attributes потребляют один descriptor matrix; API item отдельно доказывает неизменность wire. |
| E12b | P12.3 | implementation/medium | plan-exec | light | recommended: bounded package proof на той же error matrix; Files с API не пересекаются. |
| E12c | P12.4 | implementation/medium | plan-exec | light | recommended: observer contract/null/PSR-3 и OTel adapter делят event fixtures; contract идёт первым и закрывается отдельно. |
| E12c | P12.5 | implementation/medium | plan-exec | full | recommended: optional SDK, async context/link и cardinality gate — integration boundary; не делит Files с observer DTO. |
| E12d | P12.6 | implementation/medium | plan-exec | full | Every-audit-record invariant меняет security/compliance seam и должен быть доказан на direct caller. |
| E12d | P12.7 | implementation/medium | plan-exec | full | Nested session, failed-login cleanup, expiry/revocation и write policy требуют отдельного PG/security прохода. |
| solo | P12.8 | implementation/medium | plan-exec | full | Ratchets и final proof обязаны краснеть независимо от product items и идти последними. |
| E13a | P13.1 | implementation/high | plan-exec | full | recommended: compiled descriptor — producer всей фазы; legacy defaults и mode matrix должны быть закреплены до любого transport/runtime consumer. |
| E13a | P13.2 | implementation/high | plan-exec | full | recommended: shared invoker и profile читают один P13.1 descriptor/P11 fingerprint; Files различны, общий контекст экономит повторное чтение registry/tool seams. |
| E13b | P13.3 | implementation/high | plan-exec | full | recommended: profile/options/capability projection строится поверх того же descriptor и остаётся независимо исполнимым под current dependency checks. |
| E13b | P13.4 | implementation/high | plan-exec | full | recommended: package routes меняют внешний HTTP mount и async/deprecation semantics; отдельный route table proof не смешивается с product primitives. |
| solo | P13.5 | implementation/high | plan-exec | full | recommended: единственный P13 item с G-DDL; tenant table, lease/fence и rollback нельзя скрывать внутри батча. |
| solo | P13.6 | implementation/high | plan-exec | full | recommended: effect-count/replay matrix объединяет готовые invoker/store и требует отдельной reconciliation boundary после возможного unknown effect. |
| solo | P13.7 | implementation/high | plan-exec | full | recommended: durable cursor format, key rotation and ordering version have an independent compatibility/cryptographic proof. |
| solo | P13.8 | implementation/high | plan-exec | full | recommended: five-layer authorization and N08 field leak are a security boundary with cross-transport negative fixtures. |
| E13g | P13.9 | implementation/high | plan-exec | full | recommended: error wire projection and snapshots share the exact P12 error/OpenAPI schemas; serializer closes first. |
| E13g | P13.10 | implementation/high | plan-exec | full | recommended: generator consumes the completed wire/profile schemas in the same session but closes separately with byte/digest proof. |
| solo | P13.11 | implementation/high | plan-exec | full | recommended: compatibility gate must attack the generated baseline independently; planted red fixtures cannot be co-authored with product serializers. |
| solo | P13.12 | implementation/high | plan-exec | full | recommended: sole G-API freeze and final cross-transport/anti-GraphQL ratchets consume every branch and remain isolated from implementation groups. |
| E14a | P14.1 | implementation/high | plan-exec | full | recommended: shared producer/consumer, required reads and validation context; ready subsets permitted. |
| E14a | P14.2 | implementation/high | plan-exec | full | recommended: shared producer/consumer, required reads and validation context; ready subsets permitted. |
| solo | P14.3 | implementation/high | plan-exec | full | recommended: G-DDL schema/connection boundary. |
| E14c | P14.4 | implementation/high | plan-exec | full | recommended: shared producer/consumer, required reads and validation context; ready subsets permitted. |
| E14c | P14.5 | implementation/high | plan-exec | full | recommended: shared producer/consumer, required reads and validation context; ready subsets permitted. |
| solo | P14.6 | implementation/high | plan-exec | full | recommended: durable state machine and PG CAS. |
| E14e | P14.7 | implementation/high | plan-exec | full | recommended: shared producer/consumer, required reads and validation context; ready subsets permitted. |
| E14e | P14.8 | implementation/high | plan-exec | full | recommended: shared producer/consumer, required reads and validation context; ready subsets permitted. |
| E14f | P14.9 | implementation/high | plan-exec | full | recommended: public optional adapter and recovery boundary. |
| E14f | P14.10 | implementation/high | plan-exec | full | recommended: external resource authorization and result access. |
| E14g | P14.11 | implementation/high | plan-exec | full | recommended: shared producer/consumer, required reads and validation context; ready subsets permitted. |
| E14g | P14.12 | implementation/high | plan-exec | full | recommended: shared producer/consumer, required reads and validation context; ready subsets permitted. |
| solo | P14.13 | implementation/high | plan-exec | full | recommended: independent signing/rotation security review. |
| solo | P14.14 | implementation/high | plan-exec | full | recommended: independent SSRF transport security review. |
| E14j | P14.15 | implementation/high | plan-exec | full | recommended: shared producer/consumer, required reads and validation context; ready subsets permitted. |
| E14j | P14.16 | implementation/high | plan-exec | full | recommended: shared producer/consumer, required reads and validation context; ready subsets permitted. |
| solo | P14.17 | implementation/high | plan-exec | full | recommended: destructive retention qualified on isolated stores. |
| solo | P14.18 | implementation/high | plan-exec | full | recommended: compatibility gate and planted failures separate from producer. |
| solo | P14.19 | implementation/high | plan-exec | full | recommended: G-SECRET activation-policy boundary. |
| solo | P14.20 | implementation/high | plan-exec | full | recommended: integrated package qualification after wiring. |
| solo | P14.21 | implementation/high | plan-exec | full | recommended: final qualification joins all branches and documents limits. |
| solo | P15.1 | implementation/medium | plan-exec | full | Public modules FQCN set and nine discovery consumers; G-API-P15. |
| E15b | P15.2 | implementation/medium | plan-exec | light | recommended: independent bounded package rename lane. |
| E15b | P15.3 | implementation/medium | plan-exec | light | recommended: independent bounded package rename lane. |
| E15b | P15.4 | implementation/medium | plan-exec | light | recommended: independent bounded package rename lane. |
| E15b | P15.5 | implementation/medium | plan-exec | light | recommended: independent bounded package rename lane. |
| E15b | P15.6 | implementation/medium | plan-exec | light | recommended: independent bounded package rename lane. |
| E15c | P15.7 | implementation/medium | plan-exec | full | Four public wakeup contracts; G-API-P15. |
| E15c | P15.8 | implementation/medium | plan-exec | full | Four public API classes with wire-invariance proof; G-API-P15. |
| solo | P15.9 | implementation/medium | plan-exec | light | One tenancy result rename with serialization invariant. |
| solo | P15.10 | implementation/medium | plan-exec | full | Five public core FQCN groups; G-API-P15. |
| E15f | P15.11 | implementation/medium | plan-exec | full | Conditional package identity branch; G-PKG-API. |
| E15f | P15.12 | implementation/medium | plan-exec | full | Conditional package identity branch; G-PKG-AUTH. |
| solo | P15.13 | implementation/medium | plan-exec | full | recommended sequential closure: registry/surface reconciliation. |
| solo | P15.14 | implementation/medium | plan-exec | full | recommended sequential closure: integrated and negative gate proof. |
| E16a | P16.1 | implementation/medium | plan-exec | light | recommended: stable-FQCN PSR-4 move pattern for one profile-B package. |
| E16a | P16.2 | implementation/medium | plan-exec | light | recommended: stable-FQCN PSR-4 move pattern for one profile-B package. |
| E16a | P16.3 | implementation/medium | plan-exec | light | recommended: same move pattern plus bounded Tasks harness preparation. |
| E16b | P16.4 | implementation/medium | plan-exec | light | recommended sequential API profile-C rollout: operation surface first. |
| E16b | P16.5 | implementation/medium | plan-exec | light | recommended sequential API profile-C rollout: Cost/Bulk consume moved surface. |
| E16b | P16.6 | implementation/medium | plan-exec | light | recommended sequential API profile-C rollout: Webhooks completes package. |
| E16c | P16.7 | implementation/medium | plan-exec | light | recommended: bounded profile-B root cleanup with stable FQCN. |
| E16c | P16.8 | implementation/medium | plan-exec | light | recommended: bounded profile-B move using the same manifest/autoload proof. |
| solo | P16.9 | implementation/medium | plan-exec | light | Panels no-move/docs item has no shared product setup. |
| solo | P16.10 | implementation/medium | plan-exec | light | Atomic cross-package dev-autoload cutover; partial execution is broken. |
| solo | P16.11 | implementation/medium | plan-exec | light | Read-only no-move census and one architecture growth map. |
| solo | P16.12 | implementation/medium | plan-exec | light | recommended sequential closure: path-bearing metadata reconciliation. |
| solo | P16.13 | implementation/medium | plan-exec | light | recommended sequential closure: integrated planted-negative proof. |
| solo | P17.1 | implementation/high | plan-exec | full | Release cohort/candidate is the immutable input to every other acceptance proof. |
| E17b | P17.2 | implementation/high | plan-exec | full | recommended: external fixtures share one candidate repository but close independently. |
| E17b | P17.3 | implementation/high | plan-exec | full | recommended: REST/MCP clients reuse the candidate server and published snapshots. |
| E17b | P17.4 | implementation/high | plan-exec | full | recommended: bulk/webhook clients reuse the same isolated API fixture and local receiver. |
| E17b | P17.5 | implementation/high | plan-exec | full | recommended: independent consumer reuses the candidate repository, never package source. |
| E17c | P17.6 | implementation/high | plan-exec | full | recommended sequential release evidence: pristine profiles before final DoD. |
| E17c | P17.7 | implementation/high | plan-exec | full | recommended: upgrade proof reuses the exact candidate and distribution harness. |
| solo | P17.8 | implementation/high | plan-exec | full | Scorecard/docs are an independent measurement and must not be tuned inside product fixtures. |
| E17e | P17.9 | implementation/high | plan-exec | full | recommended sequential gate work: freeze the 27-row manifest first. |
| E17e | P17.10 | implementation/high | plan-exec | full | recommended sequential gate work: execute and mutation-prove the frozen manifest. |
| solo | P17.11 | implementation/high | plan-exec | full | Sealed no-write preflight joins every routine proof and emits the owner-review packet. |
| solo | P17.12 | implementation/high | manual | full | required: only exact G-RELEASE may authorize irreversible packet-bound publication. |
Группировка — рекомендация: готовое подмножество исполнимо, порядок внутри батча свободен.
P1.1 предшествует всем items P1; P1.7 следует за ними. P2.1 предшествует B2;
P2.5 следует за B2; P2.6 — последний в фазе. P3.2 предшествует P3.3;
P3.1 независим от P3.2. В P4 порядок жёсткий по данным:
`P4.1 → P4.2 → {B5, B6} → P4.7 → P4.8`, `P4.9 → P4.10`, `P4.11` после `P4.7`;
ветка часов и гейт биндингов по файлам независимы от ветки `DataScope`.
В P5 исходный порядок жёсткий по данным:
`P5.1 → B7 → P5.5 → P5.6 → P5.7 → B8 → P5.11 → P5.12`, `P5.4 → {P5.5, P5.8}`,
`P5.8` перед `P5.12`; `P5.4` независим от `P5.3` и может идти сразу после `P5.2`,
а ветка inbox (`P5.8`) независима от ветки relay. После execution-audit:
resume `P5.6` → `{P5.13, P5.14, P5.15, P5.16, P5.17}` → `P5.18` → `P6.1`.
В P6 порядок по данным: `P6.1 → {P6.2, P6.4, P6.5}`; `P6.3` после `P6.1`;
`P6.6` последний. Все items P6 — `solo`: гейты и документ нельзя сливать
(ложно-зелёное), а P6.4/P6.5 не делят Files с гейтом.
P7–P17: конкретные последовательности батчей, модели и границы review приведены
в `execution-batches.md`; ниже — несокращаемые зависимости. Все predecessor
outputs читаются **после** их терминального checkpoint, даже если items стоят
рядом в одном батче.

- P7: P7.1 → P7.12 и P7.2; P7.3–P7.5 после P7.2; P7.7 после P7.6;
  P7.10 после P7.2; P7.11 последним после всех readers и D100 proof.
- P8: P8.1 → P8.2 → P8.3 → P8.4; P8.5 после P8.1; P8.6 последним.
- P9: P9.1 → P9.2 → P9.3 → P9.4–P9.6 → P9.7 → P9.8; P9.8 отдельно
  сверяет живую карту и оба DB lane.
- P10: P10.1 → P10.2 → P10.3 → P10.4; P10.5/P10.6 после P10.3;
  P10.7 после P10.4/P10.6; P10.8 последним. P10.3 отдельно под G-DDL.
- P11: P11.1 → P11.2 → P11.3; затем P11.4/P11.5; P11.6/P11.7 после
  P11.5; P11.8 последним.
- P12: P12.1 → P12.2/P12.3, P12.4 и P12.6; P12.5 после P12.4,
  P12.7 после P12.6; P12.8 последним.
- P13: P13.1 → P13.2; P13.4 после P13.3; P13.6 после P13.2/P13.5;
  P13.9/P13.10 после P13.2/P13.3; P13.11 после P13.10; P13.12
  последним под G-API. P13.5 отдельно под G-DDL.
- P14: P14.1→P14.2, P14.4→P14.5, P14.7→P14.8,
  P14.11→P14.12, P14.15→P14.16; P14.3 и P14.19 отдельно под G-DDL
  и G-SECRET. Все ветви сходятся P14.19→P14.20→P14.21.
- P15: P15.11/P15.12 — условные owner branches; P15.13 ждёт их
  terminal/skipped результат и все P15.1–P15.10; P15.14 после P15.13.
- P16: P16.4→P16.5→P16.6; P16.10 ждёт P16.3/P16.8; P16.12 ждёт
  P16.1–P16.11; P16.13 последним.
- P17: P17.1 фиксирует candidate; внешние fixtures P17.2–P17.5 и
  install/upgrade P17.6–P17.7 используют его digest; P17.9→P17.10;
  P17.11 ждёт P17.1–P17.10; P17.12 — отдельный manual под G-RELEASE.

### 3.1a Стадия 1 — проходы детализации оставшихся фаз

Каждый проход — `solo`, `Exec: plan-design`, отдельный корень по
`cold-start-root: design-root-terminal`: фазы имеют разные нормативные входы и разные
источники подтверждения, одна сессия на две фазы не экономит чтение и переполняет бюджет
контекста. Ниже записана история уже завершённых design-проходов по D37; это **не
execution routing**. Живые execution-модели определяют §3.3 / D118. P1 этот проход прошла 2026-09-19
на Claude Opus 5 (thinking, high). P2 этот проход прошла 2026-09-19
на Grok 4.6 (effort `high`). P3 этот проход прошла 2026-09-19
на Grok 4.6 (effort `high`). P4 и P5 этот проход прошли 2026-09-19
на Claude Opus 5 (thinking, high). P6 этот проход прошла 2026-09-19
на Grok 4.6 (effort `high`). P7 этот проход прошла 2026-09-19
на Grok 4.6 (effort `high`). P8 этот проход прошла 2026-09-19
на Grok 4.6 (effort `high`). P9 этот проход прошла 2026-09-19
на GPT-5.6 Sol (effort `high`). P10 этот проход прошла 2026-09-20
на GPT-5.6 Sol (effort `high`). P11 этот проход прошла 2026-09-20
на GPT-5.6 Sol (effort `high`) как допустимый fallback Grok-слота D37.
P12 этот проход прошла 2026-09-20 на GPT-5.6 Sol (effort `high`)
по той же fallback-лестнице; Perplexity MCP использован. P13 этот проход
прошла 2026-09-20 на GPT-5.6 Sol (effort `high`) как допустимый fallback
Astra-слота D37; Perplexity browser MCP использован. P14 прошла 2026-09-20
на GPT-6 Astra (effort `high`). P15–P17 прошли 2026-09-20 на GPT-5.6 Sol
(effort `high`) как допустимый fallback Grok-слота D37; Perplexity использован.

| Фаза | Историческая модель design-прохода | Review | Почему |
|:--|:--|:--|:--|
| P2 | Grok 4.6 (`high`) | light | Машинная инвентаризация: дизайн выбирает схему артефактов и сканеры, риск локален и обратим. |
| P3 | Grok 4.6 (`high`) | full | Исполняемые гейты: ошибочный гейт даёт ложно-зелёное по всему репозиторию — ровно этот дефект уже случался в PLAN1 P12.3. |
| P4 | Claude Opus 5 (thinking, high) | full | Durable-граница и контекст исполнения: смена формы `DataScope` — публичный контракт и риск потери tenant-изоляции. |
| P5 | Claude Opus 5 (thinking, high) | full | Новая messaging-основа (envelope, outbox/inbox) с гарантиями доставки; ошибка даёт молчаливую потерю событий. |
| P6 | Grok 4.6 (`high`) | full | Сокращение `corex/core`: 109 файлов, девять сторонних возможностей, публичные неймспейсы потребителей. |
| P7 | Grok 4.6 (`high`) | light | Типизированная конфигурация: механическая замена 152 вызовов `config()` по единому шаблону. |
| P8 | Grok 4.6 (`high`) | light | Декомпозиция composition roots без смены поведения; проверяется существующими пакетными сьютами. |
| P9 | GPT-5.6 Sol | full | Tenancy — 159 файлов, самый рискованный пакет; смешанная ось структуры и физическая DB-per-account. |
| P10 | GPT-5.6 Sol | full | Attributes: сменный источник определений, стабильная идентичность поля и протокол эволюции схемы поверх живых данных. |
| P12 | Grok 4.6 (`high`) | light | Таксономия ошибок и observability: аддитивный слой поверх существующих контрактов. |
| P13 | GPT-6 Astra | full | Ядро внешнего API как продукта: версии, идемпотентность, пагинация, коды ошибок — публичный контракт с необратимой стоимостью ошибки. |
| P14 | GPT-6 Astra | full | Нагрузочный контур API: бюджеты стоимости, bulk-операции, webhooks — внешние необратимые эффекты и подписи. |
| P15 | Grok 4.6 (`high`) | light | Переименования по уже утверждённому реестру P1; риск сведён к полноте sweep'а. |
| P16 | Grok 4.6 (`high`) | light | Раскатка профилей структуры по остальным пакетам по уже утверждённым правилам P1. |
| P17 | Grok 4.6 (`high`) | full | Доказательства универсальности и release-гейты 1.0: приёмка, от которой зависит коммерческое обещание. |

### 3.1b Стадия 2 — общий аудит плана

Единица работы, следующая за детализацией последней фазы. Запускается один раз.

| Единица | Модель | Exec | Context | Почему |
|:--|:--|:--|:--|:--|
| весь план | GPT-6 Astra | `task:plan-audit <ID> design` | `cold-start-root: whole-plan-independent-audit` | Ни один проход детализации не видел плана целиком: каждый читал свои нормативные входы и свой раздел эскизов. Сквозные дефекты — противоречие между `research/Pn-design.md` соседних фаз, контракт, объявленный производителем, но не потребляемый, непокрытый пункт матрицы вердиктов, нереалистичная зависимость или потерянный owner-гейт — видны только на целом. Независимость корня обязательна: авторы детализаций проверяли бы собственную работу. Аудит — Astra (D37); запас только Sol. |

Аудит второй стадии не исполняет product-работу и не закрывает items. Его результат —
вердикт и перечень фаз, требующих повторного прохода детализации, до перехода к стадии 3.

### 3.2 Объявленный route исполнения items (обязателен для детализации)

| Фаза | Model class/effort items | Exec | Review по умолчанию | Исключения, требующие `full` |
|:--|:--|:--|:--|:--|
| P1 | implementation/medium | plan-exec | light | Конституция и api-policy — `full`. |
| P2 | implementation/medium | plan-exec | light | Ни одного: артефакты read-only. |
| P3 | implementation/high | plan-exec | full | Все items: гейт по определению может дать ложно-зелёное. |
| P4 | implementation/high | plan-exec | full | Все items. |
| P5 | risk-routed per D111 | plan-exec/plan-run | full | Composer only for P5.13/P5.17; Grok 4.7 for integration; Sol for P5.6/P5.16/P5.18. |
| P6 | implementation/high | plan-exec | full | Arch-гейты зон и литмус; только P6.6 имеет owner-route exception D117, review не ослабляется. |
| P7 | implementation/medium | plan-exec | light | Секреты и redaction — `full`. |
| P8 | implementation/medium | plan-exec | light | Порядок bootstrapper'ов tenancy — `full`. |
| P9 | implementation/high | plan-exec | full | Все items. |
| P10 | implementation/high | plan-exec | full | Все items, затрагивающие живые значения атрибутов. |
| P11 | implementation/high | plan-exec | full | Freeze-семантика и fingerprint в durable payload. |
| P12 | implementation/medium | plan-exec | light | Публичные коды, OTel/cardinality integration, impersonation/audit и final ratchets — `full`. |
| P13 | implementation/high | plan-exec | full | Все items. |
| P14 | implementation/high | plan-exec | full | Все items; webhook-подписи и ротация секретов — плюс security-review. |
| P15 | implementation/medium | plan-exec | light | Публичные FQCN, package identity и финальные completeness gates — `full`; DB migration отсутствует по D86. |
| P16 | implementation/medium | plan-exec | light | Ни одного, если поведение не меняется; доказательство неизменности обязательно. |
| P17 | implementation/high | plan-exec; P17.12 `manual` | full | Все items; P17.12 — G-RELEASE и exact packet. |

### 3.3 Native execution pins после P6 (D118)

`execution-batches.md` задаёт **полный** batch → item → model маршрут P7–P17,
порядок, артефакты и проверки. Таблица ниже задаёт exact provider selectors и
усилие для обозначений этого маршрута. Для P1–P6 D112/D114 остаются
историческим route evidence; их будущие pins заменены D118. При отсутствии
selector/effort исполнитель фиксирует waiting route до product mutation, не
выбирает fallback самостоятельно. В текущем Codex provider соответствующая
проекция справа; launch печатает только свой provider.

| Код | Cursor native | Codex native | Effort | Применение |
|:--|:--|:--|:--|:--|
| C | `composer-2.5` | `gpt-5.6-terra` | medium | ограниченная механика по готовой карте |
| G46 | `cursor-grok-4.6-high` | `gpt-5.6-sol` | high | локальная интеграция по готовому design |
| G47 | `grok-4.7-high` | `gpt-5.6-sol` | high | несколько consumers/provider или новый public seam |
| S56 | `gpt-5.6-sol-high` (`-xhigh` для phase-final review) | `gpt-5.6-sol` | high; xhigh для phase-final review | DDL, durable/security, joined proof |
| F | P13.12: `gpt-5.6-sol-xhigh`; P17.11–P17.12: `gpt-5.6-sol-max` | `gpt-6-sol` | Cursor xhigh/max; Codex high/max | contract freeze и sealed release |

Для одного батча используется одна модель из его строки; квалификационные
`review` батчи стартуют новым корнем/сессией и не реализуют продукт вместо
авторов. Внутри батча состояние, scope, checks и receipt принадлежат
**каждому** item. Готовое подмножество может выполняться отдельно; фактический
batch тогда фиксируется в handoff. Все `Authority`/owner gates из item-спек
сохраняются. На phase-final переходах P8.6, P10.8, P12.8, P13.12, P14.21 и
P16.13 выполняется также межфазная сверка из `execution-batches.md`.

## 5. Decision Log

Решения живут в `decisions/<D-id>.md`; текущее/архивное представление собирается
`python3 packages/task/scripts/plan-views.py decisions --plan-dir plans/2026.09.19-№1-COREX-PLATFORM-HARDENING`
и читается в `decisions/_current.md`. Принято D1–D88, D91–D127; D89–D90 proposed;
D57 supersedes D5.

## 7. Contracts

### Consumed

| Контракт | Донор-план | Pinned | Замечено | Реакция |
|:--|:--|:--|:--|:--|
| `DB_SCHEMA.md` (FlexCRM `60_contracts`) | внешний, FlexCRM | FROZEN v1.0 | — | Существующие миграции не меняются; новые таблицы только с явным объявлением фазы. |
| `shared-data-foundation/1` | 2026.09.15-№1-COREX-FLEXCRM-REMAINING (P12.8) | v1 | Форма `DataScope` | P4 меняет контракт под отдельный owner-gate, с уведомлением потребителей. |
| Доктрина уровней D70/D91 | plans/archive/COREX-CORE | — | — | Сохраняется без изменений; `tests/Arch/LayerTest.php` остаётся обязательным гейтом. |

### Exported

| Контракт | Версия | Статус | Потребители | Уведомлены |
|:--|:--|:--|:--|:--|
| `docs/architecture/constitution.md` | v1.0.0 | draft (P1) | все фазы плана, FlexCRM | — |
| `docs/architecture/api-policy.md` | v1.0.0 | draft (P1) | P3, P13, P14, потребители внешнего API | — |
| `message-envelope/1` | v1 | planned (P5) | P10 (поисковая проекция), P12 (correlation/causation), P14 (webhooks), пакеты-владельцы контрактов сообщений | — |
| `error-descriptor/1` | v1 | planned (P12) | P13, package exceptions/results, observability adapters | — |
| `operation-observer/1` | v1 | planned (P12) | P14 bulk/long operations, messaging consumers, host telemetry | — |
| `operation-contract/1` | v1 | planned (P13) | FlexCRM, внешние клиенты, MCP-инструменты | — |
