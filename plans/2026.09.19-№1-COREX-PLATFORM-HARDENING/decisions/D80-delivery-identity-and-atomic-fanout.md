---
id: D80
date: 2026-09-20
status: accepted
item: P14
items: [P14, P14.12, P14.20]
supersedes: []
superseded_by: null
---
# D80 — Fan-out атомарен; событие и доставка имеют разные id

**Actor:** plan-designer/codex-gpt-6-astra/high
**Evidence:** RAG:— repository reads и predecessor contracts; внешние patterns,
где применимы, подтверждены `research/P14-design-rag.md`. Defaults — локальное решение.

## Решение

Inbox + delivery inserts в одной tenant transaction. Unique(subscription,event); webhook-id=delivery id, body has event_id. First successful materialization fixes recipients. Receiver responsible for dedup; no ordering/exactly-once promise.

## Почему

`research/P14-design.md` фиксирует проверенные ограничения дерева, failure modes
и отвергнутые альтернативы. Решение сохраняет D7/D9/D14, физический tenant scope
и одну contract authority; не вводит нового consumer-specific слоя.

## Consequences

Items P14.12, P14.20 реализуют точный нормативный срез и negative matrix.
Этот design record не является owner grant для G-DDL/G-SECRET или live rollout.
