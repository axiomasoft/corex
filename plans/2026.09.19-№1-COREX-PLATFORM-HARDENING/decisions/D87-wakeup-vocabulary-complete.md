---
id: D87
date: 2026-09-20
status: accepted
item: P15.7
items: [P1.3, P15, P15.7]
supersedes: []
superseded_by: null
---
# D87 — Wakeup vocabulary закрывает A70 целиком

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— A70, `DueSource`, `WakeupHandler`, `WakeupHint`,
`WakeupSweeper`, best-effort `RootRegistryWakeupHint`.

## Решение

Точный словарь: `DueSource`→`DueWakeupSource`,
`WakeupHandler`→`DueWakeupHandler`, `WakeupHint`→`WakeupScheduleHint`,
`WakeupSweeper`→`DueWakeupSweeper`; `WakeupScheduler` остаётся.

## Почему

A70 принял четыре переименования, но P1 registry ошибочно понёс
только первое. `Hint` остаётся точным: текущая реализация явно
best-effort, а `Schedule` уточняет момент вызова `scheduled(...)`.

## Consequences

P1.3 и `research/P1-design.md` исправляются с 27 на 30 строк;
P15.7 мигрирует wakeup, tenancy, notifications и tasks-workflow одним
public-contract item под G-API-P15.
