# HANDOFF — 2026-09-29 — after P02

**Next:** design-phase: task:plan-design 2026.09.27-№1-COREX-KERNEL P03

| Параметр | Значение |
|:--|:--|
| Batch | n/a |
| Model class | frontier |
| Effort | high |
| Capabilities | — |
| Context | cold-start-root: owner-requested-new-session |
| Суть | Дизайн P03 по фактам облака (`artifacts/P03-design/cloud/`) и черновым выводам `artifacts/P03-design/design-notes.md`: D57, items, досье, примеры, finish. |

**Provider-native command:**

```text
/task:plan-design 2026.09.27-№1-COREX-KERNEL P03
```

**Done:** P02 закрыта: Phase Handoff в `phases/P02/P02.md`, GREEN фазовый аудит D55, hosted CI отложен (D47). Для P03 снят внешний ресерч: E1 — Pest 5 arch не выражает раскладку каталогов и связь «тот же модуль», поэтому гейт раскладки — собственный скрипт; E2 — PHPStan `@internal` работает по первому сегменту namespace (переиспользовано). Задание облаку — `artifacts/P03-design/cloud-brief.md`: инвентарь, формы пакетов, внутрипакетные ссылки, потребители, строковые FQCN, нарушения D05 L1–L5, два варианта карты модулей S1/S2.

**Remaining:** облачный скан выполнен (`1350c1c`, слит в `main`); прототип карты модулей и черновые выводы — `artifacts/P03-design/design-notes.md`; D56 (уроки облачных сессий) принято → `task:plan-design 2026.09.27-№1-COREX-KERNEL P03` (claude-code opus: слияние фактов, решение S1/S2, items, досье, finish) → `task:plan-audit … design` (не автор) → исполнение Codex по execution-sheet.

**Sources of truth:** phases/P02/P02.md; phases/P02/P02.closed.md; findings/P02-phase-audit.md; backlog/P03.md; research/P03-design-rag.md; artifacts/P03-design-rag/README.md; artifacts/P03-design/cloud-brief.md; artifacts/P03-design/cloud/README.md; artifacts/P03-design/design-notes.md; decisions/D56-cloud-sessions-and-relay-lessons.md.

**Open risks:** Hosted CI не проверялся с P01 (D47). Однофункциональные пакеты держат корзины на уровне пакета: выбор S1/S2 меняет объём переноса и публичную поверхность, решается в дизайне P03 по фактам облака.

**Workarounds/Deferred/Open questions:** В облаке нет Task/MCP — носители плана ведёт только автор плана (вариант C).
