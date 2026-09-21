---
id: D84
date: 2026-09-20
status: accepted
item: P14
items: [P14, P14.16, P14.17, P14.21]
supersedes: []
superseded_by: null
---
# D84 — Replay не создаёт новое business event

**Actor:** plan-designer/codex-gpt-6-astra/high
**Evidence:** RAG:— repository reads и predecessor contracts; внешние patterns,
где применимы, подтверждены `research/P14-design-rag.md`. Defaults — локальное решение.

## Решение

Same id/body/destination; bounded authorized range, up to3 cycles/7d, monotonic history. Body7d/metadata30d and inbox dedup retention. Active records never pruned; unknown budget reservation never refunded.

## Почему

`research/P14-design.md` фиксирует проверенные ограничения дерева, failure modes
и отвергнутые альтернативы. Решение сохраняет D7/D9/D14, физический tenant scope
и одну contract authority; не вводит нового consumer-specific слоя.

## Consequences

Items P14.16, P14.17, P14.21 реализуют точный нормативный срез и negative matrix.
Этот design record не является owner grant для G-DDL/G-SECRET или live rollout.
