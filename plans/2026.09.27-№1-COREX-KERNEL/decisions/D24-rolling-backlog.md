---
id: D24
date: 2026-09-27
status: accepted
item: P00
items: [P00, P01, P02, P03, P04, P05, P06, P07, P08, P09]
supersedes: []
superseded_by: null
---
# D24 — rolling-backlog (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, 2026-09-27) по мандату D21 («процессом полностью будешь рулить ты», `brief/00-brief.md` B5/B6).
**Evidence:** RAG:— Task canon `swissknifeman:packages/task/lib/task/plan_readiness.py::design_snapshot` (skeleton-фаза или фаза без items → `PLAN_DESIGN_INCOMPLETE`, `design-gate` закрыт, `plan-run` запрещён); `plan-work.py design-gate` на PLAN3 до правки → `PLAN_DESIGN_INCOMPLETE`; прецедент chatom `PLAN2.D33` §3 (skeleton-фазы вне `phases/`).

## Решение

1. Фазы P01–P09 до своей очереди хранятся как skeleton-носители в `backlog/Pnn.md` (plan-owned, не phase carriers); номера фаз и items сохраняются.
2. Цикл фазы по D21: `task:plan-design <ID> <Pn>` переносит `backlog/Pnn.md` в `phases/Pnn/Pnn.md` и детализирует items → `task:plan-design <ID> finish` (fingerprint всех детализированных фаз) → `task:plan-audit <ID> design` (независимая модель; неизменённые закрытые фазы переиспользуют прежние доказательства) → исполнение строк execution-sheet.
3. «Phase design check» D21 реализуется этой парой finish + design audit: whole-plan audit охватывает только уже детализированные фазы, поэтому не требует дизайна фаз вперёд.

## Почему

- Runtime-gate Task не допускает skeleton-фаз в `phases/`; без переноса P00 не исполнима (D21 невыполним буквально).
- Отклонено: (a) дизайн P01–P09 заранее — противоречит D21/B5; (b) план-преемник для P01–P09 (как chatom D33) — преемник упрётся в тот же gate, каждая фаза потребовала бы нового плана; (c) обход gate — запрещён каноном.

## Consequences

`phases/P01…P09` → `backlog/`; `plan.md` §2/§3, `roadmap.md` цикл, `index.md`, `handoff.md`. P00.1 записывает свои решения с D25.
