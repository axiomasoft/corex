---
id: D21
date: 2026-09-27
status: accepted
item: P00
items: [P00, P01, P02, P03, P04, P05, P06, P07, P08, P09]
supersedes: []
superseded_by: null
---
# D21 — rolling-execution (accepted)

**Actor:** owner 2026-09-27 (`brief/00-brief.md` REPOSITORY-OWNER-MESSAGE)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

Катящийся дизайн с немедленным исполнением: фазы проектируются по одной (`task:plan-design <ID> <Pn>`) до уровня chatom PLAN2 (досье, примеры, runbook, self-check) и сразу исполняются по execution-sheet; следующая фаза проектируется по фактическому коду после закрытия предыдущей. Whole-plan design audit заменяется phase-level design audit перед исполнением каждой фазы (plan-lint + независимое ревью дизайна фазы). Исполнители — codex/cursor по маршрутам; frontier-решения — frontier-модель. Автор плана (Claude) рулит процессом и утверждает решения с очевидно лучшим вариантом без эскалации; owner-gate — только внешние/необратимые действия.

## Why

Владелец: «сразу выполнять фазы плана … заново проработка всех фаз без выполнения будет огромной по затратам … процессом полностью будешь рулить ты» (B5, B6).

## Consequences

Структура roadmap/handoff; D23.
