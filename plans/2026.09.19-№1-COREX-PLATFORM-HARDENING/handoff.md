# HANDOFF — 2026-09-19 — after P17

**Next:** `design-phase: task:plan-design 2026.09.19-№1-COREX-PLATFORM-HARDENING P1`

| Параметр | Значение |
|---|---|
| Batch | n/a |
| Model class | frontier — Claude Opus 5 (thinking, high) по D10 |
| Effort | high — нормативный слой, от которого зависят 16 остальных фаз; ошибка воспроизводится в каждом последующем item'е |
| Capabilities | perplexity-search (обязательный внешний RAG фазы) |
| Context | cold-start-root: design-root-terminal |
| Суть | Детализировать P1: развернуть скелет конституции и нормативов в item-спеки плюс `research/P1-design.md` с готовым текстом документов |

```
task:plan-design 2026.09.19-№1-COREX-PLATFORM-HARDENING P1
```

**Done:**

- Создан верхний слой PLAN2: `plan.md` (Context, Execution Rules, Routing на 17 design-проходов
  плюс объявленный route исполнения по фазам, D1–D9, Contracts, три развилки).
- Разобраны оба аудита построчно: `findings/audit-verdicts.md` — 165 пунктов
  (116 подтверждено, 34 принято с изменением, 7 уже реализовано, 5 отклонено, 3 опровергнуто),
  включая 14 собственных находок, которых нет ни в одном аудите.
- Измерена база: `research/00-baseline-metrics.md` — 21 пакет, 838 файлов в `src`,
  152 `config(`, 0 `static $`, 85 миграций, карта префиксов таблиц, полный список существующих
  гейтов и трёх дырок контура.
- Собраны внешние подтверждения: сводка `research/perplexity-2026-09-19.md` (19 успешных
  запросов; первичные источники Shopify, Stripe, GitLab, Atlassian, Grafana, Salesforce,
  Microsoft, Google AIP, Symfony, Drupal, Shopware, Laravel, PostgreSQL, Standard Webhooks,
  OpenTelemetry) плюс **сырые ответы** в `artifacts/rag-2026-09-19/` с `manifest.json`
  (точные строки запросов, статусы, источники, SHA256 файлов захвата, пять таймаутов и один
  незакрытый пробел).
- Зафиксированы провенанс входов `inputs/sources.md` (пути и SHA256 обоих аудитов как
  non-normative сырья) и правило владения артефактами в `plan.md` §2: обоснование плана живёт
  внутри папки плана, продукт репозитория — в дереве кода.
- Созданы 17 скелетов фаз `phases/P1..P17/Pn.md` с контрактом фазы, Phase Assurance, DoR,
  Scope Included/Excluded, зависимостями, планируемым составом items и формой Validation.
- Зафиксированы `roadmap.md` (граф фаз, соответствие фазам аудита, бюджет контекста, гейты
  владельца, отложенное) и `open-questions.md` (Q1–Q6).

**Remaining:**

- P1–P17 — скелеты; ни один item не детализирован, исполнение не начато.
- Q1 (имя `corex/api`) и Q2 (имя `corex/auth`) — решения владельца; блокируют только P15.8.
- Незакрытое внешнее подтверждение: практика PIM-платформ по эволюции определений атрибутов —
  добирается отдельным запросом при детализации P10.

**Sources of truth:** `plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/plan.md` ·
`findings/audit-verdicts.md` · `research/00-baseline-metrics.md` ·
`research/perplexity-2026-09-19.md` · `roadmap.md` · `phases/P1..P17/Pn.md` ·
`docs/dev-doctrine.md` · `audits/*` (только non-normative сырьё)

**Open risks:**

- PLAN1 не закрыт: у него остаётся терминальный `PLAN-CLOSE-CI` (D44). PLAN2 приостанавливает
  PLAN1 как ACTIVE, но не отменяет этот остаток — он исполняется в своём плане.
- P4 и P6 меняют публичные контракты ядра; без owner-gate G-API они не начинаются.
- P5, P10, P13, P14 требуют новых таблиц вне `FROZEN v1.0` — каждый случай отдельный G-DDL.
- P14 — единственная фаза с внешними необратимыми эффектами (исходящие доставки, ротация
  секретов); в тестах доставки только на локальные приёмники.

**Workarounds/Deferred/Open questions:**

- workarounds: чтение файлов в этой сессии частично выполнялось через shell из-за временной
  недоступности файлового инструмента; на содержание артефактов это не повлияло.
- deferred: GraphQL-транспорт (D7); выделение пакетов из ядра (D8); каталоги
  `capabilities/`/`integrations/` (A24); выделение транспортных подпакетов API (S34); остаток
  сравнительного прохода по шести платформам (research-item P13).
- open_questions: Q1–Q6 в `open-questions.md`; Q1/Q2 требуют владельца.
