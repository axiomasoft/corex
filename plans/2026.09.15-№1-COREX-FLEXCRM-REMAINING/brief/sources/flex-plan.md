# План

> **Текущий scope — D49 / 2026-09-14:** основной упор — backend. Frontend максимум: запускаемая заглушка, авторизация и пустой дашборд. Продуктовые экраны, UX и требования к дизайну — **вне FLEX-2**, отдельная работа после стабилизации backend и нового задания владельца. Это правило имеет приоритет над историческими UI/сквозными требованиями ниже и в старых карточках; готовность пакетов не разрешает автоматически frontend-разработку. См. [D49](decisions/D49-backend-first-frontend-out-of-scope.md).

> **Актуальная квалификация CRM main delivery 2026-09-14:** company/contact/M2M GREEN по unchanged owning source/recorded scenarios. Последний main run GREEN; текущая dependency delta не qualified. Код объединён в основной CoreX main. G1–G3 install gates BLOCKED, G4 app context boxed; текущие bytes/ref/evidence и prompt — delivery/crm-composition-20260914/gate-review.md/current-gates.json. Готовый bounded app design и текущий Next — findings/P16-crm-delivery-requalification.md, findings/P16-company-contact-app-composition.md и handoff.md. Исторические обновления ниже сохраняются; owning CRM contract design повторно не запускается.

> **История 2026-09-14 — D48 / P16.** P15 закрыт по полноте квалификации компаний/контактов; flex/crm not-ready. Следующий локальный шаг — **P16.1: исполняемое ТЗ owning CRM parity для CoreX**. Подготовка ТЗ не требует готового пакета; интеграция требует отдельно принятого ref/receipts. Очередь — roadmap.md, точный запуск — handoff.md. CoreX остаётся внешней репой.

> **D50 / P17 — текущий successor:** P15.1/P16.1 закрыты. P17.1 детализирован для admitted installed backend/API integration компаний, контактов и M2M. Required owning delivery и физический context описаны до запуска; G1–G3 остаются live gates. Единственный Next — условный P17.1 в handoff.md. ТЗ CoreX: findings/P17-corex-install-delivery-spec.md; consumer: findings/P17-backend-integration-spec.md.

## 0. Meta

| Поле | Значение |
|:--|:--|
| Plan ID | FLEX-2 |
| Title | FlexCRM волна 2 (консолидация): research-гапы → блюпринт-драфты → аспектные срезы (полная модульная БД-спека) → consistency/RAG-аудит → vault-исполнение → координация реализации (CoreX-модули для CRM) |
| Document Type | Executable Master Plan (P6 — Program/coordination) |
| Authoring Model | fable (консолидация + design) · opus/high (P6 design 2026-07-13) · gpt-5.6-sol/high (P6 D41 design 2026-09-13) · gpt-5.6-sol/xhigh (P15.1 D47 design 2026-09-14) · gpt-5.6-sol/high (P17 D50 design, provider-attested owner request 2026-09-14) |
| Repository | Синхронизируемые plans/docs: Brain `05-Projects/01-Products/FlexCRM/` ↔ существующий `~/projects/flexcrm`; P6 координирует пакеты, P9–P13 задают первые кодовые срезы (D37/D38) |
| Related Packages | corex/* · flex/{workflow,attributes,import-export} · AzGuard · Vaulter · ChatomIo · AgenTools · RegHub |
| Execution Mode | phase-first |
| Target Operator Models | sonnet (оператор-драйвер, close-механика, P5) · fable/high (research, драфты, срезы) · opus/xhigh (consistency/аудиты) |
| Approval Owner | Dmitry Vostrikov |
| Home | brain (дизайн-волна; межпроектная координация P6) |
| visibility | private |
| supersedes | FLEXCRM-W2, FLEX-IMPL (оба → plans/archive/; done-факты сохранены: P1.1/P1.2 = R-24/R-25) |
| Paused By | — |
| Layout | v2 |
<!-- migration-version: 0.2.8 -->

## 1. Context

FLEX-2 — консолидация треков Flex по образцу EDUFY-2: вместо четырёх параллельных
планов (flex-программа, FLEX-CODE, FLEXCRM-W2, FLEX-IMPL) — ОДИН управляющий план на
корневой продукт **FlexCRM** + нужные ему CoreX-модули. Дизайн-программа flex
(Этапы 0–8, B-10..B-26) и код-паттерны (FLEX-CODE, C-01..C-06) завершены и НЕ
пересматриваются; артефакты продолжают жить в `05-Projects/01-Products/FlexCRM/plans/flex/` (D3). FLEX-2 поглощает:
открытые дизайн-items FLEXCRM-W2 (P1–P5 — research-гапы против Б24/amoCRM/HubSpot,
драфты B-27..B-32, аспектные срезы вкл. полную модульную БД-спеку, consistency,
vault-исполнение) и программную координацию FLEX-IMPL (P6 — майлстоуны COREX-CORE,
OSS-выносы, flexcrm-app, сателлиты; ⏸️ до «Запускай» — код на стопе решением
владельца). FlexCMS и FlexStore выведены в тонкие paused-stubs (`05-Projects/01-Products/FlexCMS/plans/FLEXCMS`,
`05-Projects/01-Products/FlexStore/plans/FLEXSTORE`): их волны накатываются ПОСЛЕ закрытия FLEX-2 по дельта-принципу
(CMS = дельта поверх CRM-ядра; Store = дельта поверх CMS). Оркестрация — драйвер:
корневой оператор Sonnet ведёт цикл по `DRIVER.md` + `workflows/wf-flex-2-drive.js`,
внутренние research/синтез-агенты — Fable, аудиты — opus/xhigh. Открытые вопросы
не прерывают исполнение: складываются в `open-questions.md` (статус «решить-потом»),
эскалация — только кардинальное (D6).

## 2. Execution Rules

- **D49, текущая граница:** новые срезы реализуются и принимаются по backend/API; frontend только boot/auth/пустой dashboard. Продуктовый frontend/design вынесен за рамки FLEX-2, существующие UI результаты сохраняются как история.

- Один item за раз; статус обновляется В ПЛАНЕ (Phase Status + Status Board) до завершения работы.
- **D38, приоритет для новой реализации:** обязательное утверждение владельцем полной схемы БД; прежние разрешения P5/P6 этот gate не заменяют. После каждого item — видимый результат и контрольная точка в чате, до следующего кодового шага.
- Статусы — 6 канонических строк байт-в-байт (plan-protocol §5).
- План разошёлся с каноном plans/flex (B-10..B-26, C-01..C-06, D1–D17 master + чекпойнты) → не импровизировать, эскалировать.
- После закрытия item/фазы — перезаписать `05-Projects/01-Products/FlexCRM/plans/FLEX-2/handoff.md` целиком.
- Все research-факты — только через RAG (perplexity-web); без источника → `[UNVERIFIED]`.
- Открытые вопросы артефактов наследуются вниз по слоям (R → B §9 → срез P3 закрывает или заносит в open-questions.md); молча потерять вопрос нельзя — P4.1 проверяет сквозной реестр.
- **Политика прерываний (D6):** некардинальный вопрос → запись в `open-questions.md` со статусом + «решить-потом», исполнение продолжается. Кардинальное (ломает tenant-isolation / деньги-биллинг / архитектурную несущую / канон flex D1–D17) → 🔴 + Escalation Needed.
- **Драйвер (D5):** исполнение ведёт Sonnet-оператор по `05-Projects/01-Products/FlexCRM/plans/FLEX-2/DRIVER.md`; ручной cold-start per-item (W2 D10) заменён workflow-циклом. Ручной запуск `/task:plan-exec FLEX-2 Pn.m` остаётся легальным fallback.
- P5 гейтился командой «Запускай P5» (получен, фаза закрыта). Гейт P6 **снят владельцем 2026-07-13** (D15).
- **P6 — программная фаза (protocol §11):** `/task:plan-exec FLEX-2 P6.x` **ЗАПРЕЩЁН** для items P6.3–P6.26 (майлстоуны-делегаты; код исполняется командами целевой репы из ОТДЕЛЬНОЙ сессии). Легален только для пре-флайт-items P6.1–P6.2 (vault-артефакты этого плана). Закрытие майлстоуна — `/task:plan-close FLEX-2 P6.m` по факту GREEN-аудита делегата (D14).
- Wiki-ссылки в vault-документах — только `[[Имя файла]]`.
- **Переходное правило Layout v2 (до P6):** закрытие пункта фиксируется записью в `journal.jsonl`, находкой или решением при необходимости и регенерацией видов; поля исхода в файле ТЗ запрещены и ловятся линтером.

## 3. Routing

| Batch | Items | Model class/effort | Exec | Review | Почему |
|:--|:--|:--|:--|:--|:--|
| P0-authoring | P0.1–P0.2 | frontier/high | manual | full | recommended: консолидация и авторинг драйвера используют один несущий контекст волны; fable/high |
| P1-research | P1.3–P1.7 | frontier/high | manual | full | recommended: внешний research и синтез переиспользуют один RAG-контекст; fable/high + perplexity-web |
| P2-blueprints | P2.1–P2.6 | frontier/high | manual | full | recommended: blueprint-драфты разделяют ключевые архитектурные решения и входы; fable/high |
| P3-slices | P3.1–P3.5 | frontier/xhigh | manual | full | recommended: аспектные deep-dive срезы разделяют самую ответственную проработку; fable/high–xhigh |
| solo | P4.1 | frontier/xhigh | manual | full | solo: независимая адверсариальная consistency-сверка требует свежего контекста; opus/xhigh |
| solo | P4.2 | implementation/medium | plan-exec | full | solo: механические фиксы зависят от завершённого арбитража P4.1; sonnet/medium |
| P5-vault | P5.1–P5.3 | implementation/medium | plan-exec | full | recommended: vault-механика использует общий готовый набор решений и один явный стартовый gate; sonnet/medium |
| P6-preflight | P6.1–P6.2 | frontier/xhigh | manual | full | recommended: заморозка DB-контракта и арбитраж приземлений используют общий набор несущих конфликтов; opus/high–xhigh |
| P6-delegates | P6.3–P6.26 | implementation/low | manual | full | recommended: майлстоуны-делегаты закрываются общей механикой доски; код и routing живут в целевых репах по D14, а manual явно сохраняет запрет plan-exec из D36/Q101 |
| P7-design | P7.1–P7.4 | frontier/xhigh | manual | full | recommended: research, blueprint, дельта-срезы и арбитраж attendance используют общий контекст; fable/high → opus/xhigh |
| solo | P7.5 | implementation/medium | plan-exec | full | solo: vault-механика отделена owner gate «Запускай P7.5»; sonnet/medium |
| P8-design | P8.1–P8.5 | frontier/xhigh | manual | full | recommended: research, B-37/B-38, C-07 и арбитраж дельт используют общий архитектурный контекст; fable/high → opus/xhigh |
| solo | P8.6 | implementation/medium | plan-exec | full | solo: vault-механика отделена owner gate «Запускай P8.6»; sonnet/medium |
| P9-foundation | P9.1–P9.6 | frontier/high | manual | full | recommended: инвентаризация, DB approval, среда, зависимости и миграционный контур переиспользуют один foundation-контекст; до P9.3 код запрещён |
| P10-scaffold | P10.1–P10.6 | frontier/high | manual | full | recommended: API, Nuxt, Naive UI, transport, solution shells и сборки образуют один scaffold-контекст |
| P11-auth | P11.1–P11.7 | frontier/high | manual | full | recommended: auth/profile проходят сквозной backend → frontend поток с общим контрактом |
| P12-tasks | P12.1–P12.4 | frontier/high | manual | full | recommended: базовые Tasks и reuse используют общий пакетный контракт без отраслевых расширений; пакетные дельты — у владельца CoreX |
| P13-delivery | P13.1–P13.2 | frontier/high | manual | full | recommended: проверка поставки и фиксация дальнейшей очереди используют одно evidence-состояние, не объявляя весь продукт GREEN |
| P14-app-continuation | P14.1–P14.3 | implementation/high | manual | full | recommended: D46 завершает минимальный контур: следующая сессия выполняет только P14.3 поверх завершённого P14.1; P14.2 уже GREEN. Plan-run наследует provider route, учёт P14.1 согласуется с существующим evidence без повторного исполнения, без новых фильтров и CoreX writes |
| solo | P15.1 | frontier/high | manual | light | solo: первый product срез зависит от ещё неготового owning CRM контракта/ref; read-only квалификация завершается точным gap packet, продолжение реализации невозможно включить в готовую adjacent группу до его результата. Открытое package/schema reasoning, без CoreX writes |
| solo | P16.1 | frontier/high | manual | light | solo: локальное исполняемое ТЗ устранения CRM gaps по D48; read-only CoreX и owning handoff, без реализации или принятия нового package контракта |
| solo | P17.1 | implementation/high | manual | full | recommended: один bounded installed backend/API item использует общий admitted delivery/context/HTTP/reuse контекст; terra/high. Fresh successor session после design boundary; внешний receipt — live gate, не новый research item |

## 4. Канонический порядок фаз и зависимостей

Номера фаз задают порядок чтения и место в истории, но не превращают программу
в единственную линейную очередь. Исполнять можно только item, чьи зависимости
выполнены и который не находится за owner/external gate. `status.md` —
сгенерированный технический вид: он сортирует имена каталогов лексически
(`P1`, `P10`, …, `P2`) и не является источником порядка.

| Порядок | Фазы | Роль и зависимость |
|:--|:--|:--|
| 1 | P0 | Консолидация прежних планов и запуск управляющего контура. |
| 2 | P1 → P2 → P3 → P4 → P5 | Завершённый design-конвейер: research, blueprint, deep-dive, consistency, vault. |
| 3 | P6 | Долгая программная координация пакетов и полного продукта. Внутри неё действует собственный DAG; P6.5 ждёт внешнее CoreX evidence. |
| 4 | P7, P8 | Независимые design-ветки: attendance и кодовая архитектура. Они не ожидали полного P6; P8 подготовила входы для app-части P6. |
| 5 | P9 → P10 → P11 → P12 → P13 | Первый поставляемый FlexCRM-контур по D38: DB gate, scaffold, auth, Tasks, проверка поставки. Эта ветка создана намеренно, чтобы не ждать весь P6. |
| 6 | P14 | Продолжение D43 после P13: локальные срезы поверх уже принятых контрактов. P14.1 не зависит от ожидания P6.5 и не закрывает программные milestones P6. |
| 7 | P15 | После закрытого P14: проверка совместимости flex/crm и owning prerequisites первого CRM-среза. Не ждёт P6.5 для read-only сбора evidence; будущая реализация зависит от конкретных готовых contracts. |
| 8 | P16 | После закрытого P15: подготовить конкретное owning CRM parity ТЗ и команду следующего дизайна; пакетная готовность требуется будущей интеграции, не подготовке задания |

| 9 | P17 | После закрытого P16: installed backend/API integration готового consumer package. P17.1 — один item; product execution только после matching immutable/full-schema/install receipt G1–G3 и consumed G4 contracts |

Текущий локальный результат: P15.1/P16.1 закрыты; D50 допроектировал P17.1.
Единственный Next — waiting-external → `task:plan-run FLEX-2 P17.1`.
Missing owning release evidence не обходится app DDL или synthetic context.
P6.5 и программные milestones сохраняют отдельное owning acceptance.

## Актуальная полоса реализации

Задания первого контура находятся в `phases/P9/`–`phases/P13/`; принятые Tasks — `phases/P14/`, закрытый CRM readiness-срез — `phases/P15/`, следующий owning parity контракт — `phases/P16/`. Исполнительская карта — `roadmap.md`, вход владельца — `brief/00-brief.md`. Схема — `schema/DATABASE_SCHEMA.md`, явное согласие — `schema/approval.md`. P6 сохраняет координацию backend/пакетов; frontend/design исключён из текущего исполнения по D49, полный продуктовый UI не заявляется готовым. Доказательства P9/P10 используются для P6.17, P11/P12 и P14 — для части P6.18, но не закрывают остальные домены и внешние пакеты. Требование D14 о **другой сессии** относится к P6-делегатам: новый backend/API/solution composition код после DB approval пишется в текущей flexcrm; frontend-разработка ограничена D49. Старые автоматические драйверы не обходят D38. Фактический прогресс — journal/state/handoff; статус планирования не означает готовый код.

## 5. Decision Log

Решения находятся в `decisions/`; собрать или проверить их виды: `python3 packages/task/scripts/plan-views.py decisions --plan-dir <plan-dir> [--check]`.

## Актуализация P6 — D41 / 2026-09-13

P6.4 принят по provenance-пинам 2026-09-12. Следующий ограниченный шаг —
новый пакетный CoreX design из `findings/P6.5-delegate-contract.md`, не старый
COREX-CORE P3 и не массовое исполнение P6. Нормативы D23, central ownership
D28/D40 и gate D38 сохраняются. P9–P13 дают partial product evidence без
автоматического закрытия P6.17/P6.18. Полная очередь и acceptance — P6/D41.
Update Log role/model/changes формирует owned `status.md` из journal.jsonl.

## Продолжение P14 — D45 / 2026-09-14

P14.2 задаёт фильтры списка по проекту/исполнителю на существующих API/options,
URL-state и безопасную смену workspace. Новые CoreX правки, contracts и DDL
исключены. Implementation finding P14.1 не подменяет отсутствующую запись его
закрытия; P14.2 проверяет фактическую готовность зависимости до изменений.
По последующему явному указанию владельца P14.2 реализован и проверен здесь,
без новой сессии. Evidence — `findings/P14.2-task-list-filters.md`;
текущий provider route покрывает минимальный `implementation/high`.

## Продолжение P6 — D42 / 2026-09-13

Делегат `2026.09.13-№1-COREX-TENANCY-IDENTITY` уже создан. Next — waiting: новые owning
DI/AP receipts и scoped D2 release; design возобновляется по D6/packet только
после нового evidence. Принятый профиль
не даёт P4/P5/runtime authority; missing DI/AP effective receipts остаются
UNFILLED. Актуальные candidate pins — `findings/P6.5-delegate-progress.md`.
P6.5 остаётся открытым; ownership и acceptance D41 неизменны.

## Продолжение локального app-кода — D43 / 2026-09-13

Текущий запрос владельца разрешает значительный объём независимой разработки
в этой сессии, пока CoreX работает отдельно. D42 waiting не является глобальным
стопом FlexCRM. Порядок и границы — `decisions/D43-independent-app-delivery.md`;
частичное app-evidence — `findings/P6.18-tasks-workbench.md`. Программные
майлстоуны не закрываются по этой частичной поставке.

## Завершение минимального Tasks-контура — D46 / 2026-09-14

Владелец поручил агенту исправить очередь самостоятельно и делать по минимуму.
P14.3 описан до передачи команды исполнителю. Одна сессия выполняет только P14.3 поверх
сделанного P14.1, согласует его учёт и закрывает P14 при GREEN.
Фильтры и сортировки не расширяются; следующий Tasks UX-item не создаётся
автоматически. Следующий доменный шаг определяется реальной готовностью
owning contracts P6; команды на отсутствующие ТЗ не выдаются.

## Вход в Core CRM — D47 / 2026-09-14

P14 закрыт по `findings/P14.3-minimal-tasks-acceptance.md`. По явному
запросу plan-design детализированы P15 и P15.1: сначала read-only сверка
`flex/crm` со схемой 0.2.2 и пакет owning prerequisites компаний/контактов.
Source существует, но install/parity/receipts проверяются отдельно.
ТЗ реализации не создаётся до доказанного результата; P6 milestones
и внешнее ожидание P6.5 сохраняют свои acceptance. Точный Next — handoff.

## Исправленная передача после P15.1 — D48 / 2026-09-14

По текущему требованию владельца оформлен P16.1: подготовка исполняемого
owning CRM parity ТЗ по готовой матрице/gap packet. Next — P16.1 с полной
native командой в handoff. Это не подключение not-ready пакета, не повторное
исполнение P15.1 и не разрешение писать в соседний CoreX.


## Текущая граница реализации — D49 / 2026-09-14

Владелец уточнил backend-first scope: заканчиваем backend/contracts/install/API integration. Запускаемая frontend заглушка, авторизация и пустой dashboard достаточны; новые Tasks/CRM/прочие продуктовые UI не развиваем. Будущий frontend и подробные требования к дизайну — отдельная работа вне FLEX-2 после стабилизации backend. Предыдущие UI prototype/evidence сохраняются, но не являются визуальным approval. Next в handoff — backend-only.


## Полный дизайн backend-продолжения — D50 / 2026-09-14

По явному запросу владельца закрыли неоднозначность dependency closure,
full-enabled storage/FK closure, trusted physical context, shared session
workspace resolution, module binding/lifecycle, HTTP authorization/negative
acceptance и reuse. Исполняемые ТЗ — P17.1 и две P17 findings. Новый product
item в design turn не исполняется; точный условный native запуск — handoff.
CoreX remedies выполняет отдельная owning сессия, approved schema неизменна.
