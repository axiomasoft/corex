# HANDOFF — 2026-09-29 — after P02

**Next:** manual: собрать факты кода P03 в облачной сессии по `artifacts/P03-design/cloud-brief.md`, затем design-phase: task:plan-design 2026.09.27-№1-COREX-KERNEL P03

| Параметр | Значение |
|:--|:--|
| Batch | n/a |
| Model class | implementation |
| Effort | high |
| Capabilities | — |
| Context | cold-start-root: capability-missing |
| Суть | Облачная сессия (без MCP/Task) сканирует код и пушит факты в ветку `design/p03-cloud-facts`; затем автор плана (claude-code opus) проектирует P03 по этим фактам и ресерчу E1–E2. |

**Prompt:**

```text
Репозиторий axiomasoft/corex, ветка main. Выполни задание из файла
plans/2026.09.27-№1-COREX-KERNEL/artifacts/P03-design/cloud-brief.md — строго по нему.
Ты собираешь факты кода для дизайна, продукт не меняешь, MCP и внешний поиск не нужны (git + python3 stdlib).
Пиши только в plans/2026.09.27-№1-COREX-KERNEL/artifacts/P03-design/cloud/.
Сдача: ветка design/p03-cloud-facts, один коммит, git push -u origin design/p03-cloud-facts,
в ответе SHA, сводка 10–15 строк и список неясностей. PR не открывай, в main не сливай.
```

**Done:** P02 закрыта: Phase Handoff в `phases/P02/P02.md`, GREEN фазовый аудит D55, hosted CI отложен (D47). Для P03 снят внешний ресерч: E1 — Pest 5 arch не выражает раскладку каталогов и связь «тот же модуль», поэтому гейт раскладки — собственный скрипт; E2 — PHPStan `@internal` работает по первому сегменту namespace (переиспользовано). Задание облаку — `artifacts/P03-design/cloud-brief.md`: инвентарь, формы пакетов, внутрипакетные ссылки, потребители, строковые FQCN, нарушения D05 L1–L5, два варианта карты модулей S1/S2.

**Remaining:** облачная сессия по Prompt → SHA ветки `design/p03-cloud-facts` владельцу → `task:plan-design 2026.09.27-№1-COREX-KERNEL P03` (claude-code opus: слияние фактов, решение S1/S2, items, досье, finish) → `task:plan-audit … design` (не автор) → исполнение Codex по execution-sheet.

**Sources of truth:** phases/P02/P02.md; phases/P02/P02.closed.md; findings/P02-phase-audit.md; backlog/P03.md; research/P03-design-rag.md; artifacts/P03-design-rag/README.md; artifacts/P03-design/cloud-brief.md.

**Open risks:** Hosted CI не проверялся с P01 (D47). Однофункциональные пакеты держат корзины на уровне пакета: выбор S1/S2 меняет объём переноса и публичную поверхность, решается в дизайне P03 по фактам облака.

**Workarounds/Deferred/Open questions:** В облаке нет Task/MCP — носители плана ведёт только автор плана (вариант C).
